<?php
/**
 * Complete Database Seeding for Final CRM
 */

$host = 'mysql';
$user = 'root';
$pass = 'root';
$db = 'suitecrm';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "\n=== Seeding Complete CRM Data ===\n";

// Helper function
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

// Clean existing data
echo "Cleaning existing data...\n";
$tables = ['ai_chat_messages', 'ai_chat_conversations', 'form_builder_submissions', 'notes', 'calls', 'opportunities_contacts', 
           'opportunities', 'leads', 'contacts_cases', 'cases', 'contacts', 'email_addr_bean_rel', 'email_addresses',
           'aok_knowledgebase_categories', 'aok_knowledgebase', 'aok_knowledge_base_categories', 'form_builder_forms'];
foreach ($tables as $table) {
    $conn->query("DELETE FROM $table WHERE id != '1'");
}

// 1. Create Contacts
echo "\nCreating contacts...\n";
$contacts = [
    ['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john.smith@techcorp.com', 'phone' => '555-0101', 'account' => 'TechCorp Solutions', 'title' => 'CTO'],
    ['first_name' => 'Sarah', 'last_name' => 'Johnson', 'email' => 'sarah.johnson@innovate.io', 'phone' => '555-0102', 'account' => 'Innovate.io', 'title' => 'VP Sales'],
    ['first_name' => 'Michael', 'last_name' => 'Davis', 'email' => 'michael.davis@globaltech.com', 'phone' => '555-0103', 'account' => 'GlobalTech Inc', 'title' => 'Director of IT'],
    ['first_name' => 'Emily', 'last_name' => 'Wilson', 'email' => 'emily.wilson@startup.co', 'phone' => '555-0104', 'account' => 'Startup Co', 'title' => 'CEO'],
    ['first_name' => 'Robert', 'last_name' => 'Brown', 'email' => 'robert.brown@enterprise.com', 'phone' => '555-0105', 'account' => 'Enterprise Solutions', 'title' => 'Purchase Manager'],
    ['first_name' => 'Lisa', 'last_name' => 'Martinez', 'email' => 'lisa.martinez@techcorp.com', 'phone' => '555-0106', 'account' => 'TechCorp Solutions', 'title' => 'Project Manager'],
    ['first_name' => 'James', 'last_name' => 'Anderson', 'email' => 'james.anderson@cloudnine.com', 'phone' => '555-0107', 'account' => 'CloudNine Systems', 'title' => 'Engineering Lead'],
    ['first_name' => 'Jennifer', 'last_name' => 'Taylor', 'email' => 'jennifer.taylor@dataflow.io', 'phone' => '555-0108', 'account' => 'DataFlow Inc', 'title' => 'Data Analyst'],
];

$contactIds = [];
foreach ($contacts as $contact) {
    $contactId = generateUUID();
    $contactIds[] = $contactId;
    
    // Create email address
    $emailId = generateUUID();
    $sql = "INSERT INTO email_addresses (id, email_address, email_address_caps, date_created, date_modified, deleted) 
            VALUES (?, ?, ?, NOW(), NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $emailCaps = strtoupper($contact['email']);
    $stmt->bind_param("sss", $emailId, $contact['email'], $emailCaps);
    $stmt->execute();
    
    // Create contact
    $sql = "INSERT INTO contacts (id, first_name, last_name, email1, phone_work, account_name, title, 
            primary_address_street, primary_address_city, primary_address_state, primary_address_postalcode,
            assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, '123 Main St', 'San Francisco', 'CA', '94105', 
            '1', NOW(), NOW(), '1', '1', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $contactId, $contact['first_name'], $contact['last_name'], 
                      $contact['email'], $contact['phone'], $contact['account'], $contact['title']);
    $stmt->execute();
    
    // Link email to contact
    $sql = "INSERT INTO email_addr_bean_rel (id, email_address_id, bean_id, bean_module, primary_address, date_created, date_modified, deleted) 
            VALUES (?, ?, ?, 'Contacts', 1, NOW(), NOW(), 0)";
    $linkId = generateUUID();
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $linkId, $emailId, $contactId);
    $stmt->execute();
    
    echo "  Created: {$contact['first_name']} {$contact['last_name']} ({$contact['account']})\n";
}

// 2. Create Leads
echo "\nCreating leads...\n";
$leads = [
    ['first_name' => 'Emma', 'last_name' => 'Thompson', 'email' => 'emma.thompson@newcorp.com', 'phone' => '555-0201', 'company' => 'NewCorp Ltd', 'title' => 'VP Sales', 'status' => 'New', 'source' => 'Website'],
    ['first_name' => 'William', 'last_name' => 'Johnson', 'email' => 'william.johnson@bigco.com', 'phone' => '555-0202', 'company' => 'BigCo Industries', 'title' => 'Director of IT', 'status' => 'Assigned', 'source' => 'Trade Show'],
    ['first_name' => 'Olivia', 'last_name' => 'Garcia', 'email' => 'olivia.garcia@fastgrow.com', 'phone' => '555-0203', 'company' => 'FastGrow Inc', 'title' => 'CTO', 'status' => 'In Process', 'source' => 'Referral'],
    ['first_name' => 'Alexander', 'last_name' => 'Lee', 'email' => 'alex.lee@techstart.io', 'phone' => '555-0204', 'company' => 'TechStart.io', 'title' => 'CEO', 'status' => 'New', 'source' => 'Cold Call'],
    ['first_name' => 'Sophia', 'last_name' => 'Chen', 'email' => 'sophia.chen@cloudnine.com', 'phone' => '555-0205', 'company' => 'CloudNine Systems', 'title' => 'Purchase Manager', 'status' => 'Assigned', 'source' => 'Website'],
    ['first_name' => 'Daniel', 'last_name' => 'Rodriguez', 'email' => 'daniel.rodriguez@innovate.com', 'phone' => '555-0206', 'company' => 'Innovate Corp', 'title' => 'VP Engineering', 'status' => 'New', 'source' => 'LinkedIn'],
    ['first_name' => 'Isabella', 'last_name' => 'White', 'email' => 'isabella.white@startup.io', 'phone' => '555-0207', 'company' => 'StartupHub.io', 'title' => 'Operations Director', 'status' => 'In Process', 'source' => 'Partner'],
    ['first_name' => 'Matthew', 'last_name' => 'Harris', 'email' => 'matthew.harris@enterprise.net', 'phone' => '555-0208', 'company' => 'Enterprise Networks', 'title' => 'IT Manager', 'status' => 'Assigned', 'source' => 'Email'],
    ['first_name' => 'Ava', 'last_name' => 'Martin', 'email' => 'ava.martin@globalcorp.com', 'phone' => '555-0209', 'company' => 'GlobalCorp', 'title' => 'CFO', 'status' => 'New', 'source' => 'Conference'],
    ['first_name' => 'Ethan', 'last_name' => 'Thompson', 'email' => 'ethan.thompson@techsol.com', 'phone' => '555-0210', 'company' => 'Tech Solutions Inc', 'title' => 'Head of Sales', 'status' => 'In Process', 'source' => 'Website'],
];

$leadIds = [];
foreach ($leads as $lead) {
    $leadId = generateUUID();
    $leadIds[] = $leadId;
    
    $sql = "INSERT INTO leads (id, first_name, last_name, email1, phone_work, account_name, title, status, lead_source,
            assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '1', NOW(), NOW(), '1', '1', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $leadId, $lead['first_name'], $lead['last_name'], $lead['email'], 
                      $lead['phone'], $lead['company'], $lead['title'], $lead['status'], $lead['source']);
    $stmt->execute();
    echo "  Created: {$lead['first_name']} {$lead['last_name']} ({$lead['company']}) - {$lead['status']}\n";
}

// 3. Create Opportunities  
echo "\nCreating opportunities...\n";
$opportunities = [
    ['name' => 'TechCorp CRM Implementation', 'account' => 'TechCorp Solutions', 'amount' => 75000, 'stage' => 'Proposal/Price Quote', 'probability' => 60],
    ['name' => 'Innovate.io Enterprise Upgrade', 'account' => 'Innovate.io', 'amount' => 125000, 'stage' => 'Negotiation/Review', 'probability' => 80],
    ['name' => 'GlobalTech Cloud Migration', 'account' => 'GlobalTech Inc', 'amount' => 200000, 'stage' => 'Qualification', 'probability' => 30],
    ['name' => 'Startup Co Initial Setup', 'account' => 'Startup Co', 'amount' => 25000, 'stage' => 'Closed Won', 'probability' => 100],
    ['name' => 'Enterprise Solutions Expansion', 'account' => 'Enterprise Solutions', 'amount' => 150000, 'stage' => 'Value Proposition', 'probability' => 40],
    ['name' => 'CloudNine Systems Integration', 'account' => 'CloudNine Systems', 'amount' => 95000, 'stage' => 'Needs Analysis', 'probability' => 50],
    ['name' => 'DataFlow Analytics Platform', 'account' => 'DataFlow Inc', 'amount' => 180000, 'stage' => 'Proposal/Price Quote', 'probability' => 65],
];

$opportunityIds = [];
foreach ($opportunities as $opp) {
    $oppId = generateUUID();
    $opportunityIds[] = $oppId;
    
    $closeDate = date('Y-m-d', strtotime('+' . rand(30, 180) . ' days'));
    $sql = "INSERT INTO opportunities (id, name, amount, sales_stage, probability, date_closed, 
            assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, '1', NOW(), NOW(), '1', '1', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdiss", $oppId, $opp['name'], $opp['amount'], 
                      $opp['stage'], $opp['probability'], $closeDate);
    $stmt->execute();
    
    // Link to contacts
    if (rand(0, 1)) {
        $linkId = generateUUID();
        $contactId = $contactIds[array_rand($contactIds)];
        $sql = "INSERT INTO opportunities_contacts (id, opportunity_id, contact_id, date_modified, deleted) 
                VALUES (?, ?, ?, NOW(), 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $linkId, $oppId, $contactId);
        $stmt->execute();
    }
    
    echo "  Created: {$opp['name']} - \${$opp['amount']} ({$opp['stage']})\n";
}

// 4. Create Cases
echo "\nCreating cases...\n";
$cases = [
    ['name' => 'Login Issues - Password Reset', 'account' => 'TechCorp Solutions', 'priority' => 'High', 'status' => 'Open_New', 'type' => 'User', 'description' => 'User unable to login after password reset'],
    ['name' => 'API Integration Error', 'account' => 'Innovate.io', 'priority' => 'Medium', 'status' => 'Open_Assigned', 'type' => 'Product', 'description' => 'REST API returning 500 errors on bulk updates'],
    ['name' => 'Feature Request - Bulk Import', 'account' => 'GlobalTech Inc', 'priority' => 'Low', 'status' => 'Open_Pending_Input', 'type' => 'Feature', 'description' => 'Customer wants ability to import 10k+ records'],
    ['name' => 'Performance Issue - Dashboard', 'account' => 'Enterprise Solutions', 'priority' => 'High', 'status' => 'Open_In_Progress', 'type' => 'Product', 'description' => 'Dashboard takes 30+ seconds to load'],
    ['name' => 'Billing Question', 'account' => 'Startup Co', 'priority' => 'Medium', 'status' => 'Closed_Resolved', 'type' => 'Admin', 'description' => 'Question about annual vs monthly billing'],
    ['name' => 'Data Export Request', 'account' => 'CloudNine Systems', 'priority' => 'Medium', 'status' => 'Open_Assigned', 'type' => 'User', 'description' => 'Need full data export for compliance'],
    ['name' => 'Mobile App Crash', 'account' => 'DataFlow Inc', 'priority' => 'High', 'status' => 'Open_In_Progress', 'type' => 'Product', 'description' => 'iOS app crashes on startup for some users'],
];

$caseIds = [];
foreach ($cases as $case) {
    $caseId = generateUUID();
    $caseIds[] = $caseId;
    $caseNumber = rand(1000, 9999);
    
    $sql = "INSERT INTO cases (id, name, case_number, priority, status, type, description,
            assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, ?, '1', NOW(), NOW(), '1', '1', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $caseId, $case['name'], $caseNumber, 
                      $case['priority'], $case['status'], $case['type'], $case['description']);
    $stmt->execute();
    
    // Link to contacts
    $linkId = generateUUID();
    $contactId = $contactIds[array_rand($contactIds)];
    $sql = "INSERT INTO contacts_cases (id, contact_id, case_id, date_modified, deleted) 
            VALUES (?, ?, ?, NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $linkId, $contactId, $caseId);
    $stmt->execute();
    
    echo "  Created: Case #{$caseNumber} - {$case['name']} ({$case['priority']})\n";
}

// 5. Create Activities
echo "\nCreating activities...\n";

// Calls
echo "  Creating calls...\n";
$calls = [
    ['name' => 'Initial Discovery Call', 'duration' => 30, 'status' => 'Held', 'description' => 'Discussed requirements and timeline'],
    ['name' => 'Follow-up Call - Budget Discussion', 'duration' => 45, 'status' => 'Held', 'description' => 'Reviewed pricing options'],
    ['name' => 'Technical Requirements Call', 'duration' => 60, 'status' => 'Held', 'description' => 'Deep dive into technical needs'],
    ['name' => 'Demo Preparation Call', 'duration' => 15, 'status' => 'Planned', 'description' => 'Prep for upcoming demo'],
    ['name' => 'Contract Negotiation Call', 'duration' => 90, 'status' => 'Planned', 'description' => 'Final terms discussion'],
];

foreach ($calls as $call) {
    $callId = generateUUID();
    $parentType = rand(0, 1) ? 'Leads' : 'Opportunities';
    $parentId = $parentType === 'Leads' ? $leadIds[array_rand($leadIds)] : $opportunityIds[array_rand($opportunityIds)];
    
    $sql = "INSERT INTO calls (id, name, duration_minutes, status, parent_type, parent_id, 
            date_start, assigned_user_id, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), '1', NOW(), NOW(), '1', '1', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisss", $callId, $call['name'], $call['duration'], $call['status'], $parentType, $parentId);
    $stmt->execute();
}
echo "    Created " . count($calls) . " calls\n";

// Notes
echo "  Creating notes...\n";
$notes = [
    ['name' => 'Meeting Notes - Requirements', 'description' => 'Client needs: \n1. User management\n2. API integration\n3. Custom reporting\n4. Mobile access'],
    ['name' => 'Technical Specifications', 'description' => 'System requirements:\n- 100 concurrent users\n- 99.9% uptime SLA\n- Daily backups\n- SSO integration'],
    ['name' => 'Competitor Analysis', 'description' => 'Currently using Salesforce\nPain points: Cost, complexity\nLooking for simpler solution'],
    ['name' => 'Decision Criteria', 'description' => 'Key factors:\n1. Price (40%)\n2. Ease of use (30%)\n3. Features (20%)\n4. Support (10%)'],
    ['name' => 'Follow-up Action Items', 'description' => '- Send proposal by Friday\n- Schedule technical demo\n- Get security questionnaire\n- Provide references'],
];

foreach ($notes as $note) {
    $noteId = generateUUID();
    $parentType = ['Leads', 'Contacts', 'Opportunities', 'Cases'][array_rand(['Leads', 'Contacts', 'Opportunities', 'Cases'])];
    
    if ($parentType === 'Leads') $parentId = $leadIds[array_rand($leadIds)];
    elseif ($parentType === 'Contacts') $parentId = $contactIds[array_rand($contactIds)];
    elseif ($parentType === 'Opportunities') $parentId = $opportunityIds[array_rand($opportunityIds)];
    else $parentId = $caseIds[array_rand($caseIds)];
    
    $sql = "INSERT INTO notes (id, name, description, parent_type, parent_id, assigned_user_id, 
            date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, '1', NOW(), NOW(), '1', '1', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $noteId, $note['name'], $note['description'], $parentType, $parentId);
    $stmt->execute();
}
echo "    Created " . count($notes) . " notes\n";

// 6. Create Knowledge Base
echo "\nCreating knowledge base...\n";

// Categories
$categories = [
    ['name' => 'Getting Started'],
    ['name' => 'Features & Functionality'],
    ['name' => 'Troubleshooting'],
    ['name' => 'API Documentation'],
    ['name' => 'Best Practices'],
];

$categoryIds = [];
foreach ($categories as $category) {
    $categoryId = generateUUID();
    $categoryIds[$category['name']] = $categoryId;
    
    $sql = "INSERT INTO aok_knowledge_base_categories (id, name, date_entered, date_modified, 
            created_by, modified_user_id, deleted) 
            VALUES (?, ?, NOW(), NOW(), '1', '1', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $categoryId, $category['name']);
    $stmt->execute();
}

// Articles
$articles = [
    ['name' => 'Quick Start Guide', 'category' => 'Getting Started', 'content' => 'Welcome to our CRM! Follow these steps to get started:\n\n1. Set up your profile\n2. Import your contacts\n3. Create your first lead\n4. Track activities\n5. Generate reports'],
    ['name' => 'User Management Guide', 'category' => 'Getting Started', 'content' => 'Managing users in the CRM:\n\n- Creating new users\n- Setting permissions\n- Role-based access control\n- Password policies\n- User deactivation'],
    ['name' => 'Lead Management', 'category' => 'Features & Functionality', 'content' => 'Effective lead management:\n\n- Lead capture forms\n- Lead scoring\n- Assignment rules\n- Lead conversion\n- Follow-up automation'],
    ['name' => 'Sales Pipeline', 'category' => 'Features & Functionality', 'content' => 'Managing your sales pipeline:\n\n- Pipeline stages\n- Opportunity management\n- Forecasting\n- Win/loss analysis\n- Pipeline reports'],
    ['name' => 'Common Login Issues', 'category' => 'Troubleshooting', 'content' => 'Troubleshooting login problems:\n\n1. Clear browser cache\n2. Check caps lock\n3. Reset password\n4. Contact admin for account unlock\n5. Check system status'],
    ['name' => 'Performance Optimization', 'category' => 'Troubleshooting', 'content' => 'Improving CRM performance:\n\n- Browser recommendations\n- Network requirements\n- Data cleanup\n- Index optimization\n- Caching settings'],
    ['name' => 'REST API Overview', 'category' => 'API Documentation', 'content' => 'Using our REST API:\n\n- Authentication\n- Endpoints\n- Rate limits\n- Response formats\n- Error handling'],
    ['name' => 'Webhook Configuration', 'category' => 'API Documentation', 'content' => 'Setting up webhooks:\n\n- Event types\n- Endpoint configuration\n- Security\n- Retry logic\n- Testing webhooks'],
    ['name' => 'Data Security Best Practices', 'category' => 'Best Practices', 'content' => 'Keeping your data secure:\n\n- Strong passwords\n- Two-factor authentication\n- Regular backups\n- Access control\n- Audit logs'],
    ['name' => 'Email Integration Tips', 'category' => 'Best Practices', 'content' => 'Email integration best practices:\n\n- Email sync setup\n- Template management\n- Tracking settings\n- Bounce handling\n- Compliance (CAN-SPAM)'],
];

foreach ($articles as $article) {
    $articleId = generateUUID();
    
    $sql = "INSERT INTO aok_knowledgebase (id, name, status, revision, description, 
            date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, 'published', '1', ?, NOW(), NOW(), '1', '1', 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $articleId, $article['name'], $article['content']);
    $stmt->execute();
    
    // Link to category
    $linkId = generateUUID();
    $sql = "INSERT INTO aok_knowledgebase_categories (id, aok_knowledgebase_id, aok_knowledge_base_categories_id, 
            date_modified, deleted) 
            VALUES (?, ?, ?, NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $linkId, $articleId, $categoryIds[$article['category']]);
    $stmt->execute();
}
echo "  Created " . count($categories) . " categories and " . count($articles) . " articles\n";

// 7. Create Forms
echo "\nCreating forms...\n";
$forms = [
    [
        'name' => 'Contact Us',
        'fields' => json_encode([
            ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
            ['name' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ['name' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => false],
            ['name' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true]
        ])
    ],
    [
        'name' => 'Demo Request',
        'fields' => json_encode([
            ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
            ['name' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'label' => 'Work Email', 'required' => true],
            ['name' => 'phone', 'type' => 'tel', 'label' => 'Phone Number', 'required' => true],
            ['name' => 'company', 'type' => 'text', 'label' => 'Company Name', 'required' => true],
            ['name' => 'company_size', 'type' => 'select', 'label' => 'Company Size', 
             'options' => ['1-10', '11-50', '51-200', '201-500', '500+'], 'required' => true]
        ])
    ],
    [
        'name' => 'Newsletter Signup',
        'fields' => json_encode([
            ['name' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true],
            ['name' => 'interests', 'type' => 'checkbox', 'label' => 'Interests', 
             'options' => ['Product Updates', 'Tips & Tricks', 'Industry News'], 'required' => false]
        ])
    ]
];

$formIds = [];
foreach ($forms as $form) {
    $formId = generateUUID();
    $formIds[] = $formId;
    
    $sql = "INSERT INTO form_builder_forms (id, name, fields, status, created_by, modified_user_id, 
            date_entered, date_modified, deleted) 
            VALUES (?, ?, ?, 'active', '1', '1', NOW(), NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $formId, $form['name'], $form['fields']);
    $stmt->execute();
    echo "  Created form: {$form['name']}\n";
}

// Create form submissions
echo "\nCreating form submissions...\n";
$submissions = [
    [
        'form_id' => $formIds[0], // Contact Us
        'data' => json_encode([
            'first_name' => 'Jessica',
            'last_name' => 'Parker',
            'email' => 'jessica.parker@example.com',
            'company' => 'Parker Industries',
            'message' => 'Interested in learning more about your CRM solution for our sales team.'
        ])
    ],
    [
        'form_id' => $formIds[1], // Demo Request
        'data' => json_encode([
            'first_name' => 'David',
            'last_name' => 'Kim',
            'email' => 'david.kim@techcorp.com',
            'phone' => '555-0999',
            'company' => 'TechCorp Industries',
            'company_size' => '201-500'
        ])
    ],
    [
        'form_id' => $formIds[2], // Newsletter
        'data' => json_encode([
            'email' => 'subscriber@email.com',
            'interests' => ['Product Updates', 'Tips & Tricks']
        ])
    ]
];

foreach ($submissions as $submission) {
    $submissionId = generateUUID();
    
    $sql = "INSERT INTO form_builder_submissions (id, form_id, data, submitted_at, 
            created_by, modified_user_id, date_entered, date_modified, deleted) 
            VALUES (?, ?, ?, NOW(), '1', '1', NOW(), NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $submissionId, $submission['form_id'], $submission['data']);
    $stmt->execute();
}
echo "  Created " . count($submissions) . " form submissions\n";

// 8. Create AI Chat Conversations
echo "\nCreating AI chat conversations...\n";
$conversations = [
    [
        'contact_id' => $contactIds[0],
        'messages' => [
            ['role' => 'user', 'content' => 'Hi, I need help with pricing for your CRM'],
            ['role' => 'assistant', 'content' => 'Hello! I\'d be happy to help you with our pricing. We have three main plans: Starter at $29/user/month, Professional at $59/user/month, and Enterprise at $99/user/month. How many users do you need?'],
            ['role' => 'user', 'content' => 'We have about 25 sales reps'],
            ['role' => 'assistant', 'content' => 'For 25 users, I recommend our Professional plan which includes advanced features like AI lead scoring, custom workflows, and priority support. This would be $1,475/month. Would you like to schedule a demo to see these features in action?'],
            ['role' => 'user', 'content' => 'Yes, that would be great'],
        ]
    ],
    [
        'contact_id' => $contactIds[1],
        'messages' => [
            ['role' => 'user', 'content' => 'Our dashboard is loading very slowly'],
            ['role' => 'assistant', 'content' => 'I\'m sorry to hear you\'re experiencing slow dashboard loading. Let me help troubleshoot this issue. Can you tell me approximately how long it takes to load and how many records you have in the system?'],
            ['role' => 'user', 'content' => 'It takes about 30 seconds, we have around 50,000 contacts'],
            ['role' => 'assistant', 'content' => 'With 50,000 contacts, some slowness can occur. I\'ll create a support ticket for our team to optimize your dashboard performance. In the meantime, try using filters to reduce the initial data load. Ticket #4521 has been created.'],
        ]
    ],
    [
        'contact_id' => $contactIds[2],
        'messages' => [
            ['role' => 'user', 'content' => 'Can your CRM integrate with Slack?'],
            ['role' => 'assistant', 'content' => 'Yes! Our CRM has a native Slack integration. You can receive notifications for new leads, deal updates, and support tickets directly in Slack. You can also update records using Slack commands.'],
            ['role' => 'user', 'content' => 'How do I set it up?'],
            ['role' => 'assistant', 'content' => 'To set up Slack integration: 1) Go to Settings > Integrations, 2) Click on Slack, 3) Click "Connect to Slack", 4) Authorize the app, 5) Choose which notifications to enable. The whole process takes about 5 minutes. Need help with any specific step?'],
        ]
    ]
];

foreach ($conversations as $conv) {
    $convId = generateUUID();
    
    // Create conversation
    $sql = "INSERT INTO ai_chat_conversations (id, contact_id, status, started_at, ended_at, 
            created_by, modified_user_id, date_entered, date_modified, deleted) 
            VALUES (?, ?, 'completed', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY + INTERVAL 10 MINUTE, 
            '1', '1', NOW(), NOW(), 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $convId, $conv['contact_id']);
    $stmt->execute();
    
    // Create messages
    $timestamp = time() - 86400; // Start yesterday
    foreach ($conv['messages'] as $message) {
        $messageId = generateUUID();
        $metadata = null;
        
        if ($message['role'] === 'assistant') {
            $metadata = json_encode([
                'sentiment' => 'positive',
                'intent' => 'support',
                'confidence' => 0.95
            ]);
        }
        
        $sql = "INSERT INTO ai_chat_messages (id, conversation_id, role, content, metadata, created_at, deleted) 
                VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $messageId, $convId, $message['role'], $message['content'], $metadata, $timestamp);
        $stmt->execute();
        
        $timestamp += 60; // 1 minute between messages
    }
}
echo "  Created " . count($conversations) . " AI chat conversations\n";

// Summary
echo "\n=== Seeding Complete ===\n";
echo "Successfully created:\n";
echo "- " . count($contacts) . " contacts with email addresses\n";
echo "- " . count($leads) . " leads\n";
echo "- " . count($opportunities) . " opportunities\n";
echo "- " . count($cases) . " support cases\n";
echo "- " . count($calls) . " calls\n";
echo "- " . count($notes) . " notes\n";
echo "- " . count($articles) . " knowledge base articles in " . count($categories) . " categories\n";
echo "- " . count($forms) . " forms with " . count($submissions) . " submissions\n";
echo "- " . count($conversations) . " AI chat conversations\n";
echo "\nDatabase is fully populated with test data!\n";

$conn->close();