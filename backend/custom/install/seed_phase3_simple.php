<?php
/**
 * Simple Phase 3 Demo Data Seeder
 * Seeds basic data for testing Phase 3 features
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('include/database/DBManagerFactory.php');

function seedPhase3SimpleData() {
    global $db, $current_user;
    
    echo "Seeding Phase 3 demo data (simplified)...\n\n";
    
    // Get admin user
    $current_user = BeanFactory::getBean('Users', '1');
    
    // 1. Create demo forms
    echo "Creating demo forms...\n";
    $forms = [
        [
            'id' => create_guid(),
            'name' => 'Contact Us Form',
            'description' => 'Main contact form for website',
            'fields' => json_encode([
                ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                ['name' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                ['name' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => false],
            ]),
            'settings' => json_encode([
                'submit_button_text' => 'Send Message',
                'success_message' => 'Thank you for contacting us!',
            ]),
            'is_active' => 1,
            'created_by' => $current_user->id,
            'date_entered' => gmdate('Y-m-d H:i:s'),
            'date_modified' => gmdate('Y-m-d H:i:s'),
        ],
        [
            'id' => create_guid(),
            'name' => 'Demo Request Form',
            'description' => 'Request a product demonstration',
            'fields' => json_encode([
                ['name' => 'name', 'type' => 'text', 'label' => 'Full Name', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'label' => 'Work Email', 'required' => true],
                ['name' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'required' => false],
            ]),
            'settings' => json_encode([
                'submit_button_text' => 'Request Demo',
                'success_message' => 'We\'ll contact you within 24 hours!',
            ]),
            'is_active' => 1,
            'created_by' => $current_user->id,
            'date_entered' => gmdate('Y-m-d H:i:s'),
            'date_modified' => gmdate('Y-m-d H:i:s'),
        ],
    ];
    
    foreach ($forms as $form) {
        $query = "INSERT INTO form_builder_forms 
                 (id, name, description, fields, settings, is_active, created_by, date_entered, date_modified)
                 VALUES ('{$form['id']}', '{$form['name']}', '{$form['description']}', 
                         '{$form['fields']}', '{$form['settings']}', {$form['is_active']}, 
                         '{$form['created_by']}', '{$form['date_entered']}', '{$form['date_modified']}')";
        $db->query($query);
        echo "  ✓ Created form: {$form['name']}\n";
    }
    
    // 2. Create knowledge base articles
    echo "\nCreating knowledge base articles...\n";
    $articles = [
        [
            'id' => create_guid(),
            'title' => 'Getting Started with AI Lead Scoring',
            'slug' => 'getting-started-ai-lead-scoring',
            'content' => '<h2>Introduction to AI Lead Scoring</h2><p>Our AI-powered lead scoring system helps you identify the most promising leads by analyzing multiple factors including company size, engagement behavior, and industry match.</p>',
            'excerpt' => 'Learn how to use our AI-powered lead scoring system',
            'tags' => json_encode(['ai', 'lead-scoring', 'getting-started']),
            'is_public' => 1,
            'is_featured' => 1,
            'views' => rand(100, 500),
            'author_id' => $current_user->id,
            'date_entered' => gmdate('Y-m-d H:i:s'),
            'date_modified' => gmdate('Y-m-d H:i:s'),
        ],
        [
            'id' => create_guid(),
            'title' => 'Form Builder Best Practices',
            'slug' => 'form-builder-best-practices',
            'content' => '<h2>Creating Effective Forms</h2><p>Follow these best practices to create forms that convert visitors into leads.</p><ul><li>Keep forms short and focused</li><li>Use clear labels</li><li>Make CTAs prominent</li></ul>',
            'excerpt' => 'Best practices for creating high-converting forms',
            'tags' => json_encode(['forms', 'lead-capture', 'best-practices']),
            'is_public' => 1,
            'is_featured' => 0,
            'views' => rand(50, 200),
            'author_id' => $current_user->id,
            'date_entered' => gmdate('Y-m-d H:i:s'),
            'date_modified' => gmdate('Y-m-d H:i:s'),
        ],
        [
            'id' => create_guid(),
            'title' => 'Understanding Customer Health Scores',
            'slug' => 'understanding-customer-health-scores',
            'content' => '<h2>What is a Customer Health Score?</h2><p>Customer health scores help you identify at-risk accounts before they churn. Our system analyzes support tickets, user activity, and engagement patterns.</p>',
            'excerpt' => 'How customer health scoring helps prevent churn',
            'tags' => json_encode(['customer-success', 'health-score', 'retention']),
            'is_public' => 1,
            'is_featured' => 1,
            'views' => rand(200, 600),
            'author_id' => $current_user->id,
            'date_entered' => gmdate('Y-m-d H:i:s'),
            'date_modified' => gmdate('Y-m-d H:i:s'),
        ],
    ];
    
    foreach ($articles as $article) {
        $query = "INSERT INTO knowledge_base_articles 
                 (id, title, slug, content, excerpt, tags, is_public, is_featured, views, author_id, date_entered, date_modified)
                 VALUES ('{$article['id']}', '{$db->quote($article['title'])}', '{$article['slug']}', 
                         '{$db->quote($article['content'])}', '{$db->quote($article['excerpt'])}', '{$article['tags']}', 
                         {$article['is_public']}, {$article['is_featured']}, {$article['views']}, 
                         '{$article['author_id']}', '{$article['date_entered']}', '{$article['date_modified']}')";
        $db->query($query);
        echo "  ✓ Created article: {$article['title']}\n";
    }
    
    // 3. Create sample activity tracking data
    echo "\nCreating activity tracking data...\n";
    
    // Get some leads to track
    $leadsQuery = "SELECT id, first_name, last_name FROM leads WHERE deleted = 0 LIMIT 5";
    $leadsResult = $db->query($leadsQuery);
    
    $sessionCount = 0;
    while ($lead = $db->fetchByAssoc($leadsResult)) {
        $visitorId = 'visitor_' . substr(md5($lead['id']), 0, 12);
        
        // Create visitor record
        $visitorData = [
            'id' => create_guid(),
            'visitor_id' => $visitorId,
            'lead_id' => $lead['id'],
            'total_visits' => rand(1, 10),
            'total_page_views' => rand(5, 50),
            'total_time_spent' => rand(300, 3600),
            'engagement_score' => rand(40, 95),
            'last_visit' => gmdate('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days')),
            'date_created' => gmdate('Y-m-d H:i:s', strtotime('-' . rand(30, 90) . ' days')),
        ];
        
        $query = "INSERT INTO activity_tracking_visitors 
                 (id, visitor_id, lead_id, total_visits, total_page_views, total_time_spent, engagement_score, last_visit, date_created)
                 VALUES ('{$visitorData['id']}', '{$visitorData['visitor_id']}', '{$visitorData['lead_id']}', 
                         {$visitorData['total_visits']}, {$visitorData['total_page_views']}, {$visitorData['total_time_spent']}, 
                         {$visitorData['engagement_score']}, '{$visitorData['last_visit']}', '{$visitorData['date_created']}')";
        $db->query($query);
        
        // Create a session
        $sessionData = [
            'id' => create_guid(),
            'visitor_id' => $visitorId,
            'lead_id' => $lead['id'],
            'ip_address' => '192.168.1.' . rand(1, 255),
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0',
            'referrer' => 'https://google.com',
            'landing_page' => '/features',
            'exit_page' => '/pricing',
            'page_views' => rand(3, 10),
            'total_time' => rand(120, 600),
            'bounce' => rand(0, 1),
            'date_started' => gmdate('Y-m-d H:i:s', strtotime('-' . rand(1, 7) . ' days')),
        ];
        
        $query = "INSERT INTO activity_tracking_sessions 
                 (id, visitor_id, lead_id, ip_address, user_agent, referrer, landing_page, exit_page, page_views, total_time, bounce, date_started)
                 VALUES ('{$sessionData['id']}', '{$sessionData['visitor_id']}', '{$sessionData['lead_id']}', 
                         '{$sessionData['ip_address']}', '{$sessionData['user_agent']}', '{$sessionData['referrer']}', 
                         '{$sessionData['landing_page']}', '{$sessionData['exit_page']}', {$sessionData['page_views']}, 
                         {$sessionData['total_time']}, {$sessionData['bounce']}, '{$sessionData['date_started']}')";
        $db->query($query);
        
        $sessionCount++;
    }
    echo "  ✓ Created $sessionCount visitor sessions\n";
    
    // 4. Create sample AI lead scores
    echo "\nCreating AI lead score history...\n";
    
    $leadsQuery = "SELECT id FROM leads WHERE deleted = 0 AND ai_score IS NULL LIMIT 10";
    $leadsResult = $db->query($leadsQuery);
    
    $scoreCount = 0;
    while ($lead = $db->fetchByAssoc($leadsResult)) {
        $score = rand(20, 95);
        $factors = [
            'company_size' => rand(5, 20),
            'industry_match' => rand(5, 20),
            'behavior_score' => rand(5, 25),
            'engagement' => rand(5, 20),
            'budget_signals' => rand(5, 20),
        ];
        
        // Create score history
        $scoreData = [
            'id' => create_guid(),
            'lead_id' => $lead['id'],
            'score' => $score,
            'factors' => json_encode($factors),
            'insights' => json_encode(['High engagement on pricing page', 'Downloaded whitepaper']),
            'confidence' => round(rand(70, 95) / 100, 2),
            'model_version' => 'demo-v1',
            'date_created' => gmdate('Y-m-d H:i:s'),
        ];
        
        $query = "INSERT INTO ai_lead_scoring_history 
                 (id, lead_id, score, factors, insights, confidence, model_version, date_created)
                 VALUES ('{$scoreData['id']}', '{$scoreData['lead_id']}', {$scoreData['score']}, 
                         '{$scoreData['factors']}', '{$scoreData['insights']}', {$scoreData['confidence']}, 
                         '{$scoreData['model_version']}', '{$scoreData['date_created']}')";
        $db->query($query);
        
        // Update lead record
        $updateQuery = "UPDATE leads SET ai_score = $score, ai_score_date = '{$scoreData['date_created']}' WHERE id = '{$lead['id']}'";
        $db->query($updateQuery);
        
        $scoreCount++;
    }
    echo "  ✓ Created $scoreCount AI lead scores\n";
    
    // 5. Create sample chat conversations
    echo "\nCreating AI chat conversations...\n";
    
    $conversations = [
        [
            'visitor_id' => 'chat_visitor_001',
            'intent' => 'sales',
            'sentiment' => 'positive',
            'lead_score' => 85,
            'status' => 'completed',
        ],
        [
            'visitor_id' => 'chat_visitor_002',
            'intent' => 'support',
            'sentiment' => 'neutral',
            'lead_score' => null,
            'status' => 'completed',
        ],
    ];
    
    foreach ($conversations as $conv) {
        $convId = create_guid();
        
        $query = "INSERT INTO ai_chat_conversations 
                 (id, visitor_id, intent, sentiment, lead_score, status, date_started, date_ended)
                 VALUES ('$convId', '{$conv['visitor_id']}', '{$conv['intent']}', '{$conv['sentiment']}', 
                         " . ($conv['lead_score'] ?: 'NULL') . ", '{$conv['status']}', 
                         '" . gmdate('Y-m-d H:i:s', strtotime('-2 hours')) . "', 
                         '" . gmdate('Y-m-d H:i:s', strtotime('-1 hour')) . "')";
        $db->query($query);
        
        // Add sample messages
        $messages = [
            ['role' => 'visitor', 'message' => 'Hi, I\'m interested in your CRM solution'],
            ['role' => 'bot', 'message' => 'Hello! I\'d be happy to help you learn about our CRM. What specific features are you looking for?'],
            ['role' => 'visitor', 'message' => 'I need something with good lead scoring'],
            ['role' => 'bot', 'message' => 'Great! Our AI-powered lead scoring is one of our strongest features. It analyzes multiple factors to help you identify hot prospects.'],
        ];
        
        foreach ($messages as $idx => $msg) {
            $msgId = create_guid();
            $query = "INSERT INTO ai_chat_messages 
                     (id, conversation_id, role, message, timestamp)
                     VALUES ('$msgId', '$convId', '{$msg['role']}', '{$db->quote($msg['message'])}', 
                             '" . gmdate('Y-m-d H:i:s', strtotime('-90 minutes') + ($idx * 60)) . "')";
            $db->query($query);
        }
    }
    echo "  ✓ Created " . count($conversations) . " chat conversations\n";
    
    echo "\n✅ Phase 3 demo data seeded successfully!\n";
}

// Run the seeder
try {
    seedPhase3SimpleData();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}