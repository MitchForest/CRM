<?php
/**
 * Simplified Phase 5 Data Seeding Script
 */

// Connect directly to database
$host = 'mysql';
$user = 'root';
$pass = 'root';
$db = 'suitecrm';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "\n=== Phase 5 Data Seeding Script (Direct SQL) ===\n";
echo "Creating production-ready test data...\n\n";

// 1. Create admin user
echo "Creating admin user...\n";
$adminId = bin2hex(random_bytes(18));
$passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (id, user_name, user_hash, first_name, last_name, email1, status, is_admin, date_entered, date_modified, created_by, modified_user_id) 
        VALUES ('$adminId', 'admin', '$passwordHash', 'Admin', 'User', 'admin@example.com', 'Active', 1, NOW(), NOW(), '1', '1')";
$conn->query($sql);

// 2. Create test users
echo "Creating test users...\n";
$users = [
    ['john.doe', 'John', 'Doe', 'john.doe@example.com', 'admin123'],
    ['jane.smith', 'Jane', 'Smith', 'jane.smith@example.com', 'user123'],
    ['mike.wilson', 'Mike', 'Wilson', 'mike.wilson@example.com', 'user123'],
];

$userIds = [];
foreach ($users as $userData) {
    $userId = bin2hex(random_bytes(18));
    $userIds[] = $userId;
    $hash = password_hash($userData[4], PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (id, user_name, user_hash, first_name, last_name, email1, status, is_admin, date_entered, date_modified, created_by, modified_user_id) 
            VALUES ('$userId', '{$userData[0]}', '$hash', '{$userData[1]}', '{$userData[2]}', '{$userData[3]}', 'Active', 0, NOW(), NOW(), '$adminId', '$adminId')";
    $conn->query($sql);
    echo "  Created user: {$userData[0]}\n";
}

// 3. Create contacts
echo "\nCreating contacts...\n";
$contacts = [
    ['Acme', 'Corporation', 'info@acme.com', '555-0100', 'Acme Corporation', '123 Business Ave', 'New York', 'NY', '10001'],
    ['Tech', 'Solutions Inc', 'contact@techsolutions.com', '555-0200', 'Tech Solutions Inc', '456 Innovation Blvd', 'San Francisco', 'CA', '94105'],
    ['Sarah', 'Johnson', 'sarah.johnson@acme.com', '555-0101', 'Acme Corporation', 'CEO', 'Executive', null, null],
    ['Robert', 'Chen', 'robert.chen@techsolutions.com', '555-0201', 'Tech Solutions Inc', 'CTO', 'Technology', null, null],
    ['Emily', 'Davis', 'emily.davis@gmail.com', '555-0301', null, 'Marketing Manager', null, null, null],
];

$contactIds = [];
foreach ($contacts as $contactData) {
    $contactId = bin2hex(random_bytes(18));
    $contactIds[] = $contactId;
    
    $sql = "INSERT INTO contacts (id, first_name, last_name, email1, phone_work, account_name, title, department, primary_address_street, primary_address_city, primary_address_state, primary_address_postalcode, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES ('$contactId', '{$contactData[0]}', '{$contactData[1]}', '{$contactData[2]}', '{$contactData[3]}', " . 
            ($contactData[4] ? "'{$contactData[4]}'" : "NULL") . ", " .
            ($contactData[5] ? "'{$contactData[5]}'" : "NULL") . ", " .
            ($contactData[6] ? "'{$contactData[6]}'" : "NULL") . ", " .
            ($contactData[7] ? "'{$contactData[7]}'" : "NULL") . ", " .
            ($contactData[8] ? "'{$contactData[8]}'" : "NULL") . ", " .
            (isset($contactData[9]) ? "'{$contactData[9]}'" : "NULL") . ", " .
            (isset($contactData[10]) ? "'{$contactData[10]}'" : "NULL") . ", 
            '{$userIds[0]}', NOW(), NOW(), '$adminId', '$adminId', 0)";
    
    $conn->query($sql);
    
    // Add email address
    $emailId = bin2hex(random_bytes(18));
    $conn->query("INSERT INTO email_addresses (id, email_address, email_address_caps, date_created, date_modified, deleted) 
                  VALUES ('$emailId', '{$contactData[2]}', '" . strtoupper($contactData[2]) . "', NOW(), NOW(), 0)");
    $conn->query("INSERT INTO email_addr_bean_rel (id, email_address_id, bean_id, bean_module, primary_address, date_created, date_modified, deleted) 
                  VALUES ('" . bin2hex(random_bytes(18)) . "', '$emailId', '$contactId', 'Contacts', 1, NOW(), NOW(), 0)");
    
    echo "  Created contact: {$contactData[0]} {$contactData[1]}\n";
}

// 4. Create leads
echo "\nCreating leads...\n";
$leads = [
    ['David', 'Thompson', 'VP Sales', 'Global Industries', 'david.thompson@global.com', '555-0400', 'New', 'Website'],
    ['Lisa', 'Anderson', 'Director of Operations', 'Retail Chain LLC', 'lisa.anderson@retailchain.com', '555-0500', 'Contacted', 'Trade Show'],
    ['Michael', 'Brown', null, 'Startup Ventures', 'michael.brown@startupventures.io', '555-0600', 'Qualified', 'Referral'],
];

foreach ($leads as $leadData) {
    $leadId = bin2hex(random_bytes(18));
    
    $sql = "INSERT INTO leads (id, first_name, last_name, title, account_name, email1, phone_work, status, lead_source, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES ('$leadId', '{$leadData[0]}', '{$leadData[1]}', " .
            ($leadData[2] ? "'{$leadData[2]}'" : "NULL") . ", '{$leadData[3]}', '{$leadData[4]}', '{$leadData[5]}', '{$leadData[6]}', '{$leadData[7]}', 
            '{$userIds[array_rand($userIds)]}', NOW(), NOW(), '$adminId', '$adminId', 0)";
    
    $conn->query($sql);
    
    // Add email address
    $emailId = bin2hex(random_bytes(18));
    $conn->query("INSERT INTO email_addresses (id, email_address, email_address_caps, date_created, date_modified, deleted) 
                  VALUES ('$emailId', '{$leadData[4]}', '" . strtoupper($leadData[4]) . "', NOW(), NOW(), 0)");
    $conn->query("INSERT INTO email_addr_bean_rel (id, email_address_id, bean_id, bean_module, primary_address, date_created, date_modified, deleted) 
                  VALUES ('" . bin2hex(random_bytes(18)) . "', '$emailId', '$leadId', 'Leads', 1, NOW(), NOW(), 0)");
    
    echo "  Created lead: {$leadData[0]} {$leadData[1]} ({$leadData[6]})\n";
}

// 5. Create opportunities
echo "\nCreating opportunities...\n";
$opportunities = [
    ['Acme Corp Enterprise Deal', 250000, 'Proposal', 60, '+30 days', 'New Business'],
    ['Tech Solutions Integration', 150000, 'Negotiation', 80, '+15 days', 'Existing Business'],
    ['Global Industries Pilot', 50000, 'Qualified', 30, '+45 days', 'New Business'],
    ['Retail Chain Implementation', 180000, 'Won', 100, '-5 days', 'New Business'],
    ['Startup Ventures Basic Package', 25000, 'Lost', 0, '-10 days', 'New Business'],
];

$oppIds = [];
foreach ($opportunities as $oppData) {
    $oppId = bin2hex(random_bytes(18));
    $oppIds[] = $oppId;
    $closeDate = date('Y-m-d', strtotime($oppData[4]));
    
    $sql = "INSERT INTO opportunities (id, name, amount, sales_stage, probability, date_closed, opportunity_type, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES ('$oppId', '{$oppData[0]}', {$oppData[1]}, '{$oppData[2]}', {$oppData[3]}, '$closeDate', '{$oppData[5]}', 
            '{$userIds[array_rand($userIds)]}', NOW(), NOW(), '$adminId', '$adminId', 0)";
    
    $conn->query($sql);
    
    // Link to contacts
    if ($oppData[2] === 'Won' || $oppData[2] === 'Lost') {
        $conn->query("INSERT INTO opportunities_contacts (id, contact_id, opportunity_id, date_modified, deleted) 
                      VALUES ('" . bin2hex(random_bytes(18)) . "', '{$contactIds[0]}', '$oppId', NOW(), 0)");
    }
    
    echo "  Created opportunity: {$oppData[0]} ({$oppData[2]})\n";
}

// 6. Create cases (support tickets)
echo "\nCreating support tickets...\n";
$cases = [
    ['Login issues with new system', 'Open', 'High', 'Technical', 'Customer cannot login after password reset'],
    ['Feature request: Export functionality', 'In Progress', 'Medium', 'Enhancement', 'Customer requesting CSV export feature'],
    ['Billing discrepancy', 'Resolved', 'High', 'Billing', 'Customer was charged incorrect amount', 'Refund processed and billing corrected'],
    ['Performance issues', 'Closed', 'Low', 'Technical', 'System running slowly during peak hours', 'Upgraded server resources'],
];

foreach ($cases as $i => $caseData) {
    $caseId = bin2hex(random_bytes(18));
    $caseNumber = sprintf('CASE-%03d', $i + 1);
    
    $resolution = isset($caseData[5]) ? "'{$caseData[5]}'" : "NULL";
    
    $sql = "INSERT INTO cases (id, case_number, name, status, priority, type, description, resolution, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES ('$caseId', '$caseNumber', '{$caseData[0]}', '{$caseData[1]}', '{$caseData[2]}', '{$caseData[3]}', '{$caseData[4]}', $resolution, 
            '{$userIds[array_rand($userIds)]}', NOW(), NOW(), '$adminId', '$adminId', 0)";
    
    $conn->query($sql);
    
    // Link to contacts
    $conn->query("INSERT INTO contacts_cases (id, contact_id, case_id, date_modified, deleted) 
                  VALUES ('" . bin2hex(random_bytes(18)) . "', '{$contactIds[array_rand($contactIds)]}', '$caseId', NOW(), 0)");
    
    echo "  Created case: {$caseData[0]} ({$caseData[1]})\n";
}

// 7. Create activities
echo "\nCreating activities...\n";

// Calls
$calls = [
    ['Initial discovery call with Acme', 'Held', 'Outbound', 45, '-2 days'],
    ['Follow-up call with Tech Solutions', 'Planned', 'Outbound', 30, '+2 days'],
];

foreach ($calls as $callData) {
    $callId = bin2hex(random_bytes(18));
    $dateStart = date('Y-m-d H:i:s', strtotime($callData[4]));
    
    $sql = "INSERT INTO calls (id, name, status, direction, duration_minutes, date_start, assigned_user_id, parent_type, parent_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES ('$callId', '{$callData[0]}', '{$callData[1]}', '{$callData[2]}', {$callData[3]}, '$dateStart', 
            '{$userIds[0]}', 'Opportunities', '{$oppIds[0]}', NOW(), NOW(), '$adminId', '$adminId', 0)";
    
    $conn->query($sql);
    echo "  Created call: {$callData[0]}\n";
}

// Notes
$notes = [
    ['Meeting notes - Acme requirements', 'Key requirements discussed:\\n- Need for scalability\\n- Integration with existing systems\\n- Training for 50+ users'],
    ['Support resolution notes', 'Issue resolved by resetting user permissions and clearing cache'],
];

foreach ($notes as $i => $noteData) {
    $noteId = bin2hex(random_bytes(18));
    $parentType = $i === 0 ? 'Opportunities' : 'Cases';
    $parentId = $i === 0 ? $oppIds[0] : $caseId;
    
    $sql = "INSERT INTO notes (id, name, description, assigned_user_id, parent_type, parent_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES ('$noteId', '{$noteData[0]}', '{$noteData[1]}', '{$userIds[0]}', '$parentType', '$parentId', 
            NOW(), NOW(), '$adminId', '$adminId', 0)";
    
    $conn->query($sql);
    echo "  Created note: {$noteData[0]}\n";
}

// 8. Create some AI chat conversations
echo "\nCreating AI chat conversations...\n";
$convId = bin2hex(random_bytes(18));
$sql = "INSERT INTO ai_chat_conversations (id, contact_id, status, started_at, ended_at, created_by, modified_user_id, date_entered, date_modified, deleted) 
        VALUES ('$convId', '{$contactIds[4]}', 'completed', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY + INTERVAL 30 MINUTE, 
        '$adminId', '$adminId', NOW(), NOW(), 0)";
$conn->query($sql);

// Add messages
$messages = [
    ['user', 'Hi, I need help with your product pricing'],
    ['assistant', 'Hello! I\'d be happy to help you with our pricing. Could you tell me more about your specific needs?'],
    ['user', 'We need a solution for about 50 users'],
    ['assistant', 'For 50 users, I recommend our Enterprise plan. Let me connect you with our sales team for a detailed quote.'],
];

foreach ($messages as $i => $msgData) {
    $msgId = bin2hex(random_bytes(18));
    $sql = "INSERT INTO ai_chat_messages (id, conversation_id, role, content, metadata, created_at, deleted) 
            VALUES ('$msgId', '$convId', '{$msgData[0]}', '{$msgData[1]}', " .
            ($msgData[0] === 'assistant' ? "'{\"sentiment\": \"positive\", \"confidence\": 0.95}'" : "NULL") . ", 
            NOW() - INTERVAL 1 DAY + INTERVAL " . ($i * 5) . " MINUTE, 0)";
    $conn->query($sql);
}
echo "  Created AI chat conversation with " . count($messages) . " messages\n";

// 9. Create form submissions
echo "\nCreating form submissions...\n";
$formId = bin2hex(random_bytes(18));
$sql = "INSERT INTO form_builder_forms (id, name, fields, status, created_by, modified_user_id, date_entered, date_modified, deleted) 
        VALUES ('$formId', 'Contact Us Form', '[{\"name\":\"name\",\"type\":\"text\",\"required\":true},{\"name\":\"email\",\"type\":\"email\",\"required\":true},{\"name\":\"message\",\"type\":\"textarea\",\"required\":true}]', 
        'active', '$adminId', '$adminId', NOW(), NOW(), 0)";
$conn->query($sql);

$submissionId = bin2hex(random_bytes(18));
$sql = "INSERT INTO form_builder_submissions (id, form_id, data, submitted_at, created_by, modified_user_id, date_entered, date_modified, deleted) 
        VALUES ('$submissionId', '$formId', '{\"name\":\"Test User\",\"email\":\"test@example.com\",\"message\":\"I would like more information about your services.\"}', 
        NOW() - INTERVAL 3 HOUR, '$adminId', '$adminId', NOW(), NOW(), 0)";
$conn->query($sql);
echo "  Created form submission\n";

// 10. Create knowledge base articles
echo "\nCreating knowledge base articles...\n";
$categories = ['Getting Started', 'Troubleshooting', 'Best Practices'];
$catIds = [];

foreach ($categories as $catName) {
    $catId = bin2hex(random_bytes(18));
    $catIds[] = $catId;
    $sql = "INSERT INTO aok_knowledge_base_categories (id, name, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES ('$catId', '$catName', NOW(), NOW(), '$adminId', '$adminId', 0)";
    $conn->query($sql);
}

$articles = [
    ['How to Get Started with CRM', 'A comprehensive guide to getting started...', 'published', $catIds[0]],
    ['Troubleshooting Login Issues', 'Common login problems and solutions...', 'published', $catIds[1]],
    ['Best Practices for Lead Management', 'Tips for effective lead management...', 'published', $catIds[2]],
];

foreach ($articles as $articleData) {
    $articleId = bin2hex(random_bytes(18));
    $sql = "INSERT INTO aok_knowledgebase (id, name, status, revision, description, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES ('$articleId', '{$articleData[0]}', '{$articleData[2]}', '1', '{$articleData[1]}', NOW(), NOW(), '$adminId', '$adminId', 0)";
    $conn->query($sql);
    
    // Link to category
    $sql = "INSERT INTO aok_knowledgebase_categories (id, aok_knowledgebase_id, aok_knowledge_base_categories_id, date_modified, deleted) 
            VALUES ('" . bin2hex(random_bytes(18)) . "', '$articleId', '{$articleData[3]}', NOW(), 0)";
    $conn->query($sql);
}
echo "  Created " . count($articles) . " knowledge base articles in " . count($categories) . " categories\n";

echo "\n=== Seeding Complete ===\n";
echo "Successfully created:\n";
echo "- " . (count($users) + 1) . " users (including admin)\n";
echo "- " . count($contacts) . " contacts\n";
echo "- " . count($leads) . " leads\n";
echo "- " . count($opportunities) . " opportunities\n";
echo "- " . count($cases) . " support tickets\n";
echo "- Multiple activities and interactions\n";
echo "- AI chat conversations\n";
echo "- Form submissions\n";
echo "- Knowledge base articles\n";
echo "\nTest login credentials:\n";
echo "Email: john.doe@example.com\n";
echo "Password: admin123\n";
echo "\nThe system is now ready for testing!\n";

$conn->close();