<?php

namespace Database\Seeders;

use Illuminate\Database\Capsule\Manager as DB;

class CaseSeeder extends BaseSeeder
{
    private $caseStatuses = [
        'New' => 13,
        'Assigned' => 7,
        'In Progress' => 20,
        'Pending Input' => 10,
        'Resolved' => 35,
        'Closed' => 15,
    ];
    
    private $casePriorities = [
        'Low' => 25,
        'Medium' => 50,
        'High' => 20,
        'Critical' => 5,
    ];
    
    private $caseTypes = [
        'Technical Issue' => 35,
        'Billing Question' => 15,
        'Feature Request' => 20,
        'Account Access' => 10,
        'Training Request' => 10,
        'Bug Report' => 10,
    ];
    
    private $resolutionTypes = [
        'Solved' => 'Issue resolved successfully',
        'Workaround Provided' => 'Temporary workaround provided, permanent fix in progress',
        'User Error' => 'Issue was due to incorrect usage, training provided',
        'Feature Request' => 'Converted to feature request for product team',
        'Known Issue' => 'Known issue, fix scheduled for next release',
        'No Action Needed' => 'Issue resolved itself or was not reproducible',
    ];
    
    public function run(): void
    {
        echo "Seeding support cases...\n";
        
        $userIds = json_decode(file_get_contents(__DIR__ . '/user_ids.json'), true);
        $contactIds = json_decode(file_get_contents(__DIR__ . '/contact_ids.json'), true);
        $accountIds = json_decode(file_get_contents(__DIR__ . '/account_ids.json'), true);
        
        $supportIds = [
            $userIds['kevin.liu'],
            $userIds['rachel.brown']
        ];
        
        $totalCases = 150;
        
        for ($i = 0; $i < $totalCases; $i++) {
            $caseId = $this->generateUuid();
            $createdDate = $this->randomWeekday('-6 months', 'now');
            $status = $this->getRandomByDistribution($this->caseStatuses);
            $priority = $this->getRandomByDistribution($this->casePriorities);
            $type = $this->getRandomByDistribution($this->caseTypes);
            
            // Link to contact and account
            $contactId = $contactIds[array_rand($contactIds)];
            $contact = DB::table('contacts')->where('id', $contactId)->first();
            
            // Get account from contact
            $accountContact = DB::table('accounts_contacts')
                ->where('contact_id', $contactId)
                ->where('deleted', 0)
                ->first();
            
            $accountId = $accountContact ? $accountContact->account_id : null;
            
            // Assign to support team member
            $assignedUserId = $status === 'New' ? null : $supportIds[array_rand($supportIds)];
            
            // Calculate resolution time based on status
            $resolvedDate = null;
            $resolution = null;
            if (in_array($status, ['Resolved', 'Closed'])) {
                $hoursToResolve = $this->getResolutionHours($priority);
                $resolvedDate = clone $createdDate;
                $resolvedDate->modify("+{$hoursToResolve} hours");
                
                // Skip weekends
                while (in_array($resolvedDate->format('N'), ['6', '7'])) {
                    $resolvedDate->modify('+1 day');
                }
                
                $resolution = $this->faker->randomKey($this->resolutionTypes);
            }
            
            $case = [
                'id' => $caseId,
                'name' => $this->generateCaseSubject($type),
                'date_entered' => $createdDate->format('Y-m-d H:i:s'),
                'date_modified' => $createdDate->format('Y-m-d H:i:s'),
                'created_by' => $contactId, // Created by customer
                'modified_user_id' => $assignedUserId,
                'assigned_user_id' => $assignedUserId,
                'deleted' => 0,
                'type' => $type,
                'status' => $status,
                'priority' => $priority,
                'resolution' => $resolution,
                'description' => $this->generateCaseDescription($type, $priority),
                'account_id' => $accountId,
            ];
            
            DB::table('cases')->insert($case);
            
            // Create case-contact relationship
            DB::table('contacts_cases')->insert([
                'id' => $this->generateUuid(),
                'contact_id' => $contactId,
                'case_id' => $caseId,
                'date_modified' => $createdDate->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);
            
            // Add case notes for resolved cases
            if ($status === 'Resolved' || $status === 'Closed') {
                $this->createCaseNotes($caseId, $assignedUserId, $createdDate, $resolvedDate, $resolution);
            }
            
            if ($i % 50 == 0) {
                echo "  Created {$i} cases...\n";
            }
        }
        
        echo "  Created total of {$totalCases} support cases\n";
    }
    
    private function getRandomByDistribution(array $distribution): string
    {
        $rand = mt_rand(1, 100);
        $cumulative = 0;
        
        foreach ($distribution as $value => $percentage) {
            $cumulative += $percentage;
            if ($rand <= $cumulative) {
                return $value;
            }
        }
        
        return array_key_first($distribution);
    }
    
    private function getResolutionHours(string $priority): int
    {
        $hours = [
            'Critical' => mt_rand(1, 4),
            'High' => mt_rand(4, 24),
            'Medium' => mt_rand(24, 72),
            'Low' => mt_rand(72, 168), // Up to 1 week
        ];
        
        return $hours[$priority] ?? 48;
    }
    
    private function generateCaseSubject(string $type): string
    {
        $subjects = [
            'Technical Issue' => [
                'Cannot access dashboard',
                'API returning 500 errors',
                'Export feature not working',
                'Performance issues with large projects',
                'Integration stopped syncing',
                'Mobile app crashing on login',
                'Reports showing incorrect data',
                'Cannot upload attachments',
            ],
            'Billing Question' => [
                'Invoice not received',
                'Need to update payment method',
                'Question about charges',
                'Request for refund',
                'Upgrade plan pricing',
                'Add more user licenses',
                'Annual billing inquiry',
                'Tax exemption request',
            ],
            'Feature Request' => [
                'Add Gantt chart view',
                'Need bulk import feature',
                'Custom field support',
                'Advanced filtering options',
                'API webhook support',
                'SSO integration request',
                'Mobile offline mode',
                'Custom reporting builder',
            ],
            'Account Access' => [
                'Password reset not working',
                'Account locked out',
                'Need to add new admin',
                'Remove former employee access',
                'Two-factor auth issues',
                'Cannot change email address',
                'Merge duplicate accounts',
                'Transfer account ownership',
            ],
            'Training Request' => [
                'Onboarding for new team',
                'Advanced features training',
                'API documentation help',
                'Best practices session',
                'Admin training needed',
                'Report building workshop',
                'Integration setup help',
                'Migration assistance',
            ],
            'Bug Report' => [
                'Button not responding',
                'Data not saving properly',
                'Notification emails not sending',
                'Search returning wrong results',
                'Timeline view display issue',
                'Permissions not working correctly',
                'Duplicate entries appearing',
                'Sync conflict errors',
            ],
        ];
        
        return $this->faker->randomElement($subjects[$type] ?? ['General support request']);
    }
    
    private function generateCaseDescription(string $type, string $priority): string
    {
        $descriptions = [
            'Technical Issue' => [
                'High' => "URGENT: Production system is affected. Users cannot access critical features. This is blocking our team's work. Need immediate assistance.",
                'Medium' => "We're experiencing issues with the system. It's not blocking us completely but is causing delays. Please investigate when possible.",
                'Low' => "Minor issue noticed. Not urgent but would be good to fix. Workaround available but not ideal.",
            ],
            'Billing Question' => [
                'default' => "Billing inquiry regarding our account. Please review and respond at your earliest convenience. Account details included.",
            ],
            'Feature Request' => [
                'default' => "We would like to request a new feature that would greatly benefit our workflow. Currently, we have to use workarounds which are time-consuming.",
            ],
            'Account Access' => [
                'High' => "Cannot access account and it's blocking our work. Tried password reset multiple times. Need urgent help to regain access.",
                'default' => "Having trouble with account access. Tried standard troubleshooting steps but still having issues.",
            ],
            'Training Request' => [
                'default' => "We have new team members joining and would like to schedule a training session. Also interested in learning about advanced features.",
            ],
            'Bug Report' => [
                'default' => "Found a bug in the system. Steps to reproduce:\n1. Navigate to the affected area\n2. Perform the action\n3. Observe the incorrect behavior\n\nExpected: Normal operation\nActual: Error or unexpected result",
            ],
        ];
        
        $template = $descriptions[$type][$priority] ?? $descriptions[$type]['default'] ?? "Support request details: {$type}";
        
        // Add environment details for technical issues
        if ($type === 'Technical Issue' || $type === 'Bug Report') {
            $template .= "\n\nEnvironment: " . $this->faker->randomElement(['Chrome 98', 'Firefox 97', 'Safari 15', 'Edge 98']);
            $template .= "\nOS: " . $this->faker->randomElement(['Windows 10', 'macOS 12.1', 'Ubuntu 20.04']);
            if ($this->randomProbability(50)) {
                $template .= "\nError in console: " . $this->faker->randomElement([
                    'TypeError: Cannot read property of undefined',
                    '500 Internal Server Error',
                    'NetworkError: Failed to fetch',
                    'CORS policy error',
                ]);
            }
        }
        
        return $template;
    }
    
    private function createCaseNotes(string $caseId, string $assignedUserId, \DateTime $createdDate, \DateTime $resolvedDate, string $resolution): void
    {
        // Initial acknowledgment
        $ackDate = clone $createdDate;
        $ackDate->modify('+' . mt_rand(15, 60) . ' minutes');
        
        DB::table('notes')->insert([
            'id' => $this->generateUuid(),
            'name' => 'Case acknowledged',
            'date_entered' => $ackDate->format('Y-m-d H:i:s'),
            'date_modified' => $ackDate->format('Y-m-d H:i:s'),
            'created_by' => $assignedUserId,
            'assigned_user_id' => $assignedUserId,
            'deleted' => 0,
            'parent_type' => 'Cases',
            'parent_id' => $caseId,
            'description' => 'Thank you for contacting support. I\'m looking into this issue and will update you shortly.',
        ]);
        
        // Investigation note
        if ($this->randomProbability(70)) {
            $investigateDate = clone $ackDate;
            $investigateDate->modify('+' . mt_rand(30, 120) . ' minutes');
            
            DB::table('notes')->insert([
                'id' => $this->generateUuid(),
                'name' => 'Investigation update',
                'date_entered' => $investigateDate->format('Y-m-d H:i:s'),
                'date_modified' => $investigateDate->format('Y-m-d H:i:s'),
                'created_by' => $assignedUserId,
                'assigned_user_id' => $assignedUserId,
                'deleted' => 0,
                'parent_type' => 'Cases',
                'parent_id' => $caseId,
                'description' => $this->faker->randomElement([
                    'I\'ve reproduced the issue and identified the cause. Working on a solution.',
                    'Investigated the logs and found the root cause. Implementing a fix.',
                    'This appears to be related to a recent update. Checking with the development team.',
                    'I\'ve found a workaround that should help while we work on a permanent fix.',
                ]),
            ]);
        }
        
        // Resolution note
        DB::table('notes')->insert([
            'id' => $this->generateUuid(),
            'name' => 'Case resolved',
            'date_entered' => $resolvedDate->format('Y-m-d H:i:s'),
            'date_modified' => $resolvedDate->format('Y-m-d H:i:s'),
            'created_by' => $assignedUserId,
            'assigned_user_id' => $assignedUserId,
            'deleted' => 0,
            'parent_type' => 'Cases',
            'parent_id' => $caseId,
            'description' => $this->resolutionTypes[$resolution] . "\n\nPlease let us know if you need any further assistance.",
        ]);
    }
}