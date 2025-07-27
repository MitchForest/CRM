<?php

namespace Database\Seeders;

use App\Models\Opportunity;
use Illuminate\Database\Capsule\Manager as DB;

class OpportunitySeeder extends BaseSeeder
{
    private $salesStages = [
        'Prospecting' => 20,
        'Qualification' => 15,
        'Needs Analysis' => 15,
        'Value Proposition' => 10,
        'Decision Makers' => 10,
        'Proposal/Price Quote' => 10,
        'Negotiation/Review' => 5,
        'Closed Won' => 10,
        'Closed Lost' => 5,
    ];
    
    private $leadSources = [
        'Existing Customer' => 30,
        'Self Generated' => 25,
        'Employee' => 5,
        'Partner' => 10,
        'Website' => 20,
        'Trade Show' => 5,
        'Word of mouth' => 5,
    ];
    
    private $opportunityTypes = [
        'New Business' => 60,
        'Existing Business' => 30,
        'Renewal' => 10,
    ];
    
    public function run(): void
    {
        echo "Seeding opportunities...\n";
        
        $userIds = json_decode(file_get_contents(__DIR__ . '/user_ids.json'), true);
        $accountIds = json_decode(file_get_contents(__DIR__ . '/account_ids.json'), true);
        
        // Account Executives who own opportunities
        $aeIds = [
            $userIds['david.park'],
            $userIds['jessica.williams']
        ];
        
        $totalOpportunities = 200;
        $opportunityIds = [];
        
        for ($i = 0; $i < $totalOpportunities; $i++) {
            $opportunityId = $this->generateUuid();
            $createdDate = $this->randomWeekday('-6 months', 'now');
            $salesStage = $this->getRandomByDistribution($this->salesStages);
            
            // Assign to AEs
            $assignedUserId = $aeIds[array_rand($aeIds)];
            
            // Calculate close date based on stage
            $closeDate = $this->calculateCloseDate($createdDate, $salesStage);
            
            // Calculate amount based on opportunity type and stage
            $amount = $this->calculateAmount($salesStage);
            $probability = $this->getStageProbability($salesStage);
            
            // Link to account (70% linked to existing accounts, 30% new)
            $accountId = $this->randomProbability(70) && count($accountIds) > 0 
                ? $accountIds[array_rand($accountIds)] 
                : null;
            
            $opportunity = [
                'id' => $opportunityId,
                'name' => $this->generateOpportunityName($salesStage),
                'date_entered' => $createdDate->format('Y-m-d H:i:s'),
                'date_modified' => $createdDate->format('Y-m-d H:i:s'),
                'created_by' => $assignedUserId,
                'modified_user_id' => $assignedUserId,
                'assigned_user_id' => $assignedUserId,
                'deleted' => 0,
                'opportunity_type' => $this->getRandomByDistribution($this->opportunityTypes),
                'account_id' => $accountId,
                'lead_source' => $this->getRandomByDistribution($this->leadSources),
                'amount' => $amount,
                'amount_usdollar' => $amount, // Same as amount for simplicity
                'date_closed' => $closeDate->format('Y-m-d'),
                'sales_stage' => $salesStage,
                'probability' => $probability,
                'description' => $this->generateOpportunityDescription($salesStage, $amount),
            ];
            
            DB::table('opportunities')->insert($opportunity);
            $opportunityIds[] = $opportunityId;
            
            // Create opportunity-contact relationships for closed won opportunities
            if ($salesStage === 'Closed Won' && $accountId) {
                $this->createOpportunityContacts($opportunityId, $accountId);
            }
            
            if ($i % 50 == 0) {
                echo "  Created {$i} opportunities...\n";
            }
        }
        
        echo "  Created total of {$totalOpportunities} opportunities\n";
        
        // Store opportunity IDs for other seeders
        file_put_contents(__DIR__ . '/opportunity_ids.json', json_encode($opportunityIds));
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
    
    private function calculateCloseDate(\DateTime $createdDate, string $stage): \DateTime
    {
        $closeDate = clone $createdDate;
        
        switch ($stage) {
            case 'Closed Won':
            case 'Closed Lost':
                // Closed deals: close date is in the past (10-60 days after creation)
                $daysToClose = mt_rand(10, 60);
                $closeDate->modify("+{$daysToClose} days");
                break;
            
            case 'Negotiation/Review':
            case 'Proposal/Price Quote':
                // Late stage: close date is soon (5-20 days from now)
                $closeDate = new \DateTime();
                $closeDate->modify('+' . mt_rand(5, 20) . ' days');
                break;
            
            case 'Decision Makers':
            case 'Value Proposition':
                // Mid stage: close date is 3-6 weeks out
                $closeDate = new \DateTime();
                $closeDate->modify('+' . mt_rand(21, 42) . ' days');
                break;
            
            default:
                // Early stage: close date is 1-3 months out
                $closeDate = new \DateTime();
                $closeDate->modify('+' . mt_rand(30, 90) . ' days');
                break;
        }
        
        return $closeDate;
    }
    
    private function calculateAmount(string $stage): float
    {
        // Base amount ranges
        $ranges = [
            'small' => [1000, 5000],
            'medium' => [5000, 25000],
            'large' => [25000, 100000],
            'enterprise' => [100000, 500000],
        ];
        
        // Distribution of deal sizes
        $distribution = [
            'small' => 40,
            'medium' => 35,
            'large' => 20,
            'enterprise' => 5,
        ];
        
        $size = $this->getRandomByDistribution($distribution);
        $range = $ranges[$size];
        $amount = mt_rand($range[0], $range[1]);
        
        // Round to nice numbers
        if ($amount > 10000) {
            $amount = round($amount / 1000) * 1000;
        } elseif ($amount > 1000) {
            $amount = round($amount / 100) * 100;
        }
        
        // Lost deals might have had different amounts
        if ($stage === 'Closed Lost') {
            $amount = $amount * $this->faker->randomFloat(2, 0.8, 1.2);
        }
        
        return $amount;
    }
    
    private function getStageProbability(string $stage): int
    {
        $probabilities = [
            'Prospecting' => 10,
            'Qualification' => 20,
            'Needs Analysis' => 25,
            'Value Proposition' => 50,
            'Decision Makers' => 60,
            'Proposal/Price Quote' => 75,
            'Negotiation/Review' => 90,
            'Closed Won' => 100,
            'Closed Lost' => 0,
        ];
        
        return $probabilities[$stage] ?? 10;
    }
    
    private function generateOpportunityName(string $stage): string
    {
        $products = [
            'TechFlow Pro - Annual',
            'TechFlow Enterprise - 3 Year',
            'TechFlow Team - Monthly',
            'TechFlow Starter - Annual',
            'TechFlow Plus Upgrade',
            'TechFlow Add-on Package',
            'TechFlow Professional Services',
            'TechFlow Training Package',
        ];
        
        $product = $this->faker->randomElement($products);
        $company = $this->faker->company();
        
        return "{$company} - {$product}";
    }
    
    private function generateOpportunityDescription(string $stage, float $amount): string
    {
        $userCount = max(5, (int)($amount / 250)); // Rough estimate of users
        
        $templates = [
            'Prospecting' => 'Initial opportunity identified. Potential for %d users. Estimated value: $%s',
            'Qualification' => 'Qualifying budget and decision process. Looking for solution for %d users.',
            'Needs Analysis' => 'Conducting needs analysis. Requirements: %s. Budget approved for %d users.',
            'Value Proposition' => 'Presented value prop. Strong interest in %s features. %d user licenses.',
            'Decision Makers' => 'Meeting with decision makers scheduled. Competing against %s.',
            'Proposal/Price Quote' => 'Proposal submitted for %d users at $%s. Decision expected within 2 weeks.',
            'Negotiation/Review' => 'In final negotiations. Agreed on %d users. Working on contract terms.',
            'Closed Won' => 'Deal closed! %d users at $%s. Implementation to begin %s.',
            'Closed Lost' => 'Lost to %s. Price was a factor. They needed %s which we don\'t offer.',
        ];
        
        $template = $templates[$stage] ?? 'Opportunity for %d users valued at $%s';
        
        // Generate description based on stage
        switch ($stage) {
            case 'Prospecting':
            case 'Proposal/Price Quote':
                return sprintf($template, $userCount, number_format($amount, 2));
                
            case 'Needs Analysis':
                $requirements = $this->faker->randomElement(['real-time collaboration', 'advanced reporting', 'API access', 'enterprise security']);
                return sprintf($template, $requirements, $userCount);
                
            case 'Value Proposition':
                $features = $this->faker->randomElement(['automation', 'integration', 'analytics', 'collaboration']);
                return sprintf($template, $features, $userCount);
                
            case 'Decision Makers':
                $competitor = $this->faker->randomElement(['Jira', 'Asana', 'Monday.com', 'Trello']);
                return sprintf($template, $competitor);
                
            case 'Negotiation/Review':
                return sprintf($template, $userCount);
                
            case 'Closed Won':
                $startDate = $this->faker->randomElement(['next week', 'next month', 'in 2 weeks']);
                return sprintf($template, $userCount, number_format($amount, 2), $startDate);
                
            case 'Closed Lost':
                $competitor = $this->faker->randomElement(['Jira', 'Asana', 'internal solution', 'no decision']);
                $missingFeature = $this->faker->randomElement(['Gantt charts', 'time tracking', 'resource management', 'mobile app']);
                return sprintf($template, $competitor, $missingFeature);
                
            default:
                return sprintf($template, $userCount, number_format($amount, 2));
        }
    }
    
    private function createOpportunityContacts(string $opportunityId, string $accountId): void
    {
        // Get contacts for this account
        $contacts = DB::table('accounts_contacts')
            ->where('account_id', $accountId)
            ->where('deleted', 0)
            ->limit(mt_rand(1, 3))
            ->get();
        
        foreach ($contacts as $accountContact) {
            DB::table('opportunities_contacts')->insert([
                'id' => $this->generateUuid(),
                'contact_id' => $accountContact->contact_id,
                'opportunity_id' => $opportunityId,
                'date_modified' => (new \DateTime())->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);
        }
    }
}