<?php

namespace Database\Seeders;

use Illuminate\Database\Capsule\Manager as DB;

class ActivitySeeder extends BaseSeeder
{
    public function run(): void
    {
        echo "Seeding activities (calls, meetings, notes, tasks)...\n";
        
        $userIds = json_decode(file_get_contents(__DIR__ . '/user_ids.json'), true);
        $leadIds = json_decode(file_get_contents(__DIR__ . '/lead_ids.json'), true);
        $contactIds = json_decode(file_get_contents(__DIR__ . '/contact_ids.json'), true);
        $opportunityIds = json_decode(file_get_contents(__DIR__ . '/opportunity_ids.json'), true);
        
        // Create activities for leads
        $this->createLeadActivities($leadIds, $userIds);
        
        // Create activities for opportunities
        $this->createOpportunityActivities($opportunityIds, $userIds);
        
        // Create activities for contacts/accounts
        $this->createContactActivities($contactIds, $userIds);
    }
    
    private function createLeadActivities(array $leadIds, array $userIds): void
    {
        $sdrIds = [
            $userIds['sarah.chen'],
            $userIds['mike.johnson'],
            $userIds['emily.rodriguez']
        ];
        
        $callCount = 0;
        $meetingCount = 0;
        $noteCount = 0;
        $taskCount = 0;
        
        // Process qualified and contacted leads
        $qualifiedLeads = DB::table('leads')
            ->whereIn('status', ['Contacted', 'Qualified', 'Converted'])
            ->pluck('id')
            ->toArray();
        
        foreach ($qualifiedLeads as $leadId) {
            $lead = DB::table('leads')->where('id', $leadId)->first();
            $assignedUser = $lead->assigned_user_id ?? $sdrIds[array_rand($sdrIds)];
            
            // Initial call
            $callDate = new \DateTime($lead->date_entered);
            $callDate->modify('+' . mt_rand(1, 3) . ' hours');
            
            DB::table('calls')->insert([
                'id' => $this->generateUuid(),
                'name' => 'Initial outreach call - ' . $lead->first_name . ' ' . $lead->last_name,
                'date_entered' => $callDate->format('Y-m-d H:i:s'),
                'date_modified' => $callDate->format('Y-m-d H:i:s'),
                'created_by' => $assignedUser,
                'assigned_user_id' => $assignedUser,
                'deleted' => 0,
                'status' => 'Held',
                'direction' => 'Outbound',
                'date_start' => $callDate->format('Y-m-d H:i:s'),
                'duration_hours' => 0,
                'duration_minutes' => 15,
                'parent_type' => 'Leads',
                'parent_id' => $leadId,
                'description' => $this->generateCallDescription('initial', $lead->status),
            ]);
            $callCount++;
            
            // Follow-up note
            $noteDate = (clone $callDate)->modify('+5 minutes');
            DB::table('notes')->insert([
                'id' => $this->generateUuid(),
                'name' => 'Call notes - ' . $lead->account_name,
                'date_entered' => $noteDate->format('Y-m-d H:i:s'),
                'date_modified' => $noteDate->format('Y-m-d H:i:s'),
                'created_by' => $assignedUser,
                'assigned_user_id' => $assignedUser,
                'deleted' => 0,
                'parent_type' => 'Leads',
                'parent_id' => $leadId,
                'description' => $this->generateNoteContent('lead_call', $lead),
            ]);
            $noteCount++;
            
            // Schedule follow-up task
            if ($lead->status !== 'Converted') {
                $taskDate = (clone $callDate)->modify('+3 days');
                DB::table('tasks')->insert([
                    'id' => $this->generateUuid(),
                    'name' => 'Follow up with ' . $lead->first_name,
                    'date_entered' => $callDate->format('Y-m-d H:i:s'),
                    'date_modified' => $callDate->format('Y-m-d H:i:s'),
                    'created_by' => $assignedUser,
                    'assigned_user_id' => $assignedUser,
                    'deleted' => 0,
                    'status' => $this->randomProbability(70) ? 'Completed' : 'In Progress',
                    'priority' => $this->faker->randomElement(['High', 'Medium', 'Low']),
                    'date_due' => $taskDate->format('Y-m-d'),
                    'parent_type' => 'Leads',
                    'parent_id' => $leadId,
                    'description' => 'Send follow-up email with case studies and pricing information',
                ]);
                $taskCount++;
            }
            
            // Demo meeting for qualified leads
            if (in_array($lead->status, ['Qualified', 'Converted'])) {
                $meetingDate = (clone $callDate)->modify('+' . mt_rand(3, 7) . ' days');
                while (in_array($meetingDate->format('N'), ['6', '7'])) {
                    $meetingDate->modify('+1 day');
                }
                
                DB::table('meetings')->insert([
                    'id' => $this->generateUuid(),
                    'name' => 'Product Demo - ' . $lead->account_name,
                    'date_entered' => $callDate->format('Y-m-d H:i:s'),
                    'date_modified' => $callDate->format('Y-m-d H:i:s'),
                    'created_by' => $assignedUser,
                    'assigned_user_id' => $assignedUser,
                    'deleted' => 0,
                    'status' => 'Held',
                    'date_start' => $meetingDate->setTime(14, 0)->format('Y-m-d H:i:s'),
                    'date_end' => $meetingDate->setTime(15, 0)->format('Y-m-d H:i:s'),
                    'duration_hours' => 1,
                    'duration_minutes' => 0,
                    'parent_type' => 'Leads',
                    'parent_id' => $leadId,
                    'location' => 'Zoom',
                    'description' => 'Product demonstration covering:\n- Key features overview\n- Use case discussion\n- Q&A session\n- Next steps',
                ]);
                $meetingCount++;
            }
        }
        
        echo "  Created {$callCount} calls for leads\n";
        echo "  Created {$meetingCount} meetings for leads\n";
        echo "  Created {$noteCount} notes for leads\n";
        echo "  Created {$taskCount} tasks for leads\n";
    }
    
    private function createOpportunityActivities(array $opportunityIds, array $userIds): void
    {
        $aeIds = [
            $userIds['david.park'],
            $userIds['jessica.williams']
        ];
        
        $callCount = 0;
        $meetingCount = 0;
        $noteCount = 0;
        $taskCount = 0;
        
        foreach ($opportunityIds as $oppId) {
            $opp = DB::table('opportunities')->where('id', $oppId)->first();
            $assignedUser = $opp->assigned_user_id;
            
            // Number of activities based on stage
            $activityCount = $this->getActivityCountByStage($opp->sales_stage);
            
            $currentDate = new \DateTime($opp->date_entered);
            
            for ($i = 0; $i < $activityCount; $i++) {
                $currentDate->modify('+' . mt_rand(2, 5) . ' days');
                while (in_array($currentDate->format('N'), ['6', '7'])) {
                    $currentDate->modify('+1 day');
                }
                
                // Mix of activities
                $activityType = $this->faker->randomElement(['call', 'meeting', 'note', 'task']);
                
                switch ($activityType) {
                    case 'call':
                        DB::table('calls')->insert([
                            'id' => $this->generateUuid(),
                            'name' => $this->generateActivityName('call', $opp->sales_stage),
                            'date_entered' => $currentDate->format('Y-m-d H:i:s'),
                            'date_modified' => $currentDate->format('Y-m-d H:i:s'),
                            'created_by' => $assignedUser,
                            'assigned_user_id' => $assignedUser,
                            'deleted' => 0,
                            'status' => 'Held',
                            'direction' => $this->faker->randomElement(['Inbound', 'Outbound']),
                            'date_start' => $currentDate->format('Y-m-d H:i:s'),
                            'duration_hours' => 0,
                            'duration_minutes' => 30,
                            'parent_type' => 'Opportunities',
                            'parent_id' => $oppId,
                            'description' => $this->generateCallDescription('opportunity', $opp->sales_stage),
                        ]);
                        $callCount++;
                        break;
                        
                    case 'meeting':
                        $meetingTime = clone $currentDate;
                        $meetingTime->setTime(mt_rand(10, 16), 0);
                        
                        DB::table('meetings')->insert([
                            'id' => $this->generateUuid(),
                            'name' => $this->generateActivityName('meeting', $opp->sales_stage),
                            'date_entered' => $currentDate->format('Y-m-d H:i:s'),
                            'date_modified' => $currentDate->format('Y-m-d H:i:s'),
                            'created_by' => $assignedUser,
                            'assigned_user_id' => $assignedUser,
                            'deleted' => 0,
                            'status' => 'Held',
                            'date_start' => $meetingTime->format('Y-m-d H:i:s'),
                            'date_end' => (clone $meetingTime)->modify('+60 minutes')->format('Y-m-d H:i:s'),
                            'duration_hours' => 1,
                            'duration_minutes' => 0,
                            'parent_type' => 'Opportunities',
                            'parent_id' => $oppId,
                            'location' => $this->faker->randomElement(['Zoom', 'Conference Room A', 'Client Office', 'Phone']),
                            'description' => $this->generateMeetingDescription($opp->sales_stage),
                        ]);
                        $meetingCount++;
                        break;
                        
                    case 'note':
                        DB::table('notes')->insert([
                            'id' => $this->generateUuid(),
                            'name' => $this->generateActivityName('note', $opp->sales_stage),
                            'date_entered' => $currentDate->format('Y-m-d H:i:s'),
                            'date_modified' => $currentDate->format('Y-m-d H:i:s'),
                            'created_by' => $assignedUser,
                            'assigned_user_id' => $assignedUser,
                            'deleted' => 0,
                            'parent_type' => 'Opportunities',
                            'parent_id' => $oppId,
                            'description' => $this->generateNoteContent('opportunity', $opp),
                        ]);
                        $noteCount++;
                        break;
                        
                    case 'task':
                        $taskDue = clone $currentDate;
                        $taskDue->modify('+' . mt_rand(1, 5) . ' days');
                        
                        DB::table('tasks')->insert([
                            'id' => $this->generateUuid(),
                            'name' => $this->generateActivityName('task', $opp->sales_stage),
                            'date_entered' => $currentDate->format('Y-m-d H:i:s'),
                            'date_modified' => $currentDate->format('Y-m-d H:i:s'),
                            'created_by' => $assignedUser,
                            'assigned_user_id' => $assignedUser,
                            'deleted' => 0,
                            'status' => in_array($opp->sales_stage, ['Closed Won', 'Closed Lost']) ? 'Completed' : 
                                       $this->faker->randomElement(['Not Started', 'In Progress', 'Completed']),
                            'priority' => $this->getTaskPriority($opp->sales_stage),
                            'date_due' => $taskDue->format('Y-m-d'),
                            'parent_type' => 'Opportunities',
                            'parent_id' => $oppId,
                            'description' => $this->generateTaskDescription($opp->sales_stage),
                        ]);
                        $taskCount++;
                        break;
                }
            }
        }
        
        echo "  Created {$callCount} calls for opportunities\n";
        echo "  Created {$meetingCount} meetings for opportunities\n";
        echo "  Created {$noteCount} notes for opportunities\n";
        echo "  Created {$taskCount} tasks for opportunities\n";
    }
    
    private function createContactActivities(array $contactIds, array $userIds): void
    {
        $csmIds = [
            $userIds['alex.thompson'],
            $userIds['maria.garcia']
        ];
        
        $callCount = 0;
        $meetingCount = 0;
        $noteCount = 0;
        
        // Select random subset of contacts for activities
        $activeContacts = array_slice($contactIds, 0, 100);
        shuffle($activeContacts);
        
        foreach ($activeContacts as $contactId) {
            $contact = DB::table('contacts')->where('id', $contactId)->first();
            $assignedUser = $contact->assigned_user_id ?? $csmIds[array_rand($csmIds)];
            
            // Quarterly Business Review meeting
            $qbrDate = $this->randomWeekday('-3 months', '-1 week');
            DB::table('meetings')->insert([
                'id' => $this->generateUuid(),
                'name' => 'Quarterly Business Review',
                'date_entered' => $qbrDate->format('Y-m-d H:i:s'),
                'date_modified' => $qbrDate->format('Y-m-d H:i:s'),
                'created_by' => $assignedUser,
                'assigned_user_id' => $assignedUser,
                'deleted' => 0,
                'status' => 'Held',
                'date_start' => $qbrDate->setTime(10, 0)->format('Y-m-d H:i:s'),
                'date_end' => $qbrDate->setTime(11, 30)->format('Y-m-d H:i:s'),
                'duration_hours' => 1,
                'duration_minutes' => 30,
                'parent_type' => 'Contacts',
                'parent_id' => $contactId,
                'location' => 'Zoom',
                'description' => 'Quarterly Business Review\n- Usage metrics review\n- Success stories\n- Challenges and solutions\n- Roadmap discussion\n- Renewal planning',
            ]);
            $meetingCount++;
            
            // Check-in calls
            if ($this->randomProbability(60)) {
                $callDate = $this->randomWeekday('-2 months', '-1 week');
                DB::table('calls')->insert([
                    'id' => $this->generateUuid(),
                    'name' => 'Monthly check-in call',
                    'date_entered' => $callDate->format('Y-m-d H:i:s'),
                    'date_modified' => $callDate->format('Y-m-d H:i:s'),
                    'created_by' => $assignedUser,
                    'assigned_user_id' => $assignedUser,
                    'deleted' => 0,
                    'status' => 'Held',
                    'direction' => 'Outbound',
                    'date_start' => $callDate->format('Y-m-d H:i:s'),
                    'duration_hours' => 0,
                    'duration_minutes' => 20,
                    'parent_type' => 'Contacts',
                    'parent_id' => $contactId,
                    'description' => 'Monthly check-in to discuss:\n- Current usage\n- Any blockers or issues\n- Feature requests\n- Training needs',
                ]);
                $callCount++;
            }
            
            // Success notes
            if ($this->randomProbability(40)) {
                $noteDate = $this->randomDate('-3 months', '-1 week');
                DB::table('notes')->insert([
                    'id' => $this->generateUuid(),
                    'name' => 'Customer Success Update',
                    'date_entered' => $noteDate->format('Y-m-d H:i:s'),
                    'date_modified' => $noteDate->format('Y-m-d H:i:s'),
                    'created_by' => $assignedUser,
                    'assigned_user_id' => $assignedUser,
                    'deleted' => 0,
                    'parent_type' => 'Contacts',
                    'parent_id' => $contactId,
                    'description' => $this->generateCustomerSuccessNote(),
                ]);
                $noteCount++;
            }
        }
        
        echo "  Created {$callCount} calls for contacts\n";
        echo "  Created {$meetingCount} meetings for contacts\n";
        echo "  Created {$noteCount} notes for contacts\n";
    }
    
    private function getActivityCountByStage(string $stage): int
    {
        $counts = [
            'Prospecting' => mt_rand(2, 4),
            'Qualification' => mt_rand(3, 5),
            'Needs Analysis' => mt_rand(4, 6),
            'Value Proposition' => mt_rand(5, 7),
            'Decision Makers' => mt_rand(6, 8),
            'Proposal/Price Quote' => mt_rand(7, 10),
            'Negotiation/Review' => mt_rand(8, 12),
            'Closed Won' => mt_rand(10, 15),
            'Closed Lost' => mt_rand(5, 8),
        ];
        
        return $counts[$stage] ?? 3;
    }
    
    private function generateActivityName(string $type, string $stage): string
    {
        $names = [
            'call' => [
                'Prospecting' => 'Discovery call',
                'Qualification' => 'Qualification call',
                'Needs Analysis' => 'Requirements discussion',
                'Value Proposition' => 'Value prop review',
                'Decision Makers' => 'Executive briefing call',
                'Proposal/Price Quote' => 'Proposal review call',
                'Negotiation/Review' => 'Contract negotiation',
                'Closed Won' => 'Implementation kickoff call',
                'Closed Lost' => 'Exit interview',
            ],
            'meeting' => [
                'Prospecting' => 'Initial meeting',
                'Qualification' => 'Discovery session',
                'Needs Analysis' => 'Requirements workshop',
                'Value Proposition' => 'Product demonstration',
                'Decision Makers' => 'Executive presentation',
                'Proposal/Price Quote' => 'Proposal presentation',
                'Negotiation/Review' => 'Contract review meeting',
                'Closed Won' => 'Kickoff meeting',
                'Closed Lost' => 'Lessons learned',
            ],
            'note' => [
                'default' => ['Meeting notes', 'Call summary', 'Email follow-up', 'Internal notes', 'Customer feedback'],
            ],
            'task' => [
                'Prospecting' => 'Research company background',
                'Qualification' => 'Prepare discovery questions',
                'Needs Analysis' => 'Document requirements',
                'Value Proposition' => 'Create custom demo',
                'Decision Makers' => 'Prepare executive deck',
                'Proposal/Price Quote' => 'Send proposal',
                'Negotiation/Review' => 'Review contract terms',
                'Closed Won' => 'Schedule implementation',
                'Closed Lost' => 'Update CRM with loss reason',
            ],
        ];
        
        if ($type === 'note') {
            return $this->faker->randomElement($names['note']['default']);
        }
        
        return $names[$type][$stage] ?? ucfirst($type) . ' - ' . $stage;
    }
    
    private function generateCallDescription(string $context, string $stage): string
    {
        if ($context === 'initial') {
            return "Initial outreach call. Discussed their interest in project management solutions. " .
                   "Current pain points include lack of visibility and manual reporting. " .
                   "Scheduled follow-up demo for next week.";
        }
        
        $descriptions = [
            'Prospecting' => "Exploratory call to understand their current process and challenges.",
            'Qualification' => "Qualified budget, authority, need, and timeline. Good fit for our solution.",
            'Needs Analysis' => "Deep dive into specific requirements and use cases.",
            'Value Proposition' => "Reviewed how our solution addresses their specific needs.",
            'Decision Makers' => "Call with executive team to discuss strategic alignment.",
            'Proposal/Price Quote' => "Reviewed proposal details and pricing options.",
            'Negotiation/Review' => "Negotiated contract terms and implementation timeline.",
            'Closed Won' => "Celebration call! Discussed next steps for implementation.",
            'Closed Lost' => "Understanding why we lost the deal for future improvements.",
        ];
        
        return $descriptions[$stage] ?? "Progress update call.";
    }
    
    private function generateNoteContent(string $context, $entity): string
    {
        if ($context === 'lead_call') {
            $templates = [
                "Spoke with {$entity->first_name} about their project management needs. Team size: %d people. " .
                "Main challenges: %s. Very interested in our %s features. Next step: %s",
                
                "Good conversation with {$entity->first_name}. They're currently using %s but looking to switch. " .
                "Budget range: $%d-%d/month. Decision timeline: %s",
                
                "{$entity->first_name} is the %s at {$entity->account_name}. They need a solution that can %s. " .
                "Seemed very interested when I mentioned our %s capability.",
            ];
            
            $template = $this->faker->randomElement($templates);
            
            // Fill in placeholders
            $challenges = ['lack of visibility', 'manual processes', 'poor collaboration', 'no mobile access'];
            $features = ['automation', 'reporting', 'integration', 'mobile app'];
            $nextSteps = ['schedule demo', 'send case studies', 'connect with technical team', 'proposal'];
            $competitors = ['spreadsheets', 'Jira', 'Asana', 'Monday.com', 'no formal system'];
            $needs = ['scale with growth', 'integrate with GitHub', 'support remote teams', 'improve visibility'];
            
            if (strpos($template, '%d people') !== false) {
                $template = sprintf($template, 
                    mt_rand(10, 200),
                    $this->faker->randomElement($challenges),
                    $this->faker->randomElement($features),
                    $this->faker->randomElement($nextSteps)
                );
            } elseif (strpos($template, 'currently using %s') !== false) {
                $template = sprintf($template,
                    $this->faker->randomElement($competitors),
                    mt_rand(50, 500) * 10,
                    mt_rand(100, 1000) * 10,
                    $this->faker->randomElement(['Q1', 'Q2', 'next month', 'ASAP'])
                );
            } else {
                $template = sprintf($template,
                    $entity->title ?? 'decision maker',
                    $this->faker->randomElement($needs),
                    $this->faker->randomElement($features)
                );
            }
            
            return $template;
        }
        
        if ($context === 'opportunity') {
            return "Progress update: " . $this->faker->randomElement([
                "Customer confirmed timeline and budget. Moving forward with proposal.",
                "Addressed technical questions about integration capabilities.",
                "Competitor mentioned: they're also evaluating other solutions.",
                "Strong buying signals. Decision maker is championing internally.",
                "Some concerns about implementation timeline. Provided reassurance.",
                "Excellent meeting. They want to move forward quickly.",
                "Budget approval in progress. Finance has some questions.",
                "Technical evaluation complete. Passed all requirements.",
            ]);
        }
        
        return "General note about account status and next steps.";
    }
    
    private function getMeetingType(string $stage): string
    {
        $types = [
            'Prospecting' => 'Discovery',
            'Qualification' => 'Discovery',
            'Needs Analysis' => 'Requirements',
            'Value Proposition' => 'Demo',
            'Decision Makers' => 'Executive Briefing',
            'Proposal/Price Quote' => 'Proposal Review',
            'Negotiation/Review' => 'Negotiation',
            'Closed Won' => 'Kickoff',
            'Closed Lost' => 'Debrief',
        ];
        
        return $types[$stage] ?? 'General';
    }
    
    private function generateMeetingDescription(string $stage): string
    {
        $descriptions = [
            'Prospecting' => "Agenda:\n- Introductions\n- Company overview\n- Current challenges\n- Solution overview\n- Next steps",
            'Qualification' => "Agenda:\n- Budget discussion\n- Decision process\n- Timeline\n- Technical requirements\n- Success criteria",
            'Needs Analysis' => "Agenda:\n- Current workflow walkthrough\n- Pain points deep dive\n- Must-have features\n- Nice-to-have features\n- Integration requirements",
            'Value Proposition' => "Agenda:\n- Tailored demo\n- ROI discussion\n- Implementation overview\n- Q&A\n- Pricing preview",
            'Decision Makers' => "Agenda:\n- Executive summary\n- Strategic alignment\n- ROI presentation\n- Risk mitigation\n- Partnership approach",
            'Proposal/Price Quote' => "Agenda:\n- Proposal walkthrough\n- Pricing options\n- Implementation timeline\n- Support and training\n- Contract terms",
            'Negotiation/Review' => "Agenda:\n- Outstanding items\n- Contract negotiations\n- SLA discussion\n- Payment terms\n- Signature timeline",
            'Closed Won' => "Agenda:\n- Welcome and introductions\n- Implementation timeline\n- Success metrics\n- Training schedule\n- Quick wins",
            'Closed Lost' => "Agenda:\n- Decision factors\n- What we could improve\n- Future opportunities\n- Competitive feedback\n- Door open for future",
        ];
        
        return $descriptions[$stage] ?? "Standard meeting agenda and notes.";
    }
    
    private function generateTaskDescription(string $stage): string
    {
        $descriptions = [
            'Prospecting' => "Research the company's current tech stack and identify integration points.",
            'Qualification' => "Prepare BANT qualification questions and discovery deck.",
            'Needs Analysis' => "Document all requirements and create solution mapping.",
            'Value Proposition' => "Customize demo environment with their specific use cases.",
            'Decision Makers' => "Create executive presentation focusing on ROI and strategic value.",
            'Proposal/Price Quote' => "Draft proposal with multiple pricing options and implementation plan.",
            'Negotiation/Review' => "Review and respond to contract redlines from legal.",
            'Closed Won' => "Coordinate with implementation team for smooth handoff.",
            'Closed Lost' => "Document loss reasons and add to quarterly analysis.",
        ];
        
        return $descriptions[$stage] ?? "Follow up on action items from last meeting.";
    }
    
    private function getTaskPriority(string $stage): string
    {
        if (in_array($stage, ['Proposal/Price Quote', 'Negotiation/Review', 'Decision Makers'])) {
            return 'High';
        } elseif (in_array($stage, ['Prospecting', 'Closed Lost'])) {
            return 'Low';
        }
        return 'Medium';
    }
    
    private function generateCustomerSuccessNote(): string
    {
        return $this->faker->randomElement([
            "Customer achieving great results! Usage up 40% this month. Team loves the new features.",
            "Identified upsell opportunity. They need more licenses for new team members.",
            "Health check: All green. High engagement and adoption across all departments.",
            "Risk identified: Low usage in past month. Scheduled training session to re-engage.",
            "Success story: Reduced project delivery time by 30% using our automation features.",
            "Feature request: Would like to see Gantt chart functionality. Added to product feedback.",
            "Renewal discussion: Very happy with the platform. Considering annual commitment.",
            "Support ticket follow-up: Issue resolved. Customer satisfied with quick response.",
            "Executive sponsor change. Need to build relationship with new stakeholder.",
            "Expansion opportunity: They're acquiring another company and need more licenses.",
        ]);
    }
}