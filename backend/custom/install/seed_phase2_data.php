<?php
/**
 * Phase 2 - Seed Demo Data
 * Creates sample opportunities, cases, and activities for testing
 */

// Bootstrap SuiteCRM
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/Opportunities/Opportunity.php');
require_once('modules/Cases/Case.php');
require_once('modules/Calls/Call.php');
require_once('modules/Meetings/Meeting.php');
require_once('modules/Tasks/Task.php');

function seedPhase2Data() {
    global $db;
    
    // Get admin user
    $adminQuery = "SELECT id FROM users WHERE user_name = 'admin' AND deleted = 0 LIMIT 1";
    $adminResult = $db->query($adminQuery);
    $adminRow = $db->fetchByAssoc($adminResult);
    $adminId = $adminRow['id'] ?? '1';
    
    // Get some accounts
    $accountsQuery = "SELECT id, name FROM accounts WHERE deleted = 0 LIMIT 5";
    $accountsResult = $db->query($accountsQuery);
    $accounts = [];
    while ($row = $db->fetchByAssoc($accountsResult)) {
        $accounts[] = $row;
    }
    
    if (empty($accounts)) {
        echo "No accounts found. Please create some accounts first.\n";
        return;
    }
    
    echo "Seeding Phase 2 data...\n\n";
    
    // Seed Opportunities
    $opportunities = [
        [
            'name' => 'Enterprise License Deal - ' . $accounts[0]['name'],
            'account_id' => $accounts[0]['id'],
            'sales_stage' => 'Proposal',
            'amount' => 150000,
            'probability' => 75,
            'date_closed' => date('Y-m-d', strtotime('+30 days')),
            'lead_source' => 'Website',
            'next_step' => 'Send revised proposal with enterprise features',
            'description' => 'Large enterprise deployment for 500+ users',
            'competitors' => 'Salesforce, HubSpot',
            'subscription_type' => 'annual',
        ],
        [
            'name' => 'Mid-Market Expansion - ' . $accounts[1]['name'] ?? 'Company B',
            'account_id' => $accounts[1]['id'] ?? null,
            'sales_stage' => 'Negotiation',
            'amount' => 75000,
            'probability' => 90,
            'date_closed' => date('Y-m-d', strtotime('+14 days')),
            'lead_source' => 'Referral',
            'next_step' => 'Final contract review with legal',
            'description' => 'Expanding from 50 to 150 users',
            'subscription_type' => 'annual',
        ],
        [
            'name' => 'Startup Package - ' . $accounts[2]['name'] ?? 'Company C',
            'account_id' => $accounts[2]['id'] ?? null,
            'sales_stage' => 'Qualification',
            'amount' => 12000,
            'probability' => 10,
            'date_closed' => date('Y-m-d', strtotime('+45 days')),
            'lead_source' => 'Marketing',
            'next_step' => 'Schedule discovery call',
            'description' => 'Early stage startup evaluating CRM options',
            'competitors' => 'Pipedrive, Copper',
            'subscription_type' => 'monthly',
        ],
    ];
    
    $createdOpportunities = [];
    foreach ($opportunities as $data) {
        $opportunity = BeanFactory::newBean('Opportunities');
        foreach ($data as $field => $value) {
            $opportunity->$field = $value;
        }
        $opportunity->assigned_user_id = $adminId;
        $opportunity->save();
        $createdOpportunities[] = $opportunity;
        echo "Created opportunity: {$opportunity->name}\n";
    }
    
    // Seed Cases
    $cases = [
        [
            'name' => 'Login issues after update',
            'status' => 'In Progress',
            'priority' => 'P1',
            'type' => 'technical_support',
            'account_id' => $accounts[0]['id'],
            'description' => 'Users unable to login after latest update. Getting 500 error.',
            'severity' => 'critical',
            'product_version' => '2.5.1',
            'environment' => 'production',
        ],
        [
            'name' => 'Feature Request - Bulk Import',
            'status' => 'New',
            'priority' => 'P3',
            'type' => 'feature_request',
            'account_id' => $accounts[1]['id'] ?? null,
            'description' => 'Customer requesting ability to bulk import opportunities',
            'severity' => 'minor',
            'product_version' => '2.5.0',
            'environment' => 'production',
        ],
        [
            'name' => 'API Rate Limiting Issue',
            'status' => 'Assigned',
            'priority' => 'P2',
            'type' => 'bug',
            'account_id' => $accounts[2]['id'] ?? null,
            'description' => 'API calls being rate limited incorrectly',
            'severity' => 'major',
            'product_version' => '2.5.1',
            'environment' => 'production',
        ],
    ];
    
    foreach ($cases as $data) {
        $case = BeanFactory::newBean('Cases');
        foreach ($data as $field => $value) {
            $case->$field = $value;
        }
        $case->assigned_user_id = $adminId;
        $case->save();
        echo "Created case: {$case->name}\n";
    }
    
    // Seed Activities
    // Calls
    $calls = [
        [
            'name' => 'Discovery Call - ' . $accounts[0]['name'],
            'status' => 'Planned',
            'direction' => 'Outbound',
            'date_start' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'duration_hours' => 0,
            'duration_minutes' => 30,
            'parent_type' => 'Opportunities',
            'parent_id' => $createdOpportunities[0]->id,
            'call_type' => 'discovery',
            'description' => 'Initial discovery call to understand requirements',
        ],
        [
            'name' => 'Follow-up Call - Contract Questions',
            'status' => 'Held',
            'direction' => 'Inbound',
            'date_start' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'duration_hours' => 0,
            'duration_minutes' => 15,
            'parent_type' => 'Opportunities',
            'parent_id' => $createdOpportunities[1]->id ?? null,
            'call_type' => 'follow_up',
            'call_outcome' => 'successful',
            'description' => 'Addressed contract questions, moving to legal review',
        ],
    ];
    
    foreach ($calls as $data) {
        $call = BeanFactory::newBean('Calls');
        foreach ($data as $field => $value) {
            $call->$field = $value;
        }
        $call->assigned_user_id = $adminId;
        $call->save();
        echo "Created call: {$call->name}\n";
    }
    
    // Meetings
    $meetings = [
        [
            'name' => 'Product Demo - Enterprise Features',
            'status' => 'Planned',
            'date_start' => date('Y-m-d 14:00:00', strtotime('+3 days')),
            'date_end' => date('Y-m-d 15:00:00', strtotime('+3 days')),
            'duration_hours' => 1,
            'duration_minutes' => 0,
            'location' => 'Zoom',
            'parent_type' => 'Opportunities',
            'parent_id' => $createdOpportunities[0]->id,
            'meeting_type' => 'demo',
            'description' => 'Demo enterprise features including AI capabilities',
            'demo_environment' => 'https://demo.ourcrm.com/enterprise',
        ],
        [
            'name' => 'Quarterly Business Review',
            'status' => 'Planned',
            'date_start' => date('Y-m-d 10:00:00', strtotime('+7 days')),
            'date_end' => date('Y-m-d 11:30:00', strtotime('+7 days')),
            'duration_hours' => 1,
            'duration_minutes' => 30,
            'location' => 'Customer Office',
            'parent_type' => 'Accounts',
            'parent_id' => $accounts[0]['id'],
            'meeting_type' => 'qbr',
            'description' => 'Q4 review and planning for next year',
            'attendee_count' => 8,
        ],
    ];
    
    foreach ($meetings as $data) {
        $meeting = BeanFactory::newBean('Meetings');
        foreach ($data as $field => $value) {
            $meeting->$field = $value;
        }
        $meeting->assigned_user_id = $adminId;
        $meeting->save();
        echo "Created meeting: {$meeting->name}\n";
    }
    
    // Tasks
    $tasks = [
        [
            'name' => 'Send Proposal to ' . $accounts[0]['name'],
            'status' => 'In Progress',
            'priority' => 'High',
            'date_due' => date('Y-m-d', strtotime('+1 day')),
            'parent_type' => 'Opportunities',
            'parent_id' => $createdOpportunities[0]->id,
            'task_type' => 'proposal',
            'description' => 'Customize enterprise proposal template with pricing',
        ],
        [
            'name' => 'Follow up on Support Ticket',
            'status' => 'Not Started',
            'priority' => 'Medium',
            'date_due' => date('Y-m-d', strtotime('+2 days')),
            'parent_type' => 'Cases',
            'parent_id' => $cases[0]['id'] ?? null,
            'task_type' => 'follow_up',
            'description' => 'Check if login issues have been resolved',
        ],
        [
            'name' => 'Research Competitor Pricing',
            'status' => 'Not Started',
            'priority' => 'Low',
            'date_due' => date('Y-m-d', strtotime('+5 days')),
            'task_type' => 'research',
            'description' => 'Update competitive analysis document',
        ],
    ];
    
    foreach ($tasks as $data) {
        $task = BeanFactory::newBean('Tasks');
        foreach ($data as $field => $value) {
            $task->$field = $value;
        }
        $task->assigned_user_id = $adminId;
        $task->save();
        echo "Created task: {$task->name}\n";
    }
    
    echo "\nPhase 2 demo data seeded successfully!\n";
    echo "Created:\n";
    echo "- " . count($opportunities) . " opportunities\n";
    echo "- " . count($cases) . " cases\n";
    echo "- " . count($calls) . " calls\n";
    echo "- " . count($meetings) . " meetings\n";
    echo "- " . count($tasks) . " tasks\n";
}

// Run the seeding
try {
    seedPhase2Data();
} catch (Exception $e) {
    echo "Error seeding data: " . $e->getMessage() . "\n";
    exit(1);
}