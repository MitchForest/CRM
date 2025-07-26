<?php
/**
 * Phase 3 - Seed Demo Data
 * Creates sample data for AI features, forms, knowledge base, and tracking
 */

// Bootstrap SuiteCRM
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

// Load OpenAI service
require_once(__DIR__ . '/../services/OpenAIService.php');
use SuiteCRM\Custom\Services\OpenAIService;

function seedPhase3Data() {
    global $db;
    
    echo "Seeding Phase 3 demo data...\n\n";
    
    // Get admin user
    $adminQuery = "SELECT id FROM users WHERE user_name = 'admin' AND deleted = 0 LIMIT 1";
    $adminResult = $db->query($adminQuery);
    $adminRow = $db->fetchByAssoc($adminResult);
    $adminId = $adminRow['id'] ?? '1';
    
    // Initialize OpenAI service
    try {
        $openAIService = new OpenAIService();
        $hasOpenAI = true;
    } catch (Exception $e) {
        echo "Warning: OpenAI service not available. Embeddings will be skipped.\n";
        $hasOpenAI = false;
    }
    
    // 1. Create Form Builder Forms
    echo "Creating forms...\n";
    $forms = [
        [
            'id' => generateUUID(),
            'name' => 'Contact Sales Form',
            'description' => 'Main contact form for sales inquiries',
            'fields' => [
                ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                ['name' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                ['name' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'required' => false],
                ['name' => 'message', 'type' => 'textarea', 'label' => 'How can we help?', 'required' => true],
            ],
            'settings' => [
                'submit_button_text' => 'Get in Touch',
                'success_message' => 'Thank you! We\'ll be in touch within 24 hours.',
                'notification_email' => 'sales@company.com',
            ],
        ],
        [
            'id' => generateUUID(),
            'name' => 'Demo Request Form',
            'description' => 'Form for requesting product demonstrations',
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'label' => 'Full Name', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'Work Email', 'required' => true],
                ['name' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => true],
                ['name' => 'company_size', 'type' => 'select', 'label' => 'Company Size', 'required' => true, 
                 'options' => [
                    ['label' => '1-10', 'value' => '1-10'],
                    ['label' => '11-50', 'value' => '11-50'],
                    ['label' => '51-200', 'value' => '51-200'],
                    ['label' => '201-1000', 'value' => '201-1000'],
                    ['label' => '1000+', 'value' => '1000+'],
                ]],
                ['name' => 'preferred_date', 'type' => 'text', 'label' => 'Preferred Demo Date', 'required' => false],
            ],
            'settings' => [
                'submit_button_text' => 'Request Demo',
                'success_message' => 'Demo request received! Check your email for available time slots.',
                'redirect_url' => '/thank-you',
            ],
        ],
        [
            'id' => generateUUID(),
            'name' => 'Newsletter Subscription',
            'description' => 'Simple newsletter signup form',
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true],
                ['name' => 'interests', 'type' => 'checkbox', 'label' => 'I\'m interested in product updates', 'required' => false],
            ],
            'settings' => [
                'submit_button_text' => 'Subscribe',
                'success_message' => 'You\'re subscribed! Check your inbox to confirm.',
            ],
        ],
    ];
    
    foreach ($forms as $formData) {
        $embedCode = generateEmbedCode($formData['id']);
        
        $query = "INSERT INTO form_builder_forms 
                 (id, name, description, fields, settings, embed_code, is_active, 
                  submissions_count, created_by, date_entered, date_modified, deleted)
                 VALUES (?, ?, ?, ?, ?, ?, 1, 0, ?, NOW(), NOW(), 0)";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            $formData['id'],
            $formData['name'],
            $formData['description'],
            json_encode($formData['fields']),
            json_encode($formData['settings']),
            $embedCode,
            $adminId
        ]);
        
        echo "✓ Created form: {$formData['name']}\n";
    }
    
    // 2. Create Knowledge Base Articles
    echo "\nCreating knowledge base articles...\n";
    $articles = [
        [
            'title' => 'Getting Started with Our CRM',
            'slug' => 'getting-started-crm',
            'category' => 'getting-started',
            'summary' => 'Learn the basics of setting up and using our CRM platform',
            'content' => '<h2>Welcome to Our CRM</h2><p>This guide will help you get started with our CRM platform. Follow these steps to set up your account and start managing your customer relationships effectively.</p><h3>Step 1: Account Setup</h3><p>After logging in for the first time, you\'ll need to configure your account settings...</p><h3>Step 2: Import Your Contacts</h3><p>You can easily import your existing contacts from CSV files or integrate with your email provider...</p><h3>Step 3: Customize Your Pipeline</h3><p>Our CRM allows you to customize your sales pipeline to match your business process...</p>',
            'tags' => ['setup', 'onboarding', 'basics'],
            'is_featured' => true,
        ],
        [
            'title' => 'Understanding Lead Scoring',
            'slug' => 'understanding-lead-scoring',
            'category' => 'features',
            'summary' => 'How our AI-powered lead scoring helps you prioritize prospects',
            'content' => '<h2>AI-Powered Lead Scoring</h2><p>Our CRM uses advanced AI to automatically score your leads based on multiple factors.</p><h3>How It Works</h3><p>The AI analyzes company size, industry, engagement level, and behavioral signals to assign a score from 0-100.</p><h3>Score Factors</h3><ul><li>Company Size (20%)</li><li>Industry Match (15%)</li><li>Behavior Score (25%)</li><li>Engagement (20%)</li><li>Budget Signals (20%)</li></ul>',
            'tags' => ['ai', 'lead-scoring', 'automation'],
            'is_featured' => true,
        ],
        [
            'title' => 'API Integration Guide',
            'slug' => 'api-integration-guide',
            'category' => 'api-documentation',
            'summary' => 'Complete guide to integrating with our RESTful API',
            'content' => '<h2>API Overview</h2><p>Our RESTful API allows you to integrate your applications with our CRM.</p><h3>Authentication</h3><p>All API requests require authentication using JWT tokens...</p><h3>Endpoints</h3><p>Key endpoints include:</p><ul><li>/api/v8/leads - Manage leads</li><li>/api/v8/opportunities - Manage opportunities</li><li>/api/v8/ai/score - AI lead scoring</li></ul>',
            'tags' => ['api', 'integration', 'developers'],
        ],
        [
            'title' => 'Troubleshooting Common Issues',
            'slug' => 'troubleshooting-common-issues',
            'category' => 'troubleshooting',
            'summary' => 'Solutions to frequently encountered problems',
            'content' => '<h2>Common Issues and Solutions</h2><h3>Login Problems</h3><p>If you\'re having trouble logging in, try these steps...</p><h3>Data Import Errors</h3><p>When importing data, make sure your CSV file follows our format...</p><h3>API Rate Limits</h3><p>If you\'re hitting rate limits, consider implementing exponential backoff...</p>',
            'tags' => ['troubleshooting', 'support', 'help'],
        ],
        [
            'title' => 'Best Practices for B2B Sales',
            'slug' => 'b2b-sales-best-practices',
            'category' => 'best-practices',
            'summary' => 'Proven strategies for B2B sales success using our CRM',
            'content' => '<h2>B2B Sales Best Practices</h2><p>Maximize your sales effectiveness with these proven strategies.</p><h3>Lead Nurturing</h3><p>Use our automated workflows to nurture leads through the sales funnel...</p><h3>Pipeline Management</h3><p>Keep your pipeline healthy by regularly reviewing and updating opportunity stages...</p>',
            'tags' => ['sales', 'b2b', 'strategy'],
            'is_featured' => true,
        ],
    ];
    
    foreach ($articles as $article) {
        $articleId = generateUUID();
        
        // Generate embedding if OpenAI is available
        $embedding = null;
        if ($hasOpenAI) {
            try {
                $textForEmbedding = $article['title'] . ' ' . $article['summary'] . ' ' . strip_tags($article['content']);
                $embedding = $openAIService->generateEmbedding($textForEmbedding);
            } catch (Exception $e) {
                echo "  Warning: Could not generate embedding for article\n";
            }
        }
        
        $query = "INSERT INTO knowledge_base_articles 
                 (id, title, slug, content, summary, category, tags, is_published, 
                  is_featured, view_count, helpful_count, not_helpful_count, 
                  embedding, author_id, date_published, date_entered, date_modified, deleted)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 0, 0, 0, ?, ?, NOW(), NOW(), NOW(), 0)";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            $articleId,
            $article['title'],
            $article['slug'],
            $article['content'],
            $article['summary'],
            $article['category'],
            json_encode($article['tags']),
            (int)($article['is_featured'] ?? false),
            $embedding ? json_encode($embedding) : null,
            $adminId
        ]);
        
        echo "✓ Created article: {$article['title']}\n";
    }
    
    // 3. Create Sample Activity Tracking Data
    echo "\nCreating activity tracking data...\n";
    
    // Get some leads to associate with visitors
    $leadsQuery = "SELECT id, email FROM leads WHERE deleted = 0 LIMIT 5";
    $leadsResult = $db->query($leadsQuery);
    $leads = [];
    while ($row = $db->fetchByAssoc($leadsResult)) {
        $leads[] = $row;
    }
    
    // Create visitors
    $visitors = [
        ['visitor_id' => 'visitor_' . uniqid(), 'lead_id' => $leads[0]['id'] ?? null, 'engagement' => 'high'],
        ['visitor_id' => 'visitor_' . uniqid(), 'lead_id' => $leads[1]['id'] ?? null, 'engagement' => 'medium'],
        ['visitor_id' => 'visitor_' . uniqid(), 'lead_id' => null, 'engagement' => 'low'],
    ];
    
    foreach ($visitors as $visitorData) {
        $visitorRecordId = generateUUID();
        
        $totalVisits = $visitorData['engagement'] === 'high' ? 8 : ($visitorData['engagement'] === 'medium' ? 4 : 2);
        $totalPageViews = $visitorData['engagement'] === 'high' ? 25 : ($visitorData['engagement'] === 'medium' ? 12 : 5);
        $totalTimeSpent = $visitorData['engagement'] === 'high' ? 1800 : ($visitorData['engagement'] === 'medium' ? 600 : 180);
        $engagementScore = $visitorData['engagement'] === 'high' ? 85 : ($visitorData['engagement'] === 'medium' ? 60 : 30);
        
        $query = "INSERT INTO activity_tracking_visitors 
                 (id, visitor_id, lead_id, first_visit, last_visit, total_visits, 
                  total_page_views, total_time_spent, browser, device_type, 
                  referrer_source, engagement_score, date_modified)
                 VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL 30 DAY), NOW(), ?, ?, ?, 
                         'Chrome', 'desktop', 'google', ?, NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            $visitorRecordId,
            $visitorData['visitor_id'],
            $visitorData['lead_id'],
            $totalVisits,
            $totalPageViews,
            $totalTimeSpent,
            $engagementScore
        ]);
        
        // Create some sessions
        for ($i = 0; $i < min($totalVisits, 3); $i++) {
            $sessionId = 'session_' . uniqid();
            $sessionRecordId = generateUUID();
            
            $query = "INSERT INTO activity_tracking_sessions 
                     (id, visitor_id, session_id, ip_address, start_time, end_time, 
                      duration, page_count, bounce, date_created)
                     VALUES (?, ?, ?, '192.168.1.1', DATE_SUB(NOW(), INTERVAL ? DAY), 
                             DATE_SUB(NOW(), INTERVAL ? DAY), ?, ?, ?, NOW())";
            
            $pageCount = rand(3, 8);
            $duration = rand(180, 900);
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                $sessionRecordId,
                $visitorData['visitor_id'],
                $sessionId,
                (20 - $i * 5),
                (20 - $i * 5),
                $duration,
                $pageCount,
                0
            ]);
            
            // Create page views
            $pages = ['/home', '/features', '/pricing', '/demo', '/contact', '/about'];
            for ($j = 0; $j < min($pageCount, 4); $j++) {
                $pageViewId = generateUUID();
                $page = $pages[array_rand($pages)];
                $isHighValue = in_array($page, ['/pricing', '/demo', '/contact']);
                
                $query = "INSERT INTO activity_tracking_page_views 
                         (id, visitor_id, session_id, page_url, page_title, 
                          time_on_page, scroll_depth, clicks, is_high_value, date_created)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $pageViewId,
                    $visitorData['visitor_id'],
                    $sessionId,
                    $page,
                    ucfirst(trim($page, '/')) . ' - CRM Platform',
                    rand(30, 300),
                    rand(20, 100),
                    rand(0, 5),
                    (int)$isHighValue
                ]);
            }
        }
        
        echo "✓ Created visitor with {$visitorData['engagement']} engagement\n";
    }
    
    // 4. Score existing leads with AI
    echo "\nScoring existing leads with AI...\n";
    $leadsToScore = $db->query("SELECT id, first_name, last_name FROM leads WHERE deleted = 0 AND ai_score IS NULL LIMIT 10");
    $scoredCount = 0;
    
    while ($lead = $db->fetchByAssoc($leadsToScore)) {
        // Create AI scoring history
        $scoreHistoryId = generateUUID();
        $score = rand(40, 95);
        
        $factors = [
            'company_size' => rand(10, 20),
            'industry_match' => rand(8, 15),
            'behavior_score' => rand(15, 25),
            'engagement' => rand(10, 20),
            'budget_signals' => rand(10, 20),
        ];
        
        $insights = [
            'Company shows strong buying signals',
            'High engagement with pricing page',
            'Decision maker title identified',
        ];
        
        $recommendations = [
            'Schedule a demo within next 2 days',
            'Send case study for their industry',
            'Connect on LinkedIn',
        ];
        
        $query = "INSERT INTO ai_lead_scoring_history 
                 (id, lead_id, score, previous_score, score_change, factors, 
                  insights, recommendations, model_version, date_scored)
                 VALUES (?, ?, ?, 0, ?, ?, ?, ?, 'gpt-4-turbo-preview', NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            $scoreHistoryId,
            $lead['id'],
            $score,
            $score,
            json_encode($factors),
            json_encode($insights),
            json_encode($recommendations)
        ]);
        
        // Update lead
        $updateQuery = "UPDATE leads SET ai_score = ?, ai_score_date = NOW() WHERE id = ?";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([$score, $lead['id']]);
        
        $scoredCount++;
        echo "✓ Scored lead: {$lead['first_name']} {$lead['last_name']} (Score: $score)\n";
    }
    
    // 5. Create sample chat conversations
    echo "\nCreating chat conversations...\n";
    $conversations = [
        [
            'visitor_id' => $visitors[0]['visitor_id'],
            'lead_id' => $leads[0]['id'] ?? null,
            'messages' => [
                ['role' => 'user', 'content' => 'Hi, I\'m interested in learning more about your CRM'],
                ['role' => 'assistant', 'content' => 'Hello! I\'d be happy to help you learn about our CRM platform. What specific features are you most interested in?'],
                ['role' => 'user', 'content' => 'I need something that can handle lead scoring and automation'],
                ['role' => 'assistant', 'content' => 'Great! Our CRM includes AI-powered lead scoring that automatically prioritizes your prospects based on multiple factors. We also offer workflow automation for lead nurturing. Would you like to schedule a demo to see these features in action?'],
            ]
        ],
        [
            'visitor_id' => $visitors[1]['visitor_id'],
            'lead_id' => $leads[1]['id'] ?? null,
            'messages' => [
                ['role' => 'user', 'content' => 'What integrations do you support?'],
                ['role' => 'assistant', 'content' => 'We support integrations with popular tools like Gmail, Outlook, Slack, and Zapier. We also have a comprehensive API for custom integrations. What tools are you currently using that you\'d like to integrate?'],
            ]
        ],
    ];
    
    foreach ($conversations as $conv) {
        $conversationId = generateUUID();
        
        $query = "INSERT INTO ai_chat_conversations 
                 (id, visitor_id, lead_id, status, start_time, message_count, date_created, date_modified)
                 VALUES (?, ?, ?, 'ended', DATE_SUB(NOW(), INTERVAL 1 DAY), ?, NOW(), NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            $conversationId,
            $conv['visitor_id'],
            $conv['lead_id'],
            count($conv['messages'])
        ]);
        
        foreach ($conv['messages'] as $message) {
            $messageId = generateUUID();
            
            $query = "INSERT INTO ai_chat_messages 
                     (id, conversation_id, role, content, date_created)
                     VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                $messageId,
                $conversationId,
                $message['role'],
                $message['content']
            ]);
        }
        
        echo "✓ Created chat conversation with " . count($conv['messages']) . " messages\n";
    }
    
    // 6. Create form submissions
    echo "\nCreating form submissions...\n";
    $submissions = [
        [
            'form_id' => $forms[0]['id'], // Contact Sales Form
            'data' => [
                'first_name' => 'John',
                'last_name' => 'Demo',
                'email' => 'john.demo@techcorp.com',
                'company' => 'TechCorp Solutions',
                'phone' => '555-0123',
                'message' => 'We are interested in implementing your CRM for our 50-person sales team.',
            ]
        ],
        [
            'form_id' => $forms[1]['id'], // Demo Request Form
            'data' => [
                'name' => 'Sarah Johnson',
                'email' => 'sarah@innovate.io',
                'company' => 'Innovate.io',
                'company_size' => '51-200',
                'preferred_date' => 'Next Tuesday afternoon',
            ]
        ],
    ];
    
    foreach ($submissions as $submission) {
        $submissionId = generateUUID();
        
        $query = "INSERT INTO form_builder_submissions 
                 (id, form_id, data, ip_address, referrer_url, date_submitted, deleted)
                 VALUES (?, ?, ?, '192.168.1.100', 'https://ourcrm.com/demo', NOW(), 0)";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            $submissionId,
            $submission['form_id'],
            json_encode($submission['data'])
        ]);
        
        // Update form submission count
        $updateQuery = "UPDATE form_builder_forms 
                       SET submissions_count = submissions_count + 1 
                       WHERE id = ?";
        
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([$submission['form_id']]);
        
        echo "✓ Created form submission for: " . ($submission['data']['email'] ?? 'Unknown') . "\n";
    }
    
    echo "\n✅ Phase 3 demo data seeded successfully!\n";
    echo "\nSummary:\n";
    echo "- " . count($forms) . " forms created\n";
    echo "- " . count($articles) . " knowledge base articles created\n";
    echo "- " . count($visitors) . " visitor profiles with activity data\n";
    echo "- $scoredCount leads scored with AI\n";
    echo "- " . count($conversations) . " chat conversations created\n";
    echo "- " . count($submissions) . " form submissions created\n";
}

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

function generateEmbedCode($formId) {
    $siteUrl = 'http://localhost:8080';
    return <<<HTML
<!-- CRM Form Embed -->
<div id="crm-form-{$formId}"></div>
<script>
(function(w,d,s,f,id){
    w['CRMForm']=w['CRMForm']||function(){(w['CRMForm'].q=w['CRMForm'].q||[]).push(arguments)};
    if(!d.getElementById(id)){
        var js=d.createElement(s),fjs=d.getElementsByTagName(s)[0];
        js.id=id;js.src=f;js.async=1;
        fjs.parentNode.insertBefore(js,fjs);
    }
})(window,document,'script','{$siteUrl}/forms/embed.js','crm-forms-sdk');
CRMForm('init', '{$formId}');
</script>
<!-- End CRM Form Embed -->
HTML;
}

// Run the seeding
seedPhase3Data();