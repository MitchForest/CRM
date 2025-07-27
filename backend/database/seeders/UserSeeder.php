<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Capsule\Manager as DB;

class UserSeeder extends BaseSeeder
{
    private $users = [
        // Admin
        [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'user_name' => 'john.smith',
            'email1' => 'john.smith@techflow.com',
            'title' => 'Sales Operations Manager',
            'department' => 'Operations',
            'is_admin' => 1,
            'phone_work' => '555-0101',
        ],
        // SDR Team
        [
            'first_name' => 'Sarah',
            'last_name' => 'Chen',
            'user_name' => 'sarah.chen',
            'email1' => 'sarah.chen@techflow.com',
            'title' => 'SDR Team Lead',
            'department' => 'Sales',
            'is_admin' => 0,
            'phone_work' => '555-0102',
        ],
        [
            'first_name' => 'Mike',
            'last_name' => 'Johnson',
            'user_name' => 'mike.johnson',
            'email1' => 'mike.johnson@techflow.com',
            'title' => 'Junior SDR',
            'department' => 'Sales',
            'is_admin' => 0,
            'phone_work' => '555-0103',
        ],
        [
            'first_name' => 'Emily',
            'last_name' => 'Rodriguez',
            'user_name' => 'emily.rodriguez',
            'email1' => 'emily.rodriguez@techflow.com',
            'title' => 'Senior SDR',
            'department' => 'Sales',
            'is_admin' => 0,
            'phone_work' => '555-0104',
        ],
        // Account Executive Team
        [
            'first_name' => 'David',
            'last_name' => 'Park',
            'user_name' => 'david.park',
            'email1' => 'david.park@techflow.com',
            'title' => 'Enterprise Account Executive',
            'department' => 'Sales',
            'is_admin' => 0,
            'phone_work' => '555-0105',
        ],
        [
            'first_name' => 'Jessica',
            'last_name' => 'Williams',
            'user_name' => 'jessica.williams',
            'email1' => 'jessica.williams@techflow.com',
            'title' => 'Mid-Market Account Executive',
            'department' => 'Sales',
            'is_admin' => 0,
            'phone_work' => '555-0106',
        ],
        // Customer Success Team
        [
            'first_name' => 'Alex',
            'last_name' => 'Thompson',
            'user_name' => 'alex.thompson',
            'email1' => 'alex.thompson@techflow.com',
            'title' => 'Senior Customer Success Manager',
            'department' => 'Customer Success',
            'is_admin' => 0,
            'phone_work' => '555-0107',
        ],
        [
            'first_name' => 'Maria',
            'last_name' => 'Garcia',
            'user_name' => 'maria.garcia',
            'email1' => 'maria.garcia@techflow.com',
            'title' => 'Customer Success Manager',
            'department' => 'Customer Success',
            'is_admin' => 0,
            'phone_work' => '555-0108',
        ],
        // Support Team
        [
            'first_name' => 'Kevin',
            'last_name' => 'Liu',
            'user_name' => 'kevin.liu',
            'email1' => 'kevin.liu@techflow.com',
            'title' => 'Support Team Lead',
            'department' => 'Support',
            'is_admin' => 0,
            'phone_work' => '555-0109',
        ],
        [
            'first_name' => 'Rachel',
            'last_name' => 'Brown',
            'user_name' => 'rachel.brown',
            'email1' => 'rachel.brown@techflow.com',
            'title' => 'Support Engineer',
            'department' => 'Support',
            'is_admin' => 0,
            'phone_work' => '555-0110',
        ],
    ];
    
    public function run(): void
    {
        echo "Seeding users...\n";
        
        $userIds = [];
        
        foreach ($this->users as $userData) {
            $id = $this->generateUuid();
            $now = new \DateTime();
            
            $user = array_merge($userData, [
                'id' => $id,
                'user_hash' => password_hash('password123', PASSWORD_DEFAULT),
                'status' => 'Active',
                'deleted' => 0,
                'date_entered' => $now->format('Y-m-d H:i:s'),
                'date_modified' => $now->format('Y-m-d H:i:s'),
                'created_by' => '1', // System user
            ]);
            
            DB::table('users')->insert($user);
            $userIds[$userData['user_name']] = $id;
            
            echo "  Created user: {$userData['first_name']} {$userData['last_name']} ({$userData['title']})\n";
        }
        
        // Store user IDs for use in other seeders
        file_put_contents(__DIR__ . '/user_ids.json', json_encode($userIds));
    }
}