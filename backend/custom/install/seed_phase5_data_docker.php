<?php
/**
 * Phase 5 Data Seeding Script - Seeds production-ready test data
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line\n");
}

// Load SuiteCRM bootstrap
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Use absolute path for Docker container
require_once('/var/www/html/include/entryPoint.php');

global $db, $current_user;

// Create admin user first
$adminUser = BeanFactory::newBean('Users');
$adminUser->user_name = 'admin';
$adminUser->first_name = 'Admin';
$adminUser->last_name = 'User';
$adminUser->email1 = 'admin@example.com';
$adminUser->status = 'Active';
$adminUser->is_admin = 1;
$adminUser->user_hash = password_hash('admin123', PASSWORD_DEFAULT);
$adminUser->save();

// Set current user
$current_user = $adminUser;

echo "\n=== Phase 5 Data Seeding Script ===\n";
echo "Creating production-ready test data...\n\n";

// Helper function to create beans
function createBean($module, $data) {
    $bean = BeanFactory::newBean($module);
    foreach ($data as $field => $value) {
        $bean->$field = $value;
    }
    $bean->save();
    return $bean;
}

// 1. Create test users
echo "Creating users...\n";

$users = [
    [
        'user_name' => 'john.doe',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email1' => 'john.doe@example.com',
        'status' => 'Active',
        'is_admin' => 1,
        'user_hash' => password_hash('admin123', PASSWORD_DEFAULT),
    ],
    [
        'user_name' => 'jane.smith',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email1' => 'jane.smith@example.com',
        'status' => 'Active',
        'is_admin' => 0,
        'user_hash' => password_hash('user123', PASSWORD_DEFAULT),
    ],
    [
        'user_name' => 'mike.wilson',
        'first_name' => 'Mike',
        'last_name' => 'Wilson',
        'email1' => 'mike.wilson@example.com',
        'status' => 'Active',
        'is_admin' => 0,
        'user_hash' => password_hash('user123', PASSWORD_DEFAULT),
    ],
];

$userBeans = [];
foreach ($users as $userData) {
    $user = createBean('Users', $userData);
    $userBeans[] = $user;
    echo "  Created user: {$user->user_name}\n";
}

// 2. Create contacts (unified - both people and companies)
echo "\nCreating contacts...\n";

$contacts = [
    // Companies
    [
        'first_name' => 'Acme',
        'last_name' => 'Corporation',
        'account_name' => 'Acme Corporation',
        'email1' => 'info@acme.com',
        'phone_work' => '555-0100',
        'primary_address_street' => '123 Business Ave',
        'primary_address_city' => 'New York',
        'primary_address_state' => 'NY',
        'primary_address_postalcode' => '10001',
        'description' => 'Large enterprise customer',
        'assigned_user_id' => $userBeans[0]->id,
    ],
    [
        'first_name' => 'Tech',
        'last_name' => 'Solutions Inc',
        'account_name' => 'Tech Solutions Inc',
        'email1' => 'contact@techsolutions.com',
        'phone_work' => '555-0200',
        'primary_address_street' => '456 Innovation Blvd',
        'primary_address_city' => 'San Francisco',
        'primary_address_state' => 'CA',
        'primary_address_postalcode' => '94105',
        'description' => 'Technology partner',
        'assigned_user_id' => $userBeans[1]->id,
    ],
    // People
    [
        'first_name' => 'Sarah',
        'last_name' => 'Johnson',
        'title' => 'CEO',
        'account_name' => 'Acme Corporation',
        'email1' => 'sarah.johnson@acme.com',
        'phone_work' => '555-0101',
        'phone_mobile' => '555-0111',
        'department' => 'Executive',
        'assigned_user_id' => $userBeans[0]->id,
    ],
    [
        'first_name' => 'Robert',
        'last_name' => 'Chen',
        'title' => 'CTO',
        'account_name' => 'Tech Solutions Inc',
        'email1' => 'robert.chen@techsolutions.com',
        'phone_work' => '555-0201',
        'phone_mobile' => '555-0211',
        'department' => 'Technology',
        'assigned_user_id' => $userBeans[1]->id,
    ],
    [
        'first_name' => 'Emily',
        'last_name' => 'Davis',
        'title' => 'Marketing Manager',
        'email1' => 'emily.davis@gmail.com',
        'phone_mobile' => '555-0301',
        'description' => 'Interested in our services',
        'assigned_user_id' => $userBeans[2]->id,
    ],
];

$contactBeans = [];
foreach ($contacts as $contactData) {
    $contact = createBean('Contacts', $contactData);
    $contactBeans[] = $contact;
    echo "  Created contact: {$contact->first_name} {$contact->last_name}\n";
}

// 3. Create leads
echo "\nCreating leads...\n";

$leads = [
    [
        'first_name' => 'David',
        'last_name' => 'Thompson',
        'title' => 'VP Sales',
        'company' => 'Global Industries',
        'email' => 'david.thompson@global.com',
        'phone' => '555-0400',
        'website' => 'https://globalindustries.com',
        'status' => 'New',
        'source' => 'Website',
        'description' => 'Interested in enterprise solution',
        'assigned_user_id' => $userBeans[0]->id,
    ],
    [
        'first_name' => 'Lisa',
        'last_name' => 'Anderson',
        'title' => 'Director of Operations',
        'company' => 'Retail Chain LLC',
        'email' => 'lisa.anderson@retailchain.com',
        'phone' => '555-0500',
        'status' => 'Contacted',
        'source' => 'Trade Show',
        'description' => 'Met at trade show, needs follow-up',
        'assigned_user_id' => $userBeans[1]->id,
    ],
    [
        'first_name' => 'Michael',
        'last_name' => 'Brown',
        'company' => 'Startup Ventures',
        'email' => 'michael.brown@startupventures.io',
        'phone' => '555-0600',
        'status' => 'Qualified',
        'source' => 'Referral',
        'description' => 'Ready to move to opportunity',
        'assigned_user_id' => $userBeans[2]->id,
    ],
];

$leadBeans = [];
foreach ($leads as $leadData) {
    $leadData['lead_source'] = $leadData['source'];
    unset($leadData['source']);
    $leadData['email1'] = $leadData['email'];
    unset($leadData['email']);
    $leadData['phone_work'] = $leadData['phone'];
    unset($leadData['phone']);
    $leadData['account_name'] = $leadData['company'];
    unset($leadData['company']);
    
    $lead = createBean('Leads', $leadData);
    $leadBeans[] = $lead;
    echo "  Created lead: {$lead->first_name} {$lead->last_name} ({$lead->status})\n";
}

// 4. Create opportunities
echo "\nCreating opportunities...\n";

$opportunities = [
    [
        'name' => 'Acme Corp Enterprise Deal',
        'amount' => 250000,
        'sales_stage' => 'Proposal',
        'probability' => 60,
        'date_closed' => date('Y-m-d', strtotime('+30 days')),
        'opportunity_type' => 'New Business',
        'description' => 'Large enterprise software deployment',
        'assigned_user_id' => $userBeans[0]->id,
    ],
    [
        'name' => 'Tech Solutions Integration',
        'amount' => 150000,
        'sales_stage' => 'Negotiation',
        'probability' => 80,
        'date_closed' => date('Y-m-d', strtotime('+15 days')),
        'opportunity_type' => 'Existing Business',
        'description' => 'System integration project',
        'assigned_user_id' => $userBeans[1]->id,
    ],
    [
        'name' => 'Global Industries Pilot',
        'amount' => 50000,
        'sales_stage' => 'Qualified',
        'probability' => 30,
        'date_closed' => date('Y-m-d', strtotime('+45 days')),
        'opportunity_type' => 'New Business',
        'description' => 'Pilot program for new client',
        'assigned_user_id' => $userBeans[0]->id,
    ],
    [
        'name' => 'Retail Chain Implementation',
        'amount' => 180000,
        'sales_stage' => 'Won',
        'probability' => 100,
        'date_closed' => date('Y-m-d', strtotime('-5 days')),
        'opportunity_type' => 'New Business',
        'description' => 'Successfully closed deal',
        'assigned_user_id' => $userBeans[1]->id,
    ],
    [
        'name' => 'Startup Ventures Basic Package',
        'amount' => 25000,
        'sales_stage' => 'Lost',
        'probability' => 0,
        'date_closed' => date('Y-m-d', strtotime('-10 days')),
        'opportunity_type' => 'New Business',
        'description' => 'Lost to competitor',
        'assigned_user_id' => $userBeans[2]->id,
    ],
];

$opportunityBeans = [];
foreach ($opportunities as $oppData) {
    $opp = createBean('Opportunities', $oppData);
    $opportunityBeans[] = $opp;
    
    // Link to accounts/contacts
    if ($opp->sales_stage === 'Won' || $opp->sales_stage === 'Lost') {
        $opp->load_relationship('contacts');
        $opp->contacts->add($contactBeans[0]->id);
    }
    
    echo "  Created opportunity: {$opp->name} ({$opp->sales_stage})\n";
}

// 5. Create cases (support tickets)
echo "\nCreating support tickets...\n";

$cases = [
    [
        'name' => 'Login issues with new system',
        'status' => 'Open',
        'priority' => 'High',
        'type' => 'Technical',
        'description' => 'Customer cannot login after password reset',
        'assigned_user_id' => $userBeans[0]->id,
    ],
    [
        'name' => 'Feature request: Export functionality',
        'status' => 'In Progress',
        'priority' => 'Medium',
        'type' => 'Enhancement',
        'description' => 'Customer requesting CSV export feature',
        'assigned_user_id' => $userBeans[1]->id,
    ],
    [
        'name' => 'Billing discrepancy',
        'status' => 'Resolved',
        'priority' => 'High',
        'type' => 'Billing',
        'description' => 'Customer was charged incorrect amount',
        'resolution' => 'Refund processed and billing corrected',
        'assigned_user_id' => $userBeans[2]->id,
    ],
    [
        'name' => 'Performance issues',
        'status' => 'Closed',
        'priority' => 'Low',
        'type' => 'Technical',
        'description' => 'System running slowly during peak hours',
        'resolution' => 'Upgraded server resources',
        'assigned_user_id' => $userBeans[0]->id,
    ],
];

$caseBeans = [];
foreach ($cases as $caseData) {
    $case = createBean('Cases', $caseData);
    $caseBeans[] = $case;
    
    // Link to contacts
    $case->load_relationship('contacts');
    $case->contacts->add($contactBeans[rand(0, count($contactBeans) - 1)]->id);
    
    echo "  Created case: {$case->name} ({$case->status})\n";
}

// 6. Create activities
echo "\nCreating activities...\n";

// Calls
$calls = [
    [
        'name' => 'Initial discovery call with Acme',
        'status' => 'Held',
        'direction' => 'Outbound',
        'duration_hours' => 0,
        'duration_minutes' => 45,
        'date_start' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'assigned_user_id' => $userBeans[0]->id,
        'parent_type' => 'Opportunities',
        'parent_id' => $opportunityBeans[0]->id,
    ],
    [
        'name' => 'Follow-up call with Tech Solutions',
        'status' => 'Planned',
        'direction' => 'Outbound',
        'duration_hours' => 0,
        'duration_minutes' => 30,
        'date_start' => date('Y-m-d H:i:s', strtotime('+2 days')),
        'assigned_user_id' => $userBeans[1]->id,
        'parent_type' => 'Opportunities',
        'parent_id' => $opportunityBeans[1]->id,
    ],
];

foreach ($calls as $callData) {
    $call = createBean('Calls', $callData);
    echo "  Created call: {$call->name}\n";
}

// Meetings
$meetings = [
    [
        'name' => 'Product demo for Acme team',
        'status' => 'Held',
        'duration_hours' => 1,
        'duration_minutes' => 30,
        'date_start' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'location' => 'Conference Room A',
        'assigned_user_id' => $userBeans[0]->id,
        'parent_type' => 'Opportunities',
        'parent_id' => $opportunityBeans[0]->id,
    ],
    [
        'name' => 'Contract negotiation meeting',
        'status' => 'Planned',
        'duration_hours' => 2,
        'duration_minutes' => 0,
        'date_start' => date('Y-m-d H:i:s', strtotime('+5 days')),
        'location' => 'Client office',
        'assigned_user_id' => $userBeans[1]->id,
        'parent_type' => 'Opportunities',
        'parent_id' => $opportunityBeans[1]->id,
    ],
];

foreach ($meetings as $meetingData) {
    $meeting = createBean('Meetings', $meetingData);
    echo "  Created meeting: {$meeting->name}\n";
}

// Tasks
$tasks = [
    [
        'name' => 'Send proposal to Acme',
        'status' => 'Completed',
        'priority' => 'High',
        'date_due' => date('Y-m-d', strtotime('-1 day')),
        'assigned_user_id' => $userBeans[0]->id,
        'parent_type' => 'Opportunities',
        'parent_id' => $opportunityBeans[0]->id,
    ],
    [
        'name' => 'Review contract terms',
        'status' => 'In Progress',
        'priority' => 'Medium',
        'date_due' => date('Y-m-d', strtotime('+3 days')),
        'assigned_user_id' => $userBeans[1]->id,
        'parent_type' => 'Opportunities',
        'parent_id' => $opportunityBeans[1]->id,
    ],
    [
        'name' => 'Follow up on support ticket',
        'status' => 'Not Started',
        'priority' => 'High',
        'date_due' => date('Y-m-d'),
        'assigned_user_id' => $userBeans[0]->id,
        'parent_type' => 'Cases',
        'parent_id' => $caseBeans[0]->id,
    ],
];

foreach ($tasks as $taskData) {
    $task = createBean('Tasks', $taskData);
    echo "  Created task: {$task->name}\n";
}

// Notes
$notes = [
    [
        'name' => 'Meeting notes - Acme requirements',
        'description' => 'Key requirements discussed:\n- Need for scalability\n- Integration with existing systems\n- Training for 50+ users',
        'assigned_user_id' => $userBeans[0]->id,
        'parent_type' => 'Opportunities',
        'parent_id' => $opportunityBeans[0]->id,
    ],
    [
        'name' => 'Support resolution notes',
        'description' => 'Issue resolved by resetting user permissions and clearing cache',
        'assigned_user_id' => $userBeans[0]->id,
        'parent_type' => 'Cases',
        'parent_id' => $caseBeans[3]->id,
    ],
];

foreach ($notes as $noteData) {
    $note = createBean('Notes', $noteData);
    echo "  Created note: {$note->name}\n";
}

echo "\n=== Seeding Complete ===\n";
echo "Successfully created:\n";
echo "- " . count($userBeans) . " users\n";
echo "- " . count($contactBeans) . " contacts\n";
echo "- " . count($leadBeans) . " leads\n";
echo "- " . count($opportunityBeans) . " opportunities\n";
echo "- " . count($caseBeans) . " support tickets\n";
echo "- Multiple activities (calls, meetings, tasks, notes)\n";
echo "\nTest login credentials:\n";
echo "Email: john.doe@example.com\n";
echo "Password: admin123\n";
echo "\nThe system is now ready for testing!\n";