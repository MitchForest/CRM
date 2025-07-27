<?php

namespace Database\Seeders;

use App\Models\Lead;
use Illuminate\Database\Capsule\Manager as DB;

class LeadSeeder extends BaseSeeder
{
    private $leadSources = [
        'Website' => 35,
        'Webinar' => 15,
        'Trade Show' => 10,
        'Social Media' => 20,
        'Referral' => 10,
        'Cold Call' => 5,
        'Email Campaign' => 5,
    ];
    
    private $leadStatuses = [
        'New' => 30,
        'Contacted' => 20,
        'Qualified' => 15,
        'Unqualified' => 10,
        'Converted' => 25,
    ];
    
    private $companies = [
        'Acme Corporation', 'TechStart Inc', 'Digital Dynamics', 'Cloud Nine Systems',
        'InnovateTech', 'FutureForward LLC', 'DataDriven Co', 'NextGen Solutions',
        'SmartBiz Technologies', 'Quantum Leap Inc', 'Synergy Systems', 'Peak Performance Ltd',
        'Momentum Digital', 'Velocity Ventures', 'Apex Innovations', 'Paradigm Shift Co',
        'Elevate Tech', 'Breakthrough Solutions', 'Visionary Ventures', 'Catalyst Corporation'
    ];
    
    private $titles = [
        'CEO', 'CTO', 'VP of Engineering', 'Engineering Manager', 'Product Manager',
        'Director of Operations', 'VP of Product', 'Head of Development', 'Tech Lead',
        'Solutions Architect', 'Director of IT', 'VP of Technology', 'Chief Product Officer'
    ];
    
    public function run(): void
    {
        echo "Seeding leads...\n";
        
        $userIds = json_decode(file_get_contents(__DIR__ . '/user_ids.json'), true);
        $sdrIds = [
            $userIds['sarah.chen'],
            $userIds['mike.johnson'],
            $userIds['emily.rodriguez']
        ];
        
        $totalLeads = 500;
        $leadIds = [];
        
        for ($i = 0; $i < $totalLeads; $i++) {
            $leadId = $this->generateUuid();
            $createdDate = $this->randomWeekday('-6 months', 'now');
            
            // Assign leads to SDRs with some unassigned
            $assignedUserId = $this->randomProbability(85) ? $sdrIds[array_rand($sdrIds)] : null;
            
            // Determine status based on distribution
            $status = $this->getRandomByDistribution($this->leadStatuses);
            
            // Note: converted_date doesn't exist in schema, conversion is tracked by status
            
            $lead = [
                'id' => $leadId,
                'date_entered' => $createdDate->format('Y-m-d H:i:s'),
                'date_modified' => $createdDate->format('Y-m-d H:i:s'),
                'created_by' => '1',
                'modified_user_id' => $assignedUserId,
                'assigned_user_id' => $assignedUserId,
                'deleted' => 0,
                'salutation' => $this->faker->randomElement(['Mr.', 'Ms.', 'Dr.', '']),
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName(),
                'title' => $this->faker->randomElement($this->titles),
                'department' => $this->faker->randomElement(['Engineering', 'Product', 'Operations', 'IT']),
                'phone_work' => $this->faker->phoneNumber(),
                'phone_mobile' => $this->randomProbability(70) ? $this->faker->phoneNumber() : null,
                'email1' => $this->faker->unique()->safeEmail(),
                'primary_address_street' => $this->faker->streetAddress(),
                'primary_address_city' => $this->faker->city(),
                'primary_address_state' => $this->faker->stateAbbr(),
                'primary_address_postalcode' => $this->faker->postcode(),
                'primary_address_country' => 'USA',
                'status' => $status,
                'status_description' => $this->getStatusDescription($status),
                'lead_source' => $this->getRandomByDistribution($this->leadSources),
                'lead_source_description' => null,
                'description' => $this->generateLeadDescription($status),
                'account_name' => $this->faker->randomElement($this->companies) . ' ' . $this->faker->companySuffix(),
                'website' => $this->faker->domainName()
            ];
            
            DB::table('leads')->insert($lead);
            $leadIds[] = $leadId;
            
            if ($i % 50 == 0) {
                echo "  Created {$i} leads...\n";
            }
        }
        
        echo "  Created total of {$totalLeads} leads\n";
        
        // Store lead IDs for other seeders
        file_put_contents(__DIR__ . '/lead_ids.json', json_encode($leadIds));
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
    
    private function getStatusDescription(string $status): string
    {
        $descriptions = [
            'New' => 'Fresh lead, needs initial contact',
            'Contacted' => 'Initial outreach completed, awaiting response',
            'Qualified' => 'Budget, authority, need, and timeline confirmed',
            'Unqualified' => 'Does not meet our ideal customer profile',
            'Converted' => 'Successfully converted to opportunity',
        ];
        
        return $descriptions[$status] ?? '';
    }
    
    private function generateLeadDescription(string $status): string
    {
        $templates = [
            'New' => [
                'Downloaded our whitepaper on %s',
                'Attended webinar about %s',
                'Visited pricing page multiple times',
                'Requested demo through website form',
                'Met at %s conference',
            ],
            'Contacted' => [
                'Had initial call, interested in %s features',
                'Responded to email campaign about %s',
                'Scheduled follow-up call for next week',
                'Requested more information about pricing',
            ],
            'Qualified' => [
                'Budget approved for Q%d, looking for %s solution',
                'Decision maker confirmed, evaluating %s options',
                'Timeline: Implementation needed by %s',
                'Team of %d users, need enterprise features',
            ],
            'Unqualified' => [
                'Budget constraints - revisit in Q%d',
                'Too small - only %d potential users',
                'Happy with current solution',
                'Not the decision maker',
            ],
            'Converted' => [
                'Moving forward with %s package',
                'Approved budget for %d user licenses',
                'Starting with pilot program for %s team',
                'Enterprise deployment planned for Q%d',
            ],
        ];
        
        $statusTemplates = $templates[$status] ?? $templates['New'];
        $template = $this->faker->randomElement($statusTemplates);
        
        // Fill in template placeholders
        $topics = ['project management', 'team collaboration', 'agile workflows', 'resource planning'];
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        
        // Replace placeholders based on what's in the template
        $args = [];
        
        // Count placeholders
        preg_match_all('/%[sd]/', $template, $matches);
        
        foreach ($matches[0] as $placeholder) {
            if ($placeholder === '%s') {
                $args[] = $this->faker->randomElement($topics);
            } else if ($placeholder === '%d') {
                $args[] = mt_rand(1, 4);
            }
        }
        
        if (!empty($args)) {
            $template = sprintf($template, ...$args);
        }
        
        return $template;
    }
}