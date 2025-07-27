<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Account;
use Illuminate\Database\Capsule\Manager as DB;

class ContactSeeder extends BaseSeeder
{
    private $accountTypes = [
        'Trial' => 20,
        'Active' => 68,
        'Churned' => 12,
    ];
    
    private $accountSizes = [
        'Small (1-50 employees)' => 40,
        'Medium (51-200 employees)' => 35,
        'Large (201-1000 employees)' => 20,
        'Enterprise (1000+ employees)' => 5,
    ];
    
    private $industries = [
        'Technology', 'Finance', 'Healthcare', 'Retail', 'Manufacturing',
        'Education', 'Media', 'Consulting', 'Real Estate', 'Non-profit'
    ];
    
    public function run(): void
    {
        echo "Seeding accounts and contacts...\n";
        
        $userIds = json_decode(file_get_contents(__DIR__ . '/user_ids.json'), true);
        $leadIds = json_decode(file_get_contents(__DIR__ . '/lead_ids.json'), true);
        
        // CSM assignments
        $csmIds = [
            $userIds['alex.thompson'],
            $userIds['maria.garcia']
        ];
        
        // Get converted leads (we'll convert the last 125 leads)
        $convertedLeadIds = array_slice($leadIds, -125);
        $accountIds = [];
        $contactIds = [];
        
        foreach ($convertedLeadIds as $index => $leadId) {
            // Get lead data
            $lead = DB::table('leads')->where('id', $leadId)->first();
            
            // Create account
            $accountId = $this->generateUuid();
            $accountType = $this->getRandomByDistribution($this->accountTypes);
            $signupDate = new \DateTime($lead->date_entered);
            
            // Calculate MRR based on company size
            $accountSize = $this->getRandomByDistribution($this->accountSizes);
            $employeeCount = $this->getEmployeeCount($accountSize);
            $mrr = $this->calculateMRR($employeeCount, $accountType);
            
            $account = [
                'id' => $accountId,
                'name' => $lead->account_name,
                'date_entered' => $signupDate->format('Y-m-d H:i:s'),
                'date_modified' => $signupDate->format('Y-m-d H:i:s'),
                'created_by' => '1',
                'modified_user_id' => $csmIds[array_rand($csmIds)],
                'assigned_user_id' => $csmIds[array_rand($csmIds)],
                'deleted' => 0,
                'account_type' => $accountType,
                'industry' => $this->faker->randomElement($this->industries),
                'annual_revenue' => $mrr * 12,
                'phone_office' => $lead->phone_work,
                'website' => $lead->website,
                'employees' => $employeeCount,
                'billing_address_street' => $lead->primary_address_street,
                'billing_address_city' => $lead->primary_address_city,
                'billing_address_state' => $lead->primary_address_state,
                'billing_address_postalcode' => $lead->primary_address_postalcode,
                'billing_address_country' => $lead->primary_address_country,
                'description' => $this->generateAccountDescription($accountType, $mrr, $employeeCount),
            ];
            
            DB::table('accounts')->insert($account);
            $accountIds[] = $accountId;
            
            // Create primary contact from lead
            $contactId = $this->generateUuid();
            $contact = [
                'id' => $contactId,
                'date_entered' => $signupDate->format('Y-m-d H:i:s'),
                'date_modified' => $signupDate->format('Y-m-d H:i:s'),
                'created_by' => '1',
                'modified_user_id' => $account['assigned_user_id'],
                'assigned_user_id' => $account['assigned_user_id'],
                'deleted' => 0,
                'salutation' => $lead->salutation,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'title' => $lead->title,
                'department' => $lead->department,
                'phone_work' => $lead->phone_work,
                'phone_mobile' => $lead->phone_mobile,
                'email1' => $lead->email1,
                'primary_address_street' => $lead->primary_address_street,
                'primary_address_city' => $lead->primary_address_city,
                'primary_address_state' => $lead->primary_address_state,
                'primary_address_postalcode' => $lead->primary_address_postalcode,
                'primary_address_country' => $lead->primary_address_country,
                'lead_source' => $lead->lead_source,
                'description' => 'Primary contact - ' . $lead->description,
            ];
            
            DB::table('contacts')->insert($contact);
            $contactIds[] = $contactId;
            
            // Create account-contact relationship
            DB::table('accounts_contacts')->insert([
                'id' => $this->generateUuid(),
                'contact_id' => $contactId,
                'account_id' => $accountId,
                'date_modified' => $signupDate->format('Y-m-d H:i:s'),
                'deleted' => 0,
            ]);
            
            // Update lead status to converted
            DB::table('leads')
                ->where('id', $leadId)
                ->update([
                    'status' => 'Converted',
                    'date_modified' => (new \DateTime())->format('Y-m-d H:i:s')
                ]);
            
            // Create 1-3 additional contacts per account
            $additionalContacts = mt_rand(1, 3);
            for ($j = 0; $j < $additionalContacts; $j++) {
                $additionalContactId = $this->generateUuid();
                $additionalContact = [
                    'id' => $additionalContactId,
                    'date_entered' => $this->randomDate($signupDate->format('Y-m-d'), 'now')->format('Y-m-d H:i:s'),
                    'date_modified' => $this->randomDate($signupDate->format('Y-m-d'), 'now')->format('Y-m-d H:i:s'),
                    'created_by' => $account['assigned_user_id'],
                    'modified_user_id' => $account['assigned_user_id'],
                    'assigned_user_id' => $account['assigned_user_id'],
                    'deleted' => 0,
                    'salutation' => $this->faker->randomElement(['Mr.', 'Ms.', 'Dr.', '']),
                    'first_name' => $this->faker->firstName(),
                    'last_name' => $this->faker->lastName(),
                    'title' => $this->faker->randomElement($this->titles ?? ['Manager', 'Director', 'Analyst', 'Specialist']),
                    'department' => $this->faker->randomElement(['Engineering', 'Product', 'Operations', 'Finance', 'HR']),
                    'phone_work' => $this->faker->phoneNumber(),
                    'phone_mobile' => $this->randomProbability(60) ? $this->faker->phoneNumber() : null,
                    'email1' => $this->faker->unique()->safeEmail(),
                    'primary_address_street' => $account['billing_address_street'],
                    'primary_address_city' => $account['billing_address_city'],
                    'primary_address_state' => $account['billing_address_state'],
                    'primary_address_postalcode' => $account['billing_address_postalcode'],
                    'primary_address_country' => $account['billing_address_country'],
                    'lead_source' => 'Existing Customer',
                    'description' => 'Additional contact at ' . $account['name'],
                ];
                
                DB::table('contacts')->insert($additionalContact);
                $contactIds[] = $additionalContactId;
                
                // Create account-contact relationship
                DB::table('accounts_contacts')->insert([
                    'id' => $this->generateUuid(),
                    'contact_id' => $additionalContactId,
                    'account_id' => $accountId,
                    'date_modified' => $additionalContact['date_entered'],
                    'deleted' => 0,
                ]);
            }
            
            if ($index % 25 == 0) {
                echo "  Created {$index} accounts with contacts...\n";
            }
        }
        
        echo "  Created total of 125 accounts with ~375 contacts\n";
        
        // Store IDs for other seeders
        file_put_contents(__DIR__ . '/account_ids.json', json_encode($accountIds));
        file_put_contents(__DIR__ . '/contact_ids.json', json_encode($contactIds));
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
    
    private function getEmployeeCount(string $size): int
    {
        $ranges = [
            'Small (1-50 employees)' => [1, 50],
            'Medium (51-200 employees)' => [51, 200],
            'Large (201-1000 employees)' => [201, 1000],
            'Enterprise (1000+ employees)' => [1001, 5000],
        ];
        
        $range = $ranges[$size] ?? [10, 100];
        return mt_rand($range[0], $range[1]);
    }
    
    private function calculateMRR(int $employees, string $accountType): float
    {
        // Base price per user
        $pricePerUser = 25;
        
        // Estimate users based on employees (10-30% of employees use the tool)
        $userPercentage = mt_rand(10, 30) / 100;
        $users = max(5, (int)($employees * $userPercentage));
        
        // Volume discounts
        if ($users > 100) $pricePerUser *= 0.8;
        elseif ($users > 50) $pricePerUser *= 0.9;
        
        $mrr = $users * $pricePerUser;
        
        // Trial accounts have $0 MRR
        if ($accountType === 'Trial') {
            return 0;
        }
        
        // Churned accounts had MRR but now $0
        if ($accountType === 'Churned') {
            return 0;
        }
        
        return round($mrr, 2);
    }
    
    private function generateAccountDescription(string $type, float $mrr, int $employees): string
    {
        $descriptions = [
            'Trial' => "Currently in %d-day trial period. %d employees, potential MRR: $%s",
            'Active' => "Active customer since signup. %d employees, %d users, MRR: $%s",
            'Churned' => "Churned customer. Previous MRR was $%s. Reason: %s",
        ];
        
        $template = $descriptions[$type];
        
        if ($type === 'Trial') {
            return sprintf($template, mt_rand(14, 30), $employees, number_format($this->calculateMRR($employees, 'Active'), 2));
        } elseif ($type === 'Active') {
            $users = max(5, (int)($employees * (mt_rand(10, 30) / 100)));
            return sprintf($template, $employees, $users, number_format($mrr, 2));
        } else {
            $reasons = ['Budget constraints', 'Switched to competitor', 'No longer needed', 'Poor adoption'];
            $previousMrr = $this->calculateMRR($employees, 'Active');
            return sprintf($template, number_format($previousMrr, 2), $this->faker->randomElement($reasons));
        }
    }
}