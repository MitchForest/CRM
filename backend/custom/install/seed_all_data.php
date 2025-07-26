<?php
/**
 * Comprehensive Data Seeding Script - Creates all entities with proper relationships
 */

// Direct database connection
$host = 'mysql';
$user = 'root';
$pass = 'root';
$db = 'suitecrm';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "\n=== Comprehensive CRM Data Seeding ===\n";
echo "Creating complete test data with relationships...\n\n";

// Helper to generate GUIDs
function guid() {
    return bin2hex(random_bytes(18));
}

// First, ensure we have the custom tables for leads
$conn->query("CREATE TABLE IF NOT EXISTS leads_cstm (
    id_c CHAR(36) PRIMARY KEY,
    lead_score_c INT,
    lead_score_date_c DATETIME,
    lead_temperature_c VARCHAR(100)
)");

$conn->query("CREATE TABLE IF NOT EXISTS meetings (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255),
    status VARCHAR(100),
    date_start DATETIME,
    date_end DATETIME,
    duration_minutes INT,
    parent_id CHAR(36),
    parent_type VARCHAR(255),
    description TEXT,
    assigned_user_id CHAR(36),
    date_entered DATETIME,
    date_modified DATETIME,
    created_by CHAR(36),
    modified_user_id CHAR(36),
    deleted TINYINT(1) DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS tasks (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255),
    status VARCHAR(100),
    date_due DATE,
    priority VARCHAR(100),
    parent_id CHAR(36),
    parent_type VARCHAR(255),
    assigned_user_id CHAR(36),
    date_entered DATETIME,
    date_modified DATETIME,
    created_by CHAR(36),
    modified_user_id CHAR(36),
    deleted TINYINT(1) DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS email_templates (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255),
    subject VARCHAR(255),
    body_html TEXT,
    body TEXT,
    type VARCHAR(100),
    date_entered DATETIME,
    date_modified DATETIME,
    created_by CHAR(36),
    modified_user_id CHAR(36),
    deleted TINYINT(1) DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS api_rate_limits (
    id CHAR(36) PRIMARY KEY,
    ip_address VARCHAR(45),
    endpoint VARCHAR(255),
    requests INT,
    window_start DATETIME,
    created_at DATETIME
)");

$conn->query("CREATE TABLE IF NOT EXISTS customer_health_scores (
    id CHAR(36) PRIMARY KEY,
    account_id CHAR(36),
    score INT,
    risk_level VARCHAR(100),
    factors TEXT,
    calculated_at DATETIME,
    created_by CHAR(36),
    date_entered DATETIME,
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS activity_tracking_visitors (
    visitor_id CHAR(36) PRIMARY KEY,
    first_visit DATETIME,
    last_visit DATETIME,
    total_visits INT,
    total_page_views INT,
    created_at DATETIME
)");

$conn->query("CREATE TABLE IF NOT EXISTS activity_tracking_sessions (
    session_id CHAR(36) PRIMARY KEY,
    visitor_id CHAR(36),
    start_time DATETIME,
    end_time DATETIME,
    page_count INT,
    created_at DATETIME
)");

$conn->query("CREATE TABLE IF NOT EXISTS activity_tracking_page_views (
    id CHAR(36) PRIMARY KEY,
    visitor_id CHAR(36),
    session_id CHAR(36),
    page_url VARCHAR(255),
    page_title VARCHAR(255),
    time_on_page INT,
    timestamp DATETIME,
    created_at DATETIME
)");

// Clear existing data
echo "Clearing existing data...\n";
$tables = ['users', 'leads', 'leads_cstm', 'contacts', 'opportunities', 'cases', 'calls', 'meetings', 'tasks', 'notes', 
           'email_addresses', 'email_addr_bean_rel', 'opportunities_contacts', 'contacts_cases',
           'ai_chat_conversations', 'ai_chat_messages', 'form_builder_forms', 'form_builder_submissions',
           'aok_knowledge_base_categories', 'aok_knowledgebase', 'aok_knowledgebase_categories', 'kb_articles'];

foreach ($tables as $table) {
    $conn->query("DELETE FROM $table");
}

// 1. Create Users
echo "1. Creating users...\n";
$adminId = guid();
$johnId = guid();
$sarahId = guid();
$mikeId = guid();
$emmaId = guid();

$users = [
    [$adminId, 'admin', 'Admin', 'User', 'admin@example.com', password_hash('admin123', PASSWORD_DEFAULT), 1],
    [$johnId, 'john.doe', 'John', 'Doe', 'john.doe@example.com', password_hash('admin123', PASSWORD_DEFAULT), 0],
    [$sarahId, 'sarah.johnson', 'Sarah', 'Johnson', 'sarah.johnson@example.com', password_hash('admin123', PASSWORD_DEFAULT), 0],
    [$mikeId, 'mike.wilson', 'Mike', 'Wilson', 'mike.wilson@example.com', password_hash('admin123', PASSWORD_DEFAULT), 0],
    [$emmaId, 'emma.davis', 'Emma', 'Davis', 'emma.davis@example.com', password_hash('admin123', PASSWORD_DEFAULT), 0]
];

foreach ($users as $user) {
    $sql = "INSERT INTO users (id, user_name, first_name, last_name, email1, user_hash, is_admin, status, employee_status, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', 'Active', NOW(), NOW(), '1', '1', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", ...$user);
    $stmt->execute();
    echo "  Created user: {$user[1]} ({$user[4]})\n";
}

// 2. Create Contacts (Companies and People)
echo "\n2. Creating contacts...\n";
$contacts = [];
$companies = [
    ['Acme Corporation', 'info@acme.com', '555-0100', '123 Business Ave', 'New York', 'NY', '10001', 'Technology', 5000000, 250],
    ['Global Industries', 'contact@global.com', '555-0200', '456 Corporate Blvd', 'Chicago', 'IL', '60601', 'Manufacturing', 10000000, 500],
    ['Tech Solutions Inc', 'hello@techsolutions.com', '555-0300', '789 Innovation Way', 'San Francisco', 'CA', '94105', 'Software', 3000000, 100],
    ['Retail Chain LLC', 'info@retailchain.com', '555-0400', '321 Commerce St', 'Dallas', 'TX', '75201', 'Retail', 8000000, 1000],
    ['Startup Ventures', 'team@startupventures.io', '555-0500', '555 Venture Rd', 'Austin', 'TX', '78701', 'Technology', 500000, 20]
];

$companyIds = [];
foreach ($companies as $i => $company) {
    $id = guid();
    $companyIds[] = $id;
    $contacts[$id] = ['name' => $company[0], 'type' => 'company'];
    
    $sql = "INSERT INTO contacts (id, first_name, last_name, email1, phone_work, primary_address_street, primary_address_city, primary_address_state, primary_address_postalcode, account_name, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $assignedUser = $i % 2 == 0 ? $sarahId : $mikeId;
    $stmt->bind_param("sssssssssss", $id, $company[0], $company[1], $company[2], $company[3], $company[4], $company[5], $company[6], $company[0], $assignedUser, $adminId, $adminId);
    $stmt->execute();
    echo "  Created company: {$company[0]}\n";
}

// Create people contacts
$people = [
    ['Sarah', 'Thompson', 'CEO', 'sarah.thompson@acme.com', '555-0101', $companyIds[0], 'Acme Corporation'],
    ['Robert', 'Chen', 'CTO', 'robert.chen@acme.com', '555-0102', $companyIds[0], 'Acme Corporation'],
    ['Jennifer', 'Martinez', 'VP Sales', 'jennifer.martinez@global.com', '555-0201', $companyIds[1], 'Global Industries'],
    ['David', 'Anderson', 'Director IT', 'david.anderson@global.com', '555-0202', $companyIds[1], 'Global Industries'],
    ['Lisa', 'Wilson', 'CEO', 'lisa.wilson@techsolutions.com', '555-0301', $companyIds[2], 'Tech Solutions Inc'],
    ['Michael', 'Brown', 'VP Engineering', 'michael.brown@techsolutions.com', '555-0302', $companyIds[2], 'Tech Solutions Inc'],
    ['Emily', 'Davis', 'CFO', 'emily.davis@retailchain.com', '555-0401', $companyIds[3], 'Retail Chain LLC'],
    ['James', 'Taylor', 'COO', 'james.taylor@startupventures.io', '555-0501', $companyIds[4], 'Startup Ventures']
];

$personIds = [];
foreach ($people as $i => $person) {
    $id = guid();
    $personIds[] = $id;
    $contacts[$id] = ['name' => "{$person[0]} {$person[1]}", 'type' => 'person', 'company' => $person[6]];
    
    $sql = "INSERT INTO contacts (id, first_name, last_name, title, email1, phone_work, account_name, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $assignedUser = $i % 3 == 0 ? $sarahId : ($i % 3 == 1 ? $mikeId : $emmaId);
    $stmt->bind_param("ssssssssss", $id, $person[0], $person[1], $person[2], $person[3], $person[4], $person[6], $assignedUser, $adminId, $adminId);
    $stmt->execute();
    echo "  Created contact: {$person[0]} {$person[1]} ({$person[2]} at {$person[6]})\n";
}

// 3. Create Leads with various statuses
echo "\n3. Creating leads...\n";
$leadStatuses = ['New', 'Contacted', 'Qualified'];
$leadSources = ['Website', 'Trade Show', 'Referral', 'Cold Call', 'Email Campaign', 'Social Media'];
$leads = [
    ['David', 'Thompson', 'VP Operations', 'Enterprise Solutions Inc', 'david.thompson@enterprise.com', '555-1001', 'New', 'Website', 'Looking for CRM solution'],
    ['Lisa', 'Anderson', 'Director Sales', 'Retail World', 'lisa.anderson@retailworld.com', '555-1002', 'Contacted', 'Trade Show', 'Met at SaaStr conference'],
    ['Michael', 'Johnson', 'CEO', 'StartupXYZ', 'michael.johnson@startupxyz.io', '555-1003', 'Qualified', 'Referral', 'Referred by Acme Corp'],
    ['Jennifer', 'Williams', 'CFO', 'Finance Plus', 'jennifer.williams@financeplus.com', '555-1004', 'New', 'Email Campaign', 'Downloaded whitepaper'],
    ['Robert', 'Martinez', 'CTO', 'Tech Innovators', 'robert.martinez@techinnovators.com', '555-1005', 'Contacted', 'Social Media', 'LinkedIn outreach'],
    ['Emily', 'Brown', 'VP Marketing', 'Marketing Pros', 'emily.brown@marketingpros.com', '555-1006', 'Qualified', 'Website', 'Demo request form'],
    ['James', 'Davis', 'Director IT', 'Healthcare Corp', 'james.davis@healthcarecorp.com', '555-1007', 'New', 'Cold Call', 'Initial cold call'],
    ['Sarah', 'Wilson', 'COO', 'Logistics Inc', 'sarah.wilson@logistics.com', '555-1008', 'Contacted', 'Referral', 'Partner referral'],
    ['Daniel', 'Taylor', 'President', 'Manufacturing Co', 'daniel.taylor@manufacturing.com', '555-1009', 'Qualified', 'Trade Show', 'Booth visit at conference'],
    ['Amanda', 'Garcia', 'CEO', 'Services Group', 'amanda.garcia@servicesgroup.com', '555-1010', 'New', 'Website', 'Contact form submission']
];

$leadIds = [];
foreach ($leads as $i => $lead) {
    $id = guid();
    $leadIds[] = $id;
    
    // Calculate lead score based on status and other factors
    $score = $lead[6] == 'Qualified' ? rand(80, 95) : ($lead[6] == 'Contacted' ? rand(60, 79) : rand(30, 59));
    
    $sql = "INSERT INTO leads (id, first_name, last_name, title, account_name, email1, phone_work, status, lead_source, description, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $assignedUser = $i % 2 == 0 ? $sarahId : $mikeId;
    $stmt->bind_param("ssssssssssss", $id, $lead[0], $lead[1], $lead[2], $lead[3], $lead[4], $lead[5], $lead[6], $lead[7], $lead[8], $assignedUser, $adminId, $adminId);
    $stmt->execute();
    
    // Add lead custom fields for AI scoring
    $sql = "INSERT INTO leads_cstm (id_c, lead_score_c, lead_score_date_c, lead_temperature_c) VALUES (?, ?, NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $temperature = $score >= 80 ? 'Hot' : ($score >= 60 ? 'Warm' : 'Cold');
    $stmt->bind_param("sis", $id, $score, $temperature);
    $stmt->execute();
    
    echo "  Created lead: {$lead[0]} {$lead[1]} ({$lead[6]}, Score: $score)\n";
}

// 4. Create Opportunities
echo "\n4. Creating opportunities...\n";
$oppStages = ['Qualification', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost'];
$opportunities = [
    ['Acme Corp - Enterprise Upgrade', $companyIds[0], 250000, 'Proposal', 70, '+30 days', 'Upgrading to enterprise plan'],
    ['Global Industries - New Implementation', $companyIds[1], 500000, 'Negotiation', 85, '+15 days', 'Full CRM implementation'],
    ['Tech Solutions - Expansion', $companyIds[2], 150000, 'Qualification', 40, '+45 days', 'Adding 50 new users'],
    ['Retail Chain - System Replace', $companyIds[3], 350000, 'Closed Won', 100, '-5 days', 'Replaced competitor system'],
    ['Startup Ventures - Pilot', $companyIds[4], 50000, 'Closed Lost', 0, '-10 days', 'Went with competitor'],
    ['Enterprise Solutions - New Deal', null, 180000, 'Proposal', 60, '+20 days', 'From qualified lead'],
    ['Healthcare Corp - Implementation', null, 420000, 'Negotiation', 75, '+25 days', 'Large healthcare deployment']
];

$oppIds = [];
foreach ($opportunities as $i => $opp) {
    $id = guid();
    $oppIds[] = $id;
    $closeDate = date('Y-m-d', strtotime($opp[5]));
    
    $sql = "INSERT INTO opportunities (id, name, amount, sales_stage, probability, date_closed, description, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $assignedUser = $i % 2 == 0 ? $sarahId : $mikeId;
    $stmt->bind_param("ssdsisssss", $id, $opp[0], $opp[2], $opp[3], $opp[4], $closeDate, $opp[6], $assignedUser, $adminId, $adminId);
    $stmt->execute();
    
    // Link to contacts if company specified
    if ($opp[1]) {
        $linkId = guid();
        $sql = "INSERT INTO opportunities_contacts (id, contact_id, opportunity_id, date_modified, deleted) 
                VALUES (?, ?, ?, NOW(), 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $linkId, $opp[1], $id);
        $stmt->execute();
    }
    
    echo "  Created opportunity: {$opp[0]} (\${$opp[2]}, {$opp[3]})\n";
}

// 5. Create Support Tickets (Cases)
echo "\n5. Creating support tickets...\n";
$caseStatuses = ['Open', 'In Progress', 'Resolved', 'Closed'];
$caseTypes = ['Technical', 'Billing', 'Feature Request', 'Other'];
$casePriorities = ['Low', 'Medium', 'High'];

$cases = [
    ['Login not working after update', 'Open', 'High', 'Technical', $personIds[0], 'Cannot login after latest update. Error: Invalid credentials'],
    ['Invoice discrepancy', 'In Progress', 'Medium', 'Billing', $personIds[2], 'Charged wrong amount on last invoice', 'Looking into billing records'],
    ['Request for bulk import feature', 'Open', 'Low', 'Feature Request', $personIds[4], 'Need ability to import 1000+ contacts at once'],
    ['System performance issues', 'Resolved', 'High', 'Technical', $personIds[1], 'System very slow during business hours', 'Upgraded server resources'],
    ['Training request', 'Closed', 'Medium', 'Other', $personIds[3], 'Need training for new team members', 'Training completed on ' . date('Y-m-d', strtotime('-2 days'))],
    ['API rate limit too low', 'Open', 'Medium', 'Technical', $personIds[5], 'Getting rate limited with normal usage'],
    ['Export to Excel not working', 'In Progress', 'High', 'Technical', $personIds[6], 'Export button produces corrupted files']
];

$caseIds = [];
foreach ($cases as $i => $case) {
    $id = guid();
    $caseIds[] = $id;
    $caseNumber = sprintf('CASE-%05d', 1000 + $i);
    $resolution = isset($case[6]) ? $case[6] : null;
    
    $sql = "INSERT INTO cases (id, case_number, name, status, priority, type, description, resolution, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $assignedUser = $case[2] == 'High' ? $emmaId : $johnId;
    $stmt->bind_param("ssssssssss", $id, $caseNumber, $case[0], $case[1], $case[2], $case[3], $case[5], $resolution, $assignedUser, $adminId, $adminId);
    $stmt->execute();
    
    // Link to contact
    if ($case[4]) {
        $linkId = guid();
        $sql = "INSERT INTO contacts_cases (id, contact_id, case_id, date_modified, deleted) 
                VALUES (?, ?, ?, NOW(), 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $linkId, $case[4], $id);
        $stmt->execute();
    }
    
    echo "  Created ticket: {$caseNumber} - {$case[0]} ({$case[1]})\n";
}

// 6. Create Activities (Calls, Meetings, Tasks, Notes)
echo "\n6. Creating activities...\n";

// Calls
echo "  Creating calls...\n";
$calls = [
    ['Initial discovery call', 'Held', 'Outbound', 45, '-3 days 10:00', $leadIds[0], 'Leads', 'Discussed requirements and budget'],
    ['Follow-up with decision maker', 'Planned', 'Outbound', 30, '+2 days 14:00', $oppIds[0], 'Opportunities', 'Review proposal details'],
    ['Support call - urgent issue', 'Held', 'Inbound', 25, '-1 day 09:30', $caseIds[0], 'Cases', 'Customer reported critical issue'],
    ['Quarterly business review', 'Held', 'Outbound', 60, '-7 days 15:00', $personIds[0], 'Contacts', 'Reviewed Q4 performance'],
    ['Demo call', 'Planned', 'Outbound', 45, '+1 day 11:00', $leadIds[2], 'Leads', 'Product demonstration scheduled']
];

foreach ($calls as $call) {
    $id = guid();
    $dateStart = date('Y-m-d H:i:s', strtotime($call[4]));
    
    $sql = "INSERT INTO calls (id, name, status, direction, duration_minutes, date_start, parent_id, parent_type, description, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $assignedUser = $call[2] == 'Inbound' ? $emmaId : $sarahId;
    $stmt->bind_param("sssisssssss", $id, $call[0], $call[1], $call[2], $call[3], $dateStart, $call[5], $call[6], $call[7], $assignedUser, $adminId, $adminId);
    $stmt->execute();
}

// Meetings
echo "  Creating meetings...\n";
$meetings = [
    ['Sales kickoff meeting', 'Planned', '+1 week 09:00', 120, $oppIds[1], 'Opportunities', 'In-person meeting at client site'],
    ['Technical requirements review', 'Held', '-2 days 14:00', 90, $oppIds[0], 'Opportunities', 'Reviewed technical specifications'],
    ['Customer success check-in', 'Planned', '+3 days 10:00', 30, $personIds[3], 'Contacts', 'Monthly check-in call'],
    ['Contract negotiation', 'Held', '-1 day 16:00', 60, $oppIds[1], 'Opportunities', 'Final contract terms discussion']
];

foreach ($meetings as $meeting) {
    $id = guid();
    $dateStart = date('Y-m-d H:i:s', strtotime($meeting[2]));
    $dateEnd = date('Y-m-d H:i:s', strtotime($meeting[2]) + ($meeting[3] * 60));
    
    $sql = "INSERT INTO meetings (id, name, status, date_start, date_end, duration_minutes, parent_id, parent_type, description, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssissssss", $id, $meeting[0], $meeting[1], $dateStart, $dateEnd, $meeting[3], $meeting[4], $meeting[5], $meeting[6], $mikeId, $adminId, $adminId);
    $stmt->execute();
}

// Tasks
echo "  Creating tasks...\n";
$tasks = [
    ['Send proposal to Acme Corp', 'Not Started', '+2 days', 'High', $oppIds[0], 'Opportunities'],
    ['Follow up on support ticket', 'In Progress', '+1 day', 'High', $caseIds[0], 'Cases'],
    ['Prepare demo environment', 'Completed', '-1 day', 'Medium', $leadIds[2], 'Leads'],
    ['Update opportunity stage', 'Not Started', 'today', 'Medium', $oppIds[2], 'Opportunities'],
    ['Schedule training session', 'In Progress', '+5 days', 'Low', $personIds[4], 'Contacts']
];

foreach ($tasks as $task) {
    $id = guid();
    $dateDue = date('Y-m-d', strtotime($task[2]));
    
    $sql = "INSERT INTO tasks (id, name, status, date_due, priority, parent_id, parent_type, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $assignedUser = $task[3] == 'High' ? $sarahId : $johnId;
    $stmt->bind_param("ssssssssss", $id, $task[0], $task[1], $dateDue, $task[3], $task[4], $task[5], $assignedUser, $adminId, $adminId);
    $stmt->execute();
}

// Notes
echo "  Creating notes...\n";
$notes = [
    ['Initial contact impressions', 'Very interested in our solution. Budget approved for Q1.', $leadIds[0], 'Leads'],
    ['Competitor analysis', 'Currently using Competitor X, unhappy with support and pricing.', $oppIds[0], 'Opportunities'],
    ['Support resolution steps', '1. Cleared cache\n2. Reset user permissions\n3. Issue resolved', $caseIds[3], 'Cases'],
    ['Meeting notes - Requirements', 'Key requirements:\n- Integration with SAP\n- Mobile app\n- 24/7 support', $oppIds[1], 'Opportunities'],
    ['Customer feedback', 'Very happy with implementation. Considering expansion to other departments.', $personIds[0], 'Contacts']
];

foreach ($notes as $note) {
    $id = guid();
    
    $sql = "INSERT INTO notes (id, name, description, parent_id, parent_type, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $id, $note[0], $note[1], $note[2], $note[3], $johnId, $adminId, $adminId);
    $stmt->execute();
}

// 7. Create Knowledge Base
echo "\n7. Creating knowledge base...\n";
$categories = [
    ['Getting Started', 'Basic guides for new users'],
    ['Features', 'Detailed feature documentation'],
    ['Troubleshooting', 'Common issues and solutions'],
    ['Best Practices', 'Tips for getting the most out of CRM'],
    ['API Documentation', 'Developer resources']
];

$categoryIds = [];
foreach ($categories as $cat) {
    $id = guid();
    $categoryIds[] = $id;
    $sql = "INSERT INTO aok_knowledge_base_categories (id, name, description, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $id, $cat[0], $cat[1], $adminId, $adminId);
    $stmt->execute();
}

$articles = [
    ['Getting Started with CRM', 'Learn the basics of using our CRM system...', $categoryIds[0], 'published', 234],
    ['Lead Management Guide', 'Complete guide to managing leads effectively...', $categoryIds[1], 'published', 189],
    ['Troubleshooting Login Issues', 'Common login problems and how to fix them...', $categoryIds[2], 'published', 567],
    ['Sales Pipeline Best Practices', 'Optimize your sales process with these tips...', $categoryIds[3], 'published', 145],
    ['REST API Reference', 'Complete API documentation for developers...', $categoryIds[4], 'published', 89],
    ['Email Integration Setup', 'How to configure email integration...', $categoryIds[1], 'published', 203],
    ['Data Import Guide', 'Step-by-step guide for importing data...', $categoryIds[0], 'published', 178],
    ['Security Best Practices', 'Keep your CRM data secure...', $categoryIds[3], 'published', 92],
    ['Custom Fields Tutorial', 'How to create and use custom fields...', $categoryIds[1], 'published', 156],
    ['Reporting and Analytics', 'Generate insights from your CRM data...', $categoryIds[1], 'published', 201]
];

foreach ($articles as $i => $article) {
    $id = guid();
    $sql = "INSERT INTO aok_knowledgebase (id, name, description, status, revision, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, '1', NOW(), NOW(), ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $id, $article[0], $article[1], $article[3], $adminId, $adminId);
    $stmt->execute();
    
    // Link to category
    $linkId = guid();
    $sql = "INSERT INTO aok_knowledgebase_categories (id, aok_knowledgebase_id, aok_knowledge_base_categories_id, date_modified, deleted) 
            VALUES (?, ?, ?, NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $linkId, $id, $article[2]);
    $stmt->execute();
    
    // Add to simplified kb_articles table for API
    $slug = strtolower(str_replace(' ', '-', $article[0]));
    $sql = "INSERT INTO kb_articles (id, title, slug, excerpt, category, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $catIndex = array_search($article[2], $categoryIds);
    $categoryName = $categories[$catIndex][0];
    $stmt->bind_param("ssssss", $id, $article[0], $slug, $article[1], $categoryName, $article[3]);
    $stmt->execute();
    
    echo "  Created article: {$article[0]}\n";
}

// 8. Create AI Chat Conversations
echo "\n8. Creating AI chat conversations...\n";
$conversations = [
    [$personIds[7], 'completed', '-2 days 14:30', 25, 'Pricing inquiry'],
    [$leadIds[5], 'completed', '-1 day 10:15', 15, 'Feature questions'],
    [null, 'completed', '-3 hours', 10, 'General inquiry converted to lead']
];

foreach ($conversations as $conv) {
    $convId = guid();
    $startTime = date('Y-m-d H:i:s', strtotime($conv[2]));
    $endTime = date('Y-m-d H:i:s', strtotime($conv[2]) + ($conv[3] * 60));
    
    $sql = "INSERT INTO ai_chat_conversations (id, contact_id, status, started_at, ended_at, created_by, modified_user_id, date_entered, date_modified, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $contactId = $conv[0] ?: null;
    $stmt->bind_param("sssssss", $convId, $contactId, $conv[1], $startTime, $endTime, $adminId, $adminId);
    $stmt->execute();
    
    // Add sample messages
    $messages = [
        ['user', 'Hi, I need help with pricing information'],
        ['assistant', 'Hello! I\'d be happy to help you with our pricing. Could you tell me about your team size and needs?'],
        ['user', 'We have about 50 users and need full CRM features'],
        ['assistant', 'For 50 users with full features, I recommend our Business plan at $49/user/month. Would you like to schedule a demo?'],
        ['user', 'Yes, that would be great'],
        ['assistant', 'Perfect! I can help you schedule a demo. What time works best for you this week?']
    ];
    
    foreach ($messages as $j => $msg) {
        $msgId = guid();
        $msgTime = date('Y-m-d H:i:s', strtotime($conv[2]) + ($j * 120)); // 2 minutes between messages
        $metadata = $msg[0] == 'assistant' ? json_encode(['intent' => 'pricing', 'confidence' => 0.95]) : null;
        
        $sql = "INSERT INTO ai_chat_messages (id, conversation_id, role, content, metadata, created_at, deleted) 
                VALUES (?, ?, ?, ?, ?, ?, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $msgId, $convId, $msg[0], $msg[1], $metadata, $msgTime);
        $stmt->execute();
    }
    
    echo "  Created chat conversation: {$conv[4]}\n";
}

// 9. Create Forms and Submissions
echo "\n9. Creating forms and submissions...\n";
$forms = [
    ['Contact Us', '[{"name":"name","type":"text","label":"Full Name","required":true},{"name":"email","type":"email","label":"Email","required":true},{"name":"company","type":"text","label":"Company"},{"name":"message","type":"textarea","label":"Message","required":true}]', 'active'],
    ['Demo Request', '[{"name":"name","type":"text","label":"Full Name","required":true},{"name":"email","type":"email","label":"Email","required":true},{"name":"company","type":"text","label":"Company","required":true},{"name":"phone","type":"tel","label":"Phone"},{"name":"employees","type":"select","label":"Company Size","options":["1-10","11-50","51-200","201-1000","1000+"]},{"name":"timeline","type":"select","label":"Implementation Timeline","options":["Immediate","1-3 months","3-6 months","6+ months"]}]', 'active'],
    ['Newsletter Signup', '[{"name":"email","type":"email","label":"Email","required":true},{"name":"firstName","type":"text","label":"First Name"},{"name":"lastName","type":"text","label":"Last Name"}]', 'active']
];

$formIds = [];
foreach ($forms as $form) {
    $id = guid();
    $formIds[] = $id;
    $sql = "INSERT INTO form_builder_forms (id, name, fields, status, created_by, modified_user_id, date_entered, date_modified, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $id, $form[0], $form[1], $form[2], $adminId, $adminId);
    $stmt->execute();
    echo "  Created form: {$form[0]}\n";
}

// Create some submissions
$submissions = [
    [$formIds[0], '{"name":"Test User","email":"test.user@example.com","company":"Test Company","message":"Interested in learning more about your CRM"}', '-2 days'],
    [$formIds[1], '{"name":"Demo Request","email":"demo@company.com","company":"Demo Company Inc","phone":"555-9999","employees":"51-200","timeline":"1-3 months"}', '-1 day'],
    [$formIds[0], '{"name":"Another Lead","email":"lead@business.com","company":"Business Corp","message":"Please contact me about pricing"}', '-4 hours']
];

foreach ($submissions as $sub) {
    $id = guid();
    $submittedAt = date('Y-m-d H:i:s', strtotime($sub[2]));
    $sql = "INSERT INTO form_builder_submissions (id, form_id, data, submitted_at, created_by, modified_user_id, date_entered, date_modified, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $id, $sub[0], $sub[1], $submittedAt, $adminId, $adminId);
    $stmt->execute();
}

// 10. Create Email Addresses (for proper email display)
echo "\n10. Linking email addresses...\n";
// This ensures emails show up properly in the UI
$emailsToCreate = array_merge(
    array_map(function($u) { return [$u[0], $u[4], 'Users']; }, $users),
    array_map(function($l) use($leadIds) { 
        $idx = array_search($l, $leads); 
        return [$leadIds[$idx], $l[4], 'Leads']; 
    }, $leads),
    array_map(function($p) use($personIds) { 
        $idx = array_search($p, $people); 
        return [$personIds[$idx], $p[3], 'Contacts']; 
    }, $people)
);

foreach ($emailsToCreate as $emailData) {
    $emailId = guid();
    $sql = "INSERT INTO email_addresses (id, email_address, email_address_caps, date_created, date_modified, deleted) 
            VALUES (?, ?, ?, NOW(), NOW(), 0)
            ON DUPLICATE KEY UPDATE id=id";
    $stmt = $conn->prepare($sql);
    $emailUpper = strtoupper($emailData[1]);
    $stmt->bind_param("sss", $emailId, $emailData[1], $emailUpper);
    $stmt->execute();
    
    // Link to bean
    $linkId = guid();
    $sql = "INSERT INTO email_addr_bean_rel (id, email_address_id, bean_id, bean_module, primary_address, date_created, date_modified, deleted) 
            VALUES (?, ?, ?, ?, 1, NOW(), NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $linkId, $emailId, $emailData[0], $emailData[2]);
    $stmt->execute();
}

echo "\n=== Seeding Complete! ===\n";
echo "\nCreated:\n";
echo "- " . count($users) . " users\n";
echo "- " . count($companies) . " companies\n";
echo "- " . count($people) . " person contacts\n";
echo "- " . count($leads) . " leads with scores\n";
echo "- " . count($opportunities) . " opportunities\n";
echo "- " . count($cases) . " support tickets\n";
echo "- " . count($calls) . " calls\n";
echo "- " . count($meetings) . " meetings\n";
echo "- " . count($tasks) . " tasks\n";
echo "- " . count($notes) . " notes\n";
echo "- " . count($articles) . " knowledge base articles\n";
echo "- " . count($conversations) . " AI chat conversations\n";
echo "- " . count($forms) . " forms with submissions\n";
echo "\nAll entities are properly linked with relationships!\n";
echo "\nLogin credentials:\n";
echo "Username: john.doe\n";
echo "Password: admin123\n";

$conn->close();