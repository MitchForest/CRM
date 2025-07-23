<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Leads/Lead.php');
require_once('modules/Accounts/Account.php');
require_once('modules/Contacts/Contact.php');

// Get API user we created
$admin = new User();
$admin->retrieve('325b037d-b93e-ca36-feac-68813959bdfe');

echo "Seeding demo data...\n";

// Seed Leads with AI scores
$leadData = [
    [
        'first_name' => 'John',
        'last_name' => 'Smith',
        'email1' => 'john.smith@techcorp.com',
        'account_name' => 'TechCorp Inc.',
        'title' => 'CTO',
        'phone_mobile' => '555-0101',
        'lead_source' => 'Website',
        'status' => 'New',
        'ai_score' => 85,
        'ai_insights' => 'High engagement with pricing page. Company size matches ideal customer profile.',
    ],
    [
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'email1' => 'sarah.j@innovate.io',
        'account_name' => 'Innovate.io',
        'title' => 'VP Sales',
        'phone_mobile' => '555-0102',
        'lead_source' => 'Referral',
        'status' => 'Contacted',
        'ai_score' => 72,
        'ai_insights' => 'Referral from existing customer. Mid-market company with growth potential.',
    ],
    [
        'first_name' => 'Michael',
        'last_name' => 'Brown',
        'email1' => 'mbrown@startup.com',
        'account_name' => 'StartupXYZ',
        'title' => 'Founder',
        'phone_mobile' => '555-0103',
        'lead_source' => 'Social Media',
        'status' => 'Qualified',
        'ai_score' => 92,
        'ai_insights' => 'Founder-led startup. Multiple visits to features page. Budget confirmed.',
    ],
    [
        'first_name' => 'Emily',
        'last_name' => 'Davis',
        'email1' => 'emily@enterprise.com',
        'account_name' => 'Enterprise Solutions Ltd',
        'title' => 'Director of IT',
        'phone_mobile' => '555-0104',
        'lead_source' => 'Email Campaign',
        'status' => 'New',
        'ai_score' => 68,
        'ai_insights' => 'Enterprise account but lower engagement. May need nurturing.',
    ],
    [
        'first_name' => 'Robert',
        'last_name' => 'Wilson',
        'email1' => 'rwilson@globaltech.com',
        'account_name' => 'GlobalTech',
        'title' => 'CEO',
        'phone_mobile' => '555-0105',
        'lead_source' => 'Website',
        'status' => 'New',
        'ai_score' => 95,
        'ai_insights' => 'C-level executive. Downloaded whitepaper. High intent signals.',
    ],
];

$leadCount = 0;
foreach ($leadData as $data) {
    $lead = new Lead();
    foreach ($data as $field => $value) {
        $lead->$field = $value;
    }
    $lead->assigned_user_id = $admin->id;
    $lead->ai_score_date = date('Y-m-d H:i:s');
    $lead->save();
    $leadCount++;
}
echo "Created $leadCount leads\n";

// Seed Accounts with health scores
$accountData = [
    [
        'name' => 'Acme Corporation',
        'phone_office' => '555-1001',
        'website' => 'https://acmecorp.com',
        'industry' => 'Technology',
        'annual_revenue' => '5000000',
        'employees' => '100',
        'account_type' => 'Customer',
        'health_score' => 85,
        'mrr' => 4500.00,
        'last_activity' => date('Y-m-d H:i:s', strtotime('-2 days')),
    ],
    [
        'name' => 'Global Innovations Inc',
        'phone_office' => '555-1002',
        'website' => 'https://globalinnovations.com',
        'industry' => 'Healthcare',
        'annual_revenue' => '10000000',
        'employees' => '250',
        'account_type' => 'Customer',
        'health_score' => 92,
        'mrr' => 8500.00,
        'last_activity' => date('Y-m-d H:i:s', strtotime('-1 day')),
    ],
    [
        'name' => 'TechStart Solutions',
        'phone_office' => '555-1003',
        'website' => 'https://techstart.io',
        'industry' => 'Technology',
        'annual_revenue' => '2000000',
        'employees' => '50',
        'account_type' => 'Prospect',
        'health_score' => 0,
        'mrr' => 0,
    ],
    [
        'name' => 'Enterprise Systems Ltd',
        'phone_office' => '555-1004',
        'website' => 'https://enterprisesystems.com',
        'industry' => 'Finance',
        'annual_revenue' => '25000000',
        'employees' => '1000',
        'account_type' => 'Customer',
        'health_score' => 78,
        'mrr' => 15000.00,
        'last_activity' => date('Y-m-d H:i:s', strtotime('-5 days')),
    ],
];

$accountCount = 0;
foreach ($accountData as $data) {
    $account = new Account();
    foreach ($data as $field => $value) {
        $account->$field = $value;
    }
    $account->assigned_user_id = $admin->id;
    $account->save();
    $accountCount++;
}
echo "Created $accountCount accounts\n";

// Create some contacts for the accounts
$contactData = [
    ['first_name' => 'Alice', 'last_name' => 'Anderson', 'email1' => 'alice@acmecorp.com', 'account_name' => 'Acme Corporation'],
    ['first_name' => 'Bob', 'last_name' => 'Brown', 'email1' => 'bob@globalinnovations.com', 'account_name' => 'Global Innovations Inc'],
    ['first_name' => 'Carol', 'last_name' => 'Chen', 'email1' => 'carol@techstart.io', 'account_name' => 'TechStart Solutions'],
    ['first_name' => 'David', 'last_name' => 'Davis', 'email1' => 'david@enterprisesystems.com', 'account_name' => 'Enterprise Systems Ltd'],
];

$contactCount = 0;
foreach ($contactData as $data) {
    $contact = new Contact();
    foreach ($data as $field => $value) {
        $contact->$field = $value;
    }
    $contact->assigned_user_id = $admin->id;
    $contact->save();
    $contactCount++;
}
echo "Created $contactCount contacts\n";

echo "Demo data seeded successfully!\n";