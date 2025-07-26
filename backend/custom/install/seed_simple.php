<?php
/**
 * Simple Phase 5 Data Seeding Script
 * Creates test data for all Phase 5 features
 */

// Connect to database
$host = 'mysql';
$user = 'root';
$pass = 'root';
$db = 'suitecrm';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "\n=== Phase 5 Simple Data Seeding ===\n";

// Helper function to generate UUID
function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// 1. Create Users
echo "Creating users...\n";
$users = [
    ['username' => 'admin', 'first_name' => 'Admin', 'last_name' => 'User', 'email' => 'admin@example.com'],
    ['username' => 'john.doe', 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john.doe@example.com'],
    ['username' => 'sarah.johnson', 'first_name' => 'Sarah', 'last_name' => 'Johnson', 'email' => 'sarah.johnson@example.com'],
];

$userIds = [];
foreach ($users as $userData) {
    // Check if user exists
    $sql = "SELECT id FROM users WHERE user_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userData['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $userIds[$userData['username']] = $row['id'];
        echo "  User {$userData['username']} already exists\n";
    } else {
        $userId = generateUUID();
        $userIds[$userData['username']] = $userId;
        
        $sql = "INSERT INTO users (id, user_name, first_name, last_name, email1, status, employee_status, is_admin, date_entered, date_modified, created_by, modified_user_id, deleted, user_hash) 
                VALUES (?, ?, ?, ?, ?, 'Active', 'Active', 0, NOW(), NOW(), '1', '1', 0, MD5(?))";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $userId, $userData['username'], $userData['first_name'], $userData['last_name'], $userData['email'], $userData['username']);
        $stmt->execute();
        echo "  Created user: {$userData['username']}\n";
    }
}

// 2. Create Contacts
echo "\nCreating contacts...\n";
$contacts = [
    ['first_name' => 'Michael', 'last_name' => 'Smith', 'email' => 'michael.smith@techcorp.com', 'phone' => '555-0101', 'account' => 'TechCorp Solutions'],
    ['first_name' => 'Jennifer', 'last_name' => 'Davis', 'email' => 'jennifer.davis@innovate.io', 'phone' => '555-0102', 'account' => 'Innovate.io'],
    ['first_name' => 'Robert', 'last_name' => 'Wilson', 'email' => 'robert.wilson@globaltech.com', 'phone' => '555-0103', 'account' => 'GlobalTech Inc'],
    ['first_name' => 'Lisa', 'last_name' => 'Brown', 'email' => 'lisa.brown@startup.co', 'phone' => '555-0104', 'account' => 'Startup Co'],
    ['first_name' => 'David', 'last_name' => 'Martinez', 'email' => 'david.martinez@enterprise.com', 'phone' => '555-0105', 'account' => 'Enterprise Solutions'],
];

$contactIds = [];
foreach ($contacts as $contact) {
    $contactId = generateUUID();
    $contactIds[] = $contactId;
    
    $sql = "INSERT INTO contacts (id, first_name, last_name, email1, phone_work, account_name, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), '1', '1', 0)";
    
    $assignedUser = $userIds[array_rand($userIds)];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $contactId, $contact['first_name'], $contact['last_name'], $contact['email'], $contact['phone'], $contact['account'], $assignedUser);
    $stmt->execute();
    echo "  Created contact: {$contact['first_name']} {$contact['last_name']}\n";
}

// 3. Create Leads
echo "\nCreating leads...\n";
$leads = [
    ['first_name' => 'Emma', 'last_name' => 'Thompson', 'email' => 'emma.thompson@newcorp.com', 'phone' => '555-0201', 'company' => 'NewCorp Ltd', 'title' => 'VP Sales', 'status' => 'New'],
    ['first_name' => 'James', 'last_name' => 'Anderson', 'email' => 'james.anderson@bigco.com', 'phone' => '555-0202', 'company' => 'BigCo Industries', 'title' => 'Director of IT', 'status' => 'Assigned'],
    ['first_name' => 'Olivia', 'last_name' => 'Taylor', 'email' => 'olivia.taylor@fastgrow.com', 'phone' => '555-0203', 'company' => 'FastGrow Inc', 'title' => 'CTO', 'status' => 'In Process'],
    ['first_name' => 'William', 'last_name' => 'Jones', 'email' => 'william.jones@techstart.io', 'phone' => '555-0204', 'company' => 'TechStart.io', 'title' => 'CEO', 'status' => 'New'],
    ['first_name' => 'Sophia', 'last_name' => 'Garcia', 'email' => 'sophia.garcia@cloudnine.com', 'phone' => '555-0205', 'company' => 'CloudNine Systems', 'title' => 'Purchase Manager', 'status' => 'Assigned'],
];

$leadIds = [];
foreach ($leads as $lead) {
    $leadId = generateUUID();
    $leadIds[] = $leadId;
    
    $sql = "INSERT INTO leads (id, first_name, last_name, email1, phone_work, account_name, title, status, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), '1', '1', 0)";
    
    $assignedUser = $userIds[array_rand($userIds)];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $leadId, $lead['first_name'], $lead['last_name'], $lead['email'], $lead['phone'], $lead['company'], $lead['title'], $lead['status'], $assignedUser);
    $stmt->execute();
    echo "  Created lead: {$lead['first_name']} {$lead['last_name']} ({$lead['company']})\n";
}

// 4. Create Opportunities
echo "\nCreating opportunities...\n";
$opportunities = [
    ['name' => 'TechCorp CRM Implementation', 'account' => 'TechCorp Solutions', 'amount' => 75000, 'stage' => 'Proposal/Price Quote', 'probability' => 60],
    ['name' => 'Innovate.io Enterprise Upgrade', 'account' => 'Innovate.io', 'amount' => 125000, 'stage' => 'Negotiation/Review', 'probability' => 80],
    ['name' => 'GlobalTech Cloud Migration', 'account' => 'GlobalTech Inc', 'amount' => 200000, 'stage' => 'Qualification', 'probability' => 30],
    ['name' => 'Startup Co Initial Setup', 'account' => 'Startup Co', 'amount' => 25000, 'stage' => 'Closed Won', 'probability' => 100],
    ['name' => 'Enterprise Solutions Expansion', 'account' => 'Enterprise Solutions', 'amount' => 150000, 'stage' => 'Value Proposition', 'probability' => 40],
];

$opportunityIds = [];
foreach ($opportunities as $opp) {
    $oppId = generateUUID();
    $opportunityIds[] = $oppId;
    
    $closeDate = date('Y-m-d', strtotime('+' . rand(30, 180) . ' days'));
    $sql = "INSERT INTO opportunities (id, name, account_name, amount, sales_stage, probability, date_closed, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), '1', '1', 0)";
    
    $assignedUser = $userIds[array_rand($userIds)];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssddsss", $oppId, $opp['name'], $opp['account'], $opp['amount'], $opp['stage'], $opp['probability'], $closeDate, $assignedUser);
    $stmt->execute();
    echo "  Created opportunity: {$opp['name']} (\${$opp['amount']})\n";
}

// 5. Create Cases
echo "\nCreating cases...\n";
$cases = [
    ['name' => 'Login Issues - Password Reset', 'account' => 'TechCorp Solutions', 'priority' => 'High', 'status' => 'Open_New', 'type' => 'User'],
    ['name' => 'API Integration Error', 'account' => 'Innovate.io', 'priority' => 'Medium', 'status' => 'Open_Assigned', 'type' => 'Product'],
    ['name' => 'Feature Request - Bulk Import', 'account' => 'GlobalTech Inc', 'priority' => 'Low', 'status' => 'Open_Pending_Input', 'type' => 'Feature'],
    ['name' => 'Performance Issue - Slow Loading', 'account' => 'Enterprise Solutions', 'priority' => 'High', 'status' => 'Open_In_Progress', 'type' => 'Product'],
    ['name' => 'Billing Question', 'account' => 'Startup Co', 'priority' => 'Medium', 'status' => 'Closed_Resolved', 'type' => 'Admin'],
];

foreach ($cases as $case) {
    $caseId = generateUUID();
    $caseNumber = rand(1000, 9999);
    
    $sql = "INSERT INTO cases (id, name, case_number, account_name, priority, status, type, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), '1', '1', 0)";
    
    $assignedUser = $userIds[array_rand($userIds)];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $caseId, $case['name'], $caseNumber, $case['account'], $case['priority'], $case['status'], $case['type'], $assignedUser);
    $stmt->execute();
    echo "  Created case: {$case['name']} (#{$caseNumber})\n";
}

// 6. Create Activities (Calls, Meetings, Tasks)
echo "\nCreating activities...\n";

// Calls
$calls = [
    ['name' => 'Initial Discovery Call', 'duration' => 30, 'status' => 'Held'],
    ['name' => 'Follow-up Call', 'duration' => 15, 'status' => 'Held'],
    ['name' => 'Demo Call Scheduled', 'duration' => 60, 'status' => 'Planned'],
];

foreach ($calls as $call) {
    $callId = generateUUID();
    $parentId = $leadIds[array_rand($leadIds)];
    
    $sql = "INSERT INTO calls (id, name, duration_minutes, status, parent_type, parent_id, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, 'Leads', ?, ?, NOW(), NOW(), '1', '1', 0)";
    
    $assignedUser = $userIds[array_rand($userIds)];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisss", $callId, $call['name'], $call['duration'], $call['status'], $parentId, $assignedUser);
    $stmt->execute();
}
echo "  Created " . count($calls) . " calls\n";

// Meetings
$meetings = [
    ['name' => 'Product Demo', 'duration' => 60, 'status' => 'Planned'],
    ['name' => 'Requirements Gathering', 'duration' => 90, 'status' => 'Held'],
    ['name' => 'Contract Review', 'duration' => 45, 'status' => 'Planned'],
];

foreach ($meetings as $meeting) {
    $meetingId = generateUUID();
    $parentId = $opportunityIds[array_rand($opportunityIds)];
    
    $sql = "INSERT INTO meetings (id, name, duration_minutes, status, parent_type, parent_id, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, 'Opportunities', ?, ?, NOW(), NOW(), '1', '1', 0)";
    
    $assignedUser = $userIds[array_rand($userIds)];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisss", $meetingId, $meeting['name'], $meeting['duration'], $meeting['status'], $parentId, $assignedUser);
    $stmt->execute();
}
echo "  Created " . count($meetings) . " meetings\n";

// Tasks
$tasks = [
    ['name' => 'Send follow-up email', 'status' => 'Completed', 'priority' => 'High'],
    ['name' => 'Prepare proposal', 'status' => 'In Progress', 'priority' => 'High'],
    ['name' => 'Schedule demo', 'status' => 'Not Started', 'priority' => 'Medium'],
    ['name' => 'Review contract terms', 'status' => 'In Progress', 'priority' => 'High'],
];

foreach ($tasks as $task) {
    $taskId = generateUUID();
    $parentId = $contactIds[array_rand($contactIds)];
    
    $sql = "INSERT INTO tasks (id, name, status, priority, parent_type, parent_id, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, 'Contacts', ?, ?, NOW(), NOW(), '1', '1', 0)";
    
    $assignedUser = $userIds[array_rand($userIds)];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $taskId, $task['name'], $task['status'], $task['priority'], $parentId, $assignedUser);
    $stmt->execute();
}
echo "  Created " . count($tasks) . " tasks\n";

// 7. Create Knowledge Base Articles
echo "\nCreating knowledge base articles...\n";
$articles = [
    ['title' => 'Getting Started Guide', 'content' => 'Welcome to our CRM! This guide helps you get started...', 'status' => 'published'],
    ['title' => 'API Documentation', 'content' => 'Our API allows you to integrate with third-party systems...', 'status' => 'published'],
    ['title' => 'Troubleshooting Login Issues', 'content' => 'If you cannot log in, try these steps...', 'status' => 'published'],
    ['title' => 'Import/Export Guide', 'content' => 'Learn how to import and export your data...', 'status' => 'published'],
    ['title' => 'Security Best Practices', 'content' => 'Keep your account secure with these tips...', 'status' => 'published'],
];

foreach ($articles as $article) {
    $articleId = generateUUID();
    
    $sql = "INSERT INTO aok_knowledgebase (id, name, status, revision, description, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, '1', ?, NOW(), NOW(), '1', '1', 0)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $articleId, $article['title'], $article['status'], $article['content']);
    $stmt->execute();
}
echo "  Created " . count($articles) . " knowledge base articles\n";

// 8. Create Forms
echo "\nCreating forms...\n";
$forms = [
    ['name' => 'Contact Us Form', 'fields' => json_encode(['name', 'email', 'message'])],
    ['name' => 'Demo Request Form', 'fields' => json_encode(['name', 'email', 'company', 'phone'])],
    ['name' => 'Newsletter Signup', 'fields' => json_encode(['email'])],
];

foreach ($forms as $form) {
    $formId = generateUUID();
    
    $sql = "INSERT INTO form_builder_forms (id, name, fields, status, created_by, modified_user_id, date_entered, date_modified, deleted) 
            VALUES (?, ?, ?, 'active', '1', '1', NOW(), NOW(), 0)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $formId, $form['name'], $form['fields']);
    $stmt->execute();
}
echo "  Created " . count($forms) . " forms\n";

// Summary
echo "\n=== Seeding Complete ===\n";
echo "Successfully created:\n";
echo "- " . count($users) . " users\n";
echo "- " . count($contacts) . " contacts\n";
echo "- " . count($leads) . " leads\n";
echo "- " . count($opportunities) . " opportunities\n";
echo "- " . count($cases) . " cases\n";
echo "- " . (count($calls) + count($meetings) + count($tasks)) . " activities\n";
echo "- " . count($articles) . " knowledge base articles\n";
echo "- " . count($forms) . " forms\n";
echo "\nThe CRM is now populated with test data!\n";

$conn->close();