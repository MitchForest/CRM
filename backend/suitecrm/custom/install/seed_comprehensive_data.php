<?php
/**
 * Comprehensive Seed Data Script for Phase 5
 * Creates realistic test data for ALL features
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

echo "\n=== Comprehensive Phase 5 Data Seeding ===\n";
echo "Creating production-ready test data for all features...\n\n";

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

// 1. Create Forms for Lead Capture
echo "Creating form builder forms...\n";

$forms = [
    [
        'name' => 'Contact Us Form',
        'description' => 'Main contact form for website',
        'fields' => json_encode([
            ['name' => 'firstName', 'type' => 'text', 'label' => 'First Name', 'required' => true],
            ['name' => 'lastName', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
            ['name' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'required' => false],
            ['name' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => false],
            ['name' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true]
        ]),
        'is_active' => 1
    ],
    [
        'name' => 'Newsletter Signup',
        'description' => 'Quick newsletter subscription form',
        'fields' => json_encode([
            ['name' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true],
            ['name' => 'firstName', 'type' => 'text', 'label' => 'First Name', 'required' => false]
        ]),
        'is_active' => 1
    ],
    [
        'name' => 'Demo Request Form',
        'description' => 'Request a product demo',
        'fields' => json_encode([
            ['name' => 'firstName', 'type' => 'text', 'label' => 'First Name', 'required' => true],
            ['name' => 'lastName', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'label' => 'Work Email', 'required' => true],
            ['name' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'required' => true],
            ['name' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => true],
            ['name' => 'title', 'type' => 'text', 'label' => 'Job Title', 'required' => false],
            ['name' => 'employees', 'type' => 'select', 'label' => 'Company Size', 'options' => ['1-10', '11-50', '51-200', '201-500', '500+'], 'required' => false],
            ['name' => 'timeframe', 'type' => 'select', 'label' => 'Implementation Timeframe', 'options' => ['Immediate', '1-3 months', '3-6 months', '6+ months'], 'required' => false]
        ]),
        'is_active' => 1
    ],
    [
        'name' => 'Whitepaper Download',
        'description' => 'Gate content with lead capture',
        'fields' => json_encode([
            ['name' => 'firstName', 'type' => 'text', 'label' => 'First Name', 'required' => true],
            ['name' => 'lastName', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'label' => 'Business Email', 'required' => true],
            ['name' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => true],
            ['name' => 'industry', 'type' => 'select', 'label' => 'Industry', 'options' => ['Technology', 'Healthcare', 'Finance', 'Manufacturing', 'Retail', 'Other'], 'required' => false]
        ]),
        'is_active' => 1
    ]
];

$formIds = [];
foreach ($forms as $formData) {
    $formId = generateUUID();
    $formIds[] = $formId;
    
    $sql = "INSERT INTO form_builder_forms (id, name, description, fields, is_active, submissions_count, created_by, modified_user_id, date_entered, date_modified, deleted) 
            VALUES (?, ?, ?, ?, ?, 0, '1', '1', NOW(), NOW(), 0)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "Error preparing statement: " . $conn->error . "\n";
        continue;
    }
    $stmt->bind_param("ssssi", $formId, $formData['name'], $formData['description'], $formData['fields'], $formData['is_active']);
    $stmt->execute();
    
    echo "  Created form: {$formData['name']}\n";
}

// 2. Create Form Submissions
echo "\nCreating form submissions...\n";

$submissions = [
    [
        'form_id' => $formIds[0], // Contact Us Form
        'data' => [
            'firstName' => 'Jennifer',
            'lastName' => 'Martinez',
            'email' => 'jennifer.martinez@techcorp.com',
            'phone' => '555-0789',
            'company' => 'TechCorp Solutions',
            'message' => 'We are interested in your CRM solution for our sales team of 50+ people. Please contact us to discuss pricing and implementation.'
        ]
    ],
    [
        'form_id' => $formIds[2], // Demo Request Form
        'data' => [
            'firstName' => 'Richard',
            'lastName' => 'Lee',
            'email' => 'rlee@innovate.io',
            'phone' => '555-0890',
            'company' => 'Innovate.io',
            'title' => 'VP of Sales',
            'employees' => '51-200',
            'timeframe' => '1-3 months'
        ]
    ],
    [
        'form_id' => $formIds[1], // Newsletter
        'data' => [
            'email' => 'subscriber@example.com',
            'firstName' => 'News'
        ]
    ]
];

foreach ($submissions as $submission) {
    $submissionId = generateUUID();
    
    $sql = "INSERT INTO form_builder_submissions (id, form_id, data, submitted_at, created_by, modified_user_id, date_entered, date_modified, deleted) 
            VALUES (?, ?, ?, NOW(), '1', '1', NOW(), NOW(), 0)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "Error preparing submission statement: " . $conn->error . "\n";
        continue;
    }
    $dataJson = json_encode($submission['data']);
    $stmt->bind_param("sss", $submissionId, $submission['form_id'], $dataJson);
    if (!$stmt->execute()) {
        echo "Error executing submission statement: " . $stmt->error . "\n";
    }
    
    echo "  Created form submission\n";
}

// 3. Enhanced Knowledge Base with More Articles
echo "\nCreating comprehensive knowledge base...\n";

$kbCategories = [
    'Getting Started' => [
        ['title' => 'Quick Start Guide', 'content' => 'Welcome to our CRM! This guide will help you get started quickly...\n\n1. Dashboard Overview\n2. Managing Contacts\n3. Creating Your First Lead\n4. Setting Up Email Integration'],
        ['title' => 'System Requirements', 'content' => 'Minimum requirements for running our CRM:\n\n- Modern web browser (Chrome, Firefox, Safari, Edge)\n- Internet connection\n- 2GB RAM minimum\n- Screen resolution 1024x768 or higher'],
        ['title' => 'Initial Setup Checklist', 'content' => 'Follow these steps to set up your CRM:\n\n☐ Create user accounts\n☐ Import contacts\n☐ Configure email settings\n☐ Set up custom fields\n☐ Create sales pipeline stages']
    ],
    'Sales Features' => [
        ['title' => 'Lead Management Best Practices', 'content' => 'Effective lead management strategies:\n\n1. Respond within 24 hours\n2. Use lead scoring\n3. Track all interactions\n4. Segment by industry\n5. Regular follow-ups'],
        ['title' => 'Pipeline Management', 'content' => 'Managing your sales pipeline effectively:\n\n- Qualified: Initial interest confirmed\n- Proposal: Quote sent\n- Negotiation: Terms discussion\n- Won/Lost: Deal closed'],
        ['title' => 'Converting Leads to Opportunities', 'content' => 'Step-by-step guide to lead conversion:\n\n1. Qualify the lead\n2. Click "Convert" button\n3. Create or select account\n4. Create opportunity\n5. Set close date and amount']
    ],
    'Support & Troubleshooting' => [
        ['title' => 'Common Login Issues', 'content' => 'Troubleshooting login problems:\n\n1. Check caps lock\n2. Clear browser cache\n3. Reset password via email\n4. Check account status\n5. Contact admin if locked'],
        ['title' => 'Data Import Guide', 'content' => 'How to import your data:\n\n1. Prepare CSV file\n2. Map fields correctly\n3. Run test import\n4. Verify data\n5. Complete full import'],
        ['title' => 'API Integration Guide', 'content' => 'Integrating with our API:\n\n1. Get API credentials\n2. Authentication via JWT\n3. Rate limits: 1000/hour\n4. Webhook setup\n5. Error handling']
    ],
    'Advanced Features' => [
        ['title' => 'AI-Powered Lead Scoring', 'content' => 'Our AI analyzes:\n\n- Email engagement\n- Website activity\n- Company size\n- Industry fit\n- Behavioral patterns\n\nScores update in real-time.'],
        ['title' => 'Custom Reporting', 'content' => 'Create custom reports:\n\n1. Select data source\n2. Choose fields\n3. Add filters\n4. Set grouping\n5. Schedule delivery'],
        ['title' => 'Workflow Automation', 'content' => 'Automate repetitive tasks:\n\n- Email sequences\n- Task creation\n- Lead assignment\n- Status updates\n- Notifications']
    ]
];

foreach ($kbCategories as $categoryName => $articles) {
    // Create category
    $categoryId = generateUUID();
    $sql = "INSERT INTO aok_knowledge_base_categories (id, name, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, NOW(), NOW(), '1', '1', 0)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $categoryId, $categoryName);
    $stmt->execute();
    
    // Create articles
    foreach ($articles as $article) {
        $articleId = generateUUID();
        $sql = "INSERT INTO aok_knowledgebase (id, name, status, revision, description, date_entered, date_modified, created_by, modified_user_id, deleted) 
                VALUES (?, ?, 'published', '1', ?, NOW(), NOW(), '1', '1', 0)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $articleId, $article['title'], $article['content']);
        $stmt->execute();
        
        // Link to category
        $linkId = generateUUID();
        $sql = "INSERT INTO aok_knowledgebase_categories (id, aok_knowledgebase_id, aok_knowledge_base_categories_id, date_modified, deleted) 
                VALUES (?, ?, ?, NOW(), 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $linkId, $articleId, $categoryId);
        $stmt->execute();
    }
    
    echo "  Created category '$categoryName' with " . count($articles) . " articles\n";
}

// 4. Create Activity Tracking Data
echo "\nCreating activity tracking data...\n";

// Create visitors
$visitors = [];
for ($i = 1; $i <= 10; $i++) {
    $visitorId = generateUUID();
    $visitors[] = $visitorId;
    
    $sql = "INSERT INTO activity_tracking_visitors (visitor_id, first_visit, last_visit, total_visits, total_page_views, created_at) 
            VALUES (?, NOW() - INTERVAL ? DAY, NOW() - INTERVAL ? HOUR, ?, ?, NOW())";
    
    $firstVisitDays = rand(7, 30);
    $lastVisitHours = rand(1, 48);
    $totalVisits = rand(1, 10);
    $totalPageViews = rand(5, 50);
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siiii", $visitorId, $firstVisitDays, $lastVisitHours, $totalVisits, $totalPageViews);
    $stmt->execute();
}

// Create sessions and page views
$pages = [
    ['/', 'Home'],
    ['/features', 'Features'],
    ['/pricing', 'Pricing'],
    ['/demo', 'Request Demo'],
    ['/contact', 'Contact Us'],
    ['/blog/crm-best-practices', 'CRM Best Practices'],
    ['/resources/whitepaper', 'CRM Selection Guide']
];

foreach ($visitors as $visitorId) {
    // Create session
    $sessionId = generateUUID();
    $sql = "INSERT INTO activity_tracking_sessions (session_id, visitor_id, start_time, end_time, page_count, created_at) 
            VALUES (?, ?, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 1 HOUR, ?, NOW())";
    
    $pageCount = rand(3, 7);
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $sessionId, $visitorId, $pageCount);
    $stmt->execute();
    
    // Create page views
    for ($j = 0; $j < $pageCount; $j++) {
        $page = $pages[array_rand($pages)];
        $viewId = generateUUID();
        
        $sql = "INSERT INTO activity_tracking_page_views 
                (id, visitor_id, session_id, page_url, page_title, time_on_page, timestamp, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? MINUTE, NOW())";
        
        $timeOnPage = rand(10, 300);
        $minutesAgo = 120 - ($j * 10);
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssii", $viewId, $visitorId, $sessionId, $page[0], $page[1], $timeOnPage, $minutesAgo);
        $stmt->execute();
    }
}

echo "  Created " . count($visitors) . " visitors with sessions and page views\n";

// 5. Create Enhanced AI Chat Conversations
echo "\nCreating AI chat conversations...\n";

$chatScenarios = [
    [
        'contact_name' => 'Sarah Johnson',
        'messages' => [
            ['role' => 'user', 'content' => 'Hi, I need help with your CRM pricing'],
            ['role' => 'assistant', 'content' => 'Hello! I\'d be happy to help you with our pricing. We have plans starting at $29/user/month. How many users do you need?', 'intent' => 'pricing'],
            ['role' => 'user', 'content' => 'We have about 25 sales people'],
            ['role' => 'assistant', 'content' => 'For 25 users, I recommend our Professional plan at $49/user/month, which includes advanced features like AI lead scoring and custom workflows. Would you like to schedule a demo to see these features?', 'intent' => 'schedule_demo'],
            ['role' => 'user', 'content' => 'Yes, that would be great'],
            ['role' => 'assistant', 'content' => 'Perfect! I can help you schedule a demo. Our team typically does demos Tuesday through Thursday. What day works best for you?', 'intent' => 'schedule_demo']
        ]
    ],
    [
        'contact_name' => 'Robert Chen',
        'messages' => [
            ['role' => 'user', 'content' => 'I\'m having trouble logging into my account'],
            ['role' => 'assistant', 'content' => 'I\'m sorry to hear you\'re having login issues. I can help create a support ticket for you. Can you tell me what error message you\'re seeing?', 'intent' => 'support'],
            ['role' => 'user', 'content' => 'It says my account is locked'],
            ['role' => 'assistant', 'content' => 'I understand. An account lock usually happens after multiple failed login attempts. I\'ll create a high-priority support ticket for you. Our team will unlock your account and send you a password reset link within the next hour.', 'intent' => 'create_ticket']
        ]
    ],
    [
        'contact_name' => 'Emily Davis',
        'messages' => [
            ['role' => 'user', 'content' => 'How do I import my contacts from Salesforce?'],
            ['role' => 'assistant', 'content' => 'I can help you with importing contacts from Salesforce. Let me find the relevant guide for you...', 'intent' => 'knowledge_query'],
            ['role' => 'assistant', 'content' => 'I found a helpful article: "Data Import Guide"\n\nTo import from Salesforce:\n1. Export your contacts as CSV\n2. Go to Settings > Import\n3. Select "Contacts" and upload your file\n4. Map the fields\n5. Run the import\n\nWould you like me to send you the full guide?', 'intent' => 'knowledge_query']
        ]
    ]
];

foreach ($chatScenarios as $scenario) {
    $conversationId = generateUUID();
    
    // Find contact by name
    $nameParts = explode(' ', $scenario['contact_name']);
    $sql = "SELECT id FROM contacts WHERE first_name = ? AND last_name = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $nameParts[0], $nameParts[1]);
    $stmt->execute();
    $result = $stmt->get_result();
    $contact = $result->fetch_assoc();
    $contactId = $contact ? $contact['id'] : null;
    
    // Create conversation
    $sql = "INSERT INTO ai_chat_conversations 
            (id, contact_id, status, started_at, ended_at, created_by, modified_user_id, date_entered, date_modified, deleted) 
            VALUES (?, ?, 'completed', NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY + INTERVAL 30 MINUTE, '1', '1', NOW(), NOW(), 0)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $conversationId, $contactId);
    $stmt->execute();
    
    // Create messages
    $timestamp = time() - 86400; // Start 1 day ago
    foreach ($scenario['messages'] as $message) {
        $messageId = generateUUID();
        $metadata = null;
        
        if ($message['role'] === 'assistant' && isset($message['intent'])) {
            $metadata = json_encode([
                'intent' => $message['intent'],
                'sentiment' => 'positive',
                'confidence' => 0.95
            ]);
        }
        
        $sql = "INSERT INTO ai_chat_messages 
                (id, conversation_id, role, content, metadata, created_at, deleted) 
                VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?), 0)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $messageId, $conversationId, $message['role'], $message['content'], $metadata, $timestamp);
        $stmt->execute();
        
        $timestamp += 60; // Add 1 minute between messages
    }
}

echo "  Created " . count($chatScenarios) . " AI chat conversations\n";

// 6. Create Customer Health Scores
echo "\nCreating customer health scores...\n";

$sql = "SELECT id, account_name FROM contacts WHERE account_name IS NOT NULL AND account_name != '' GROUP BY account_name";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $scoreId = generateUUID();
    $healthScore = rand(60, 95);
    $riskLevel = $healthScore >= 80 ? 'low' : ($healthScore >= 65 ? 'medium' : 'high');
    
    $factors = json_encode([
        'engagement' => rand(70, 100),
        'usage' => rand(60, 100),
        'support_tickets' => rand(0, 5),
        'payment_history' => 100,
        'feature_adoption' => rand(50, 100)
    ]);
    
    $sql = "INSERT INTO customer_health_scores 
            (id, account_id, score, risk_level, factors, calculated_at, created_by, date_entered, date_modified, deleted) 
            VALUES (?, ?, ?, ?, ?, NOW(), '1', NOW(), NOW(), 0)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiss", $scoreId, $row['id'], $healthScore, $riskLevel, $factors);
    $stmt->execute();
}

echo "  Created customer health scores\n";

// 7. Create Email Templates
echo "\nCreating email templates...\n";

$templates = [
    [
        'name' => 'Welcome Email',
        'subject' => 'Welcome to {{company_name}}!',
        'body' => 'Hi {{first_name}},\n\nWelcome to our CRM platform! We\'re excited to have you on board.\n\nHere are some resources to get you started:\n- Quick Start Guide\n- Video Tutorials\n- Support Portal\n\nBest regards,\nThe Team'
    ],
    [
        'name' => 'Follow-up Email',
        'subject' => 'Following up on our conversation',
        'body' => 'Hi {{first_name}},\n\nThank you for taking the time to speak with me {{days_ago}} days ago about {{topic}}.\n\nI wanted to follow up on our discussion and see if you have any questions.\n\nBest regards,\n{{sender_name}}'
    ],
    [
        'name' => 'Demo Thank You',
        'subject' => 'Thank you for attending our demo',
        'body' => 'Hi {{first_name}},\n\nThank you for attending our product demo today. As promised, here are the key takeaways:\n\n{{key_points}}\n\nNext steps:\n{{next_steps}}\n\nPlease let me know if you have any questions!\n\nBest,\n{{sender_name}}'
    ]
];

foreach ($templates as $template) {
    $templateId = generateUUID();
    
    $sql = "INSERT INTO email_templates 
            (id, name, subject, body_html, body, type, date_entered, date_modified, created_by, modified_user_id, deleted) 
            VALUES (?, ?, ?, ?, ?, 'email', NOW(), NOW(), '1', '1', 0)";
    
    $bodyHtml = nl2br($template['body']);
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $templateId, $template['name'], $template['subject'], $bodyHtml, $template['body']);
    $stmt->execute();
}

echo "  Created " . count($templates) . " email templates\n";

// 8. Create API Rate Limits
echo "\nSetting up API rate limits...\n";

$sql = "INSERT INTO api_rate_limits (id, ip_address, endpoint, requests, window_start, created_at) 
        VALUES (?, '127.0.0.1', '/api/auth/login', 5, NOW(), NOW())";
$stmt = $conn->prepare($sql);
$limitId = generateUUID();
$stmt->bind_param("s", $limitId);
$stmt->execute();

echo "  Configured API rate limits\n";

// Summary
echo "\n=== Comprehensive Seeding Complete ===\n";
echo "Successfully created:\n";
echo "- " . count($forms) . " lead capture forms\n";
echo "- " . count($submissions) . " form submissions\n";
echo "- " . array_sum(array_map('count', $kbCategories)) . " knowledge base articles in " . count($kbCategories) . " categories\n";
echo "- " . count($visitors) . " visitor tracking records\n";
echo "- " . count($chatScenarios) . " AI chat conversations\n";
echo "- Customer health scores\n";
echo "- Email templates\n";
echo "- API configuration\n";
echo "\nThe CRM is now fully populated with realistic test data!\n";

$conn->close();