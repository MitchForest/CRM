<?php
/**
 * Phase 5 Seed Data Script - Populates database with realistic sample data
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line\n");
}

// Load SuiteCRM bootstrap
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

chdir(dirname(__FILE__) . '/../../..');
require_once('include/entryPoint.php');
require_once('modules/Users/User.php');

global $db, $current_user;

// Set admin as current user for proper permissions
$current_user = BeanFactory::getBean('Users', '1');

echo "\n=== Phase 5 Seed Data Script ===\n";
echo "This will populate the database with sample data.\n";
echo "Starting seed process...\n\n";

try {
    // Helper function to create GUID
    function createGuid() {
        return create_guid();
    }
    
    // Helper function to get random date in range
    function randomDate($start, $end) {
        $timestamp = mt_rand(strtotime($start), strtotime($end));
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    // 1. Create Users
    echo "Creating users...\n";
    
    $users = [
        [
            'user_name' => 'sales_rep1',
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'email1' => 'sarah.johnson@example.com',
            'title' => 'Sales Representative',
            'department' => 'Sales',
            'phone_work' => '555-0201',
            'role' => 'sales_rep'
        ],
        [
            'user_name' => 'sales_rep2',
            'first_name' => 'Mike',
            'last_name' => 'Wilson',
            'email1' => 'mike.wilson@example.com',
            'title' => 'Senior Sales Representative',
            'department' => 'Sales',
            'phone_work' => '555-0202',
            'role' => 'sales_rep'
        ],
        [
            'user_name' => 'cs_rep1',
            'first_name' => 'Emma',
            'last_name' => 'Davis',
            'email1' => 'emma.davis@example.com',
            'title' => 'Customer Success Manager',
            'department' => 'Customer Success',
            'phone_work' => '555-0301',
            'role' => 'customer_success'
        ],
        [
            'user_name' => 'cs_rep2',
            'first_name' => 'James',
            'last_name' => 'Brown',
            'email1' => 'james.brown@example.com',
            'title' => 'Customer Success Representative',
            'department' => 'Customer Success',
            'phone_work' => '555-0302',
            'role' => 'customer_success'
        ]
    ];
    
    $userIds = [];
    foreach ($users as $userData) {
        $user = BeanFactory::newBean('Users');
        $user->user_name = $userData['user_name'];
        $user->first_name = $userData['first_name'];
        $user->last_name = $userData['last_name'];
        $user->email1 = $userData['email1'];
        $user->title = $userData['title'];
        $user->department = $userData['department'];
        $user->phone_work = $userData['phone_work'];
        $user->status = 'Active';
        $user->employee_status = 'Active';
        $user->user_hash = password_hash('password123', PASSWORD_DEFAULT); // Default password
        $user->save();
        
        $userIds[$userData['role']][] = $user->id;
        echo "  Created user: {$userData['first_name']} {$userData['last_name']}\n";
    }
    
    // 2. Create Leads
    echo "\nCreating leads...\n";
    
    $leadSources = ['Website', 'Email Campaign', 'Trade Show', 'Referral', 'Cold Call', 'Chat'];
    $leadStatuses = ['New', 'Contacted', 'Qualified'];
    $industries = ['Technology', 'Healthcare', 'Finance', 'Retail', 'Manufacturing', 'Education'];
    
    $leads = [];
    for ($i = 1; $i <= 20; $i++) {
        $lead = BeanFactory::newBean('Leads');
        $lead->first_name = ['John', 'Jane', 'Michael', 'Emily', 'David', 'Sarah', 'Robert', 'Lisa'][array_rand(['John', 'Jane', 'Michael', 'Emily', 'David', 'Sarah', 'Robert', 'Lisa'])];
        $lead->last_name = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'][array_rand(['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'])];
        $lead->title = ['CEO', 'CTO', 'VP Sales', 'Marketing Director', 'IT Manager', 'Operations Manager'][array_rand(['CEO', 'CTO', 'VP Sales', 'Marketing Director', 'IT Manager', 'Operations Manager'])];
        $lead->email1 = strtolower($lead->first_name . '.' . $lead->last_name . $i . '@example-company.com');
        $lead->phone_work = '555-' . sprintf('%04d', 1000 + $i);
        $lead->phone_mobile = '555-' . sprintf('%04d', 2000 + $i);
        $lead->account_name = 'Company ' . $i . ' Inc';
        $lead->website = 'https://www.company' . $i . '.com';
        $lead->lead_source = $leadSources[array_rand($leadSources)];
        $lead->status = $leadStatuses[array_rand($leadStatuses)];
        $lead->industry = $industries[array_rand($industries)];
        $lead->employees = rand(10, 1000);
        $lead->annual_revenue = rand(100000, 10000000);
        $lead->description = 'Lead from ' . $lead->lead_source . ' - interested in our solutions';
        $lead->assigned_user_id = $userIds['sales_rep'][array_rand($userIds['sales_rep'])];
        $lead->date_entered = randomDate('-30 days', 'now');
        $lead->save();
        
        $leads[] = $lead;
        echo "  Created lead: {$lead->first_name} {$lead->last_name} ({$lead->account_name})\n";
    }
    
    // 3. Create Contacts (mix of people and companies)
    echo "\nCreating contacts...\n";
    
    $contacts = [];
    for ($i = 1; $i <= 15; $i++) {
        $contact = BeanFactory::newBean('Contacts');
        
        if ($i <= 10) {
            // Person contact
            $contact->first_name = ['Alice', 'Bob', 'Carol', 'Daniel', 'Eva', 'Frank', 'Grace', 'Henry'][array_rand(['Alice', 'Bob', 'Carol', 'Daniel', 'Eva', 'Frank', 'Grace', 'Henry'])];
            $contact->last_name = ['Anderson', 'Baker', 'Clark', 'Davis', 'Evans', 'Foster', 'Green', 'Hill'][array_rand(['Anderson', 'Baker', 'Clark', 'Davis', 'Evans', 'Foster', 'Green', 'Hill'])];
            $contact->title = ['Director', 'Manager', 'Analyst', 'Consultant', 'Specialist'][array_rand(['Director', 'Manager', 'Analyst', 'Consultant', 'Specialist'])];
            $contact->email1 = strtolower($contact->first_name . '.' . $contact->last_name . '@contact-company' . $i . '.com');
        } else {
            // Company contact
            $contact->first_name = '';
            $contact->last_name = 'Enterprise Solutions ' . ($i - 10);
            $contact->account_name = 'Enterprise Solutions ' . ($i - 10) . ' LLC';
        }
        
        $contact->phone_work = '555-' . sprintf('%04d', 3000 + $i);
        $contact->phone_mobile = '555-' . sprintf('%04d', 4000 + $i);
        $contact->department = ['Sales', 'Marketing', 'IT', 'Operations', 'Finance'][array_rand(['Sales', 'Marketing', 'IT', 'Operations', 'Finance'])];
        $contact->assigned_user_id = $userIds['sales_rep'][array_rand($userIds['sales_rep'])];
        $contact->date_entered = randomDate('-60 days', 'now');
        $contact->save();
        
        $contacts[] = $contact;
        echo "  Created contact: " . ($contact->first_name ? "{$contact->first_name} {$contact->last_name}" : $contact->account_name) . "\n";
    }
    
    // 4. Create Opportunities
    echo "\nCreating opportunities...\n";
    
    $oppStages = ['Qualified', 'Proposal', 'Negotiation'];
    $oppTypes = ['New Business', 'Existing Business', 'Renewal'];
    
    for ($i = 1; $i <= 10; $i++) {
        $opp = BeanFactory::newBean('Opportunities');
        $opp->name = 'Deal ' . $i . ' - ' . ['Software License', 'Service Agreement', 'Implementation Project', 'Support Contract'][array_rand(['Software License', 'Service Agreement', 'Implementation Project', 'Support Contract'])];
        $opp->amount = rand(10000, 500000);
        $opp->sales_stage = $oppStages[array_rand($oppStages)];
        $opp->probability = $opp->sales_stage == 'Qualified' ? 30 : ($opp->sales_stage == 'Proposal' ? 60 : 80);
        $opp->date_closed = date('Y-m-d', strtotime('+' . rand(30, 120) . ' days'));
        $opp->opportunity_type = $oppTypes[array_rand($oppTypes)];
        $opp->lead_source = $leadSources[array_rand($leadSources)];
        $opp->description = 'Opportunity for ' . $opp->name;
        $opp->assigned_user_id = $userIds['sales_rep'][array_rand($userIds['sales_rep'])];
        $opp->date_entered = randomDate('-20 days', 'now');
        
        // Link to a contact
        if (!empty($contacts)) {
            $opp->save();
            $contact = $contacts[array_rand($contacts)];
            $opp->load_relationship('contacts');
            $opp->contacts->add($contact->id);
        }
        
        echo "  Created opportunity: {$opp->name} (\${$opp->amount})\n";
    }
    
    // 5. Create Support Tickets (Cases)
    echo "\nCreating support tickets...\n";
    
    $ticketTypes = ['Technical', 'Billing', 'Feature Request', 'Other'];
    $ticketStatuses = ['Open', 'In Progress', 'Resolved', 'Closed'];
    $ticketPriorities = ['Low', 'Medium', 'High'];
    
    for ($i = 1; $i <= 10; $i++) {
        $case = BeanFactory::newBean('Cases');
        $case->name = ['Login Issue', 'Feature Request', 'Billing Question', 'Performance Problem', 'Integration Help', 'Data Export', 'User Training', 'Bug Report'][array_rand(['Login Issue', 'Feature Request', 'Billing Question', 'Performance Problem', 'Integration Help', 'Data Export', 'User Training', 'Bug Report'])];
        $case->status = $ticketStatuses[array_rand($ticketStatuses)];
        $case->priority = $ticketPriorities[array_rand($ticketPriorities)];
        $case->type = $ticketTypes[array_rand($ticketTypes)];
        $case->description = 'Customer reported: ' . $case->name;
        $case->resolution = $case->status == 'Closed' ? 'Issue resolved successfully' : '';
        $case->assigned_user_id = $userIds['customer_success'][array_rand($userIds['customer_success'])];
        $case->date_entered = randomDate('-15 days', 'now');
        $case->save();
        
        echo "  Created support ticket: {$case->name} ({$case->status})\n";
    }
    
    // 6. Create Knowledge Base Articles
    echo "\nCreating knowledge base articles...\n";
    
    $kbCategories = ['Getting Started', 'Troubleshooting', 'Best Practices', 'FAQs', 'Integration Guides'];
    $kbArticles = [
        ['How to Get Started with Our CRM', 'A comprehensive guide to setting up and using our CRM system.'],
        ['Troubleshooting Login Issues', 'Common login problems and their solutions.'],
        ['Best Practices for Lead Management', 'Tips and strategies for effective lead management.'],
        ['API Integration Guide', 'Step-by-step guide to integrating with our API.'],
        ['Understanding Customer Health Scores', 'How health scores are calculated and what they mean.'],
        ['Setting Up Email Campaigns', 'Guide to creating and managing email campaigns.'],
        ['Data Import and Export', 'How to import and export your data.'],
        ['User Permissions and Roles', 'Understanding the permission system.'],
        ['Mobile App Features', 'Overview of mobile app capabilities.'],
        ['Reporting and Analytics', 'Creating custom reports and dashboards.']
    ];
    
    foreach ($kbArticles as $i => $article) {
        $kb = BeanFactory::newBean('KBContents');
        if (!$kb) {
            echo "  Knowledge base module not available, creating custom article...\n";
            
            // Use custom table if KB module doesn't exist
            $id = createGuid();
            $slug = strtolower(str_replace(' ', '-', $article[0]));
            $category = $kbCategories[array_rand($kbCategories)];
            
            $db->query("INSERT INTO knowledge_base_articles 
                (id, title, slug, content, summary, category, is_published, view_count, date_entered, date_modified) 
                VALUES 
                ('$id', '{$article[0]}', '$slug', '{$article[1]}', '{$article[1]}', '$category', 1, " . rand(10, 500) . ", NOW(), NOW())");
            
            echo "  Created KB article: {$article[0]}\n";
        }
    }
    
    // 7. Create Form Builder Forms
    echo "\nCreating sample forms...\n";
    
    $forms = [
        [
            'name' => 'Contact Us Form',
            'description' => 'General contact form for website visitors',
            'fields' => json_encode([
                ['type' => 'text', 'name' => 'first_name', 'label' => 'First Name', 'required' => true],
                ['type' => 'text', 'name' => 'last_name', 'label' => 'Last Name', 'required' => true],
                ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
                ['type' => 'text', 'name' => 'company', 'label' => 'Company'],
                ['type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => true]
            ])
        ],
        [
            'name' => 'Demo Request Form',
            'description' => 'Form for requesting product demos',
            'fields' => json_encode([
                ['type' => 'text', 'name' => 'name', 'label' => 'Full Name', 'required' => true],
                ['type' => 'email', 'name' => 'email', 'label' => 'Work Email', 'required' => true],
                ['type' => 'text', 'name' => 'company', 'label' => 'Company', 'required' => true],
                ['type' => 'select', 'name' => 'company_size', 'label' => 'Company Size', 'options' => ['1-10', '11-50', '51-200', '201+']],
                ['type' => 'select', 'name' => 'preferred_time', 'label' => 'Preferred Demo Time', 'options' => ['Morning', 'Afternoon', 'Evening']]
            ])
        ],
        [
            'name' => 'Support Ticket Form',
            'description' => 'Form for submitting support requests',
            'fields' => json_encode([
                ['type' => 'text', 'name' => 'name', 'label' => 'Your Name', 'required' => true],
                ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
                ['type' => 'select', 'name' => 'issue_type', 'label' => 'Issue Type', 'options' => ['Technical', 'Billing', 'Feature Request', 'Other']],
                ['type' => 'text', 'name' => 'subject', 'label' => 'Subject', 'required' => true],
                ['type' => 'textarea', 'name' => 'description', 'label' => 'Description', 'required' => true]
            ])
        ]
    ];
    
    foreach ($forms as $formData) {
        $id = createGuid();
        $embedCode = '<script src="https://crm.example.com/embed/form.js" data-form-id="' . $id . '"></script>';
        
        $db->query("INSERT INTO form_builder_forms 
            (id, name, description, fields, settings, embed_code, is_active, date_entered, date_modified) 
            VALUES 
            ('$id', '{$formData['name']}', '{$formData['description']}', '{$formData['fields']}', '{}', '$embedCode', 1, NOW(), NOW())");
        
        echo "  Created form: {$formData['name']}\n";
    }
    
    // 8. Create Activity History
    echo "\nCreating activity history...\n";
    
    // Calls
    for ($i = 1; $i <= 15; $i++) {
        $call = BeanFactory::newBean('Calls');
        $call->name = ['Follow-up call', 'Discovery call', 'Check-in call', 'Demo call', 'Closing call'][array_rand(['Follow-up call', 'Discovery call', 'Check-in call', 'Demo call', 'Closing call'])];
        $call->status = 'Held';
        $call->direction = ['Inbound', 'Outbound'][array_rand(['Inbound', 'Outbound'])];
        $call->duration_hours = 0;
        $call->duration_minutes = rand(5, 60);
        $call->date_start = randomDate('-30 days', 'now');
        $call->description = 'Call notes: Discussed product features and pricing';
        $call->assigned_user_id = $userIds['sales_rep'][array_rand($userIds['sales_rep'])];
        $call->save();
        
        // Link to contact
        if (!empty($contacts)) {
            $contact = $contacts[array_rand($contacts)];
            $call->load_relationship('contacts');
            $call->contacts->add($contact->id);
        }
    }
    echo "  Created 15 call records\n";
    
    // Meetings
    for ($i = 1; $i <= 10; $i++) {
        $meeting = BeanFactory::newBean('Meetings');
        $meeting->name = ['Product Demo', 'Strategy Session', 'Quarterly Review', 'Implementation Planning', 'Training Session'][array_rand(['Product Demo', 'Strategy Session', 'Quarterly Review', 'Implementation Planning', 'Training Session'])];
        $meeting->status = ['Planned', 'Held'][array_rand(['Planned', 'Held'])];
        $meeting->duration_hours = rand(1, 2);
        $meeting->duration_minutes = [0, 30][array_rand([0, 30])];
        $meeting->date_start = randomDate('-20 days', '+20 days');
        $meeting->location = ['Conference Room A', 'Zoom', 'Teams', 'Client Office'][array_rand(['Conference Room A', 'Zoom', 'Teams', 'Client Office'])];
        $meeting->description = 'Meeting agenda and notes';
        $meeting->assigned_user_id = $userIds['sales_rep'][array_rand($userIds['sales_rep'])];
        $meeting->save();
        
        // Link to contact
        if (!empty($contacts)) {
            $contact = $contacts[array_rand($contacts)];
            $meeting->load_relationship('contacts');
            $meeting->contacts->add($contact->id);
        }
    }
    echo "  Created 10 meeting records\n";
    
    // 9. Create Visitor Tracking Data
    echo "\nCreating visitor tracking data...\n";
    
    $visitorIds = [];
    for ($i = 1; $i <= 10; $i++) {
        $visitorId = 'visitor_' . md5(uniqid());
        $leadId = $i <= 5 ? $leads[$i - 1]->id : null; // Link first 5 visitors to leads
        
        $db->query("INSERT INTO activity_tracking_visitors 
            (id, visitor_id, lead_id, first_visit, last_visit, total_visits, total_page_views, total_time_spent, 
             browser, device_type, referrer_source, engagement_score, date_entered, date_modified) 
            VALUES 
            ('" . createGuid() . "', '$visitorId', " . ($leadId ? "'$leadId'" : "NULL") . ", 
             '" . randomDate('-30 days', '-10 days') . "', '" . randomDate('-5 days', 'now') . "', 
             " . rand(1, 10) . ", " . rand(5, 50) . ", " . rand(60, 3600) . ", 
             'Chrome', 'Desktop', 'Google', " . rand(20, 90) . ", NOW(), NOW())");
        
        $visitorIds[] = $visitorId;
    }
    echo "  Created 10 visitor tracking records\n";
    
    // 10. Create AI Chat Conversations
    echo "\nCreating AI chat conversations...\n";
    
    for ($i = 1; $i <= 5; $i++) {
        $convId = createGuid();
        $visitorId = $visitorIds[array_rand($visitorIds)];
        
        $db->query("INSERT INTO ai_chat_conversations 
            (id, visitor_id, lead_id, status, started_at, ended_at, date_entered, date_modified) 
            VALUES 
            ('$convId', '$visitorId', NULL, 'completed', 
             '" . randomDate('-10 days', '-1 day') . "', '" . randomDate('-10 days', '-1 day') . "', 
             NOW(), NOW())");
        
        // Add some messages
        $messages = [
            ['AI', 'Hello! How can I help you today?'],
            ['Visitor', 'I need information about your pricing'],
            ['AI', 'I\'d be happy to help you with pricing information. What specific features are you interested in?'],
            ['Visitor', 'We need CRM for a team of 50 people'],
            ['AI', 'For a team of 50 people, I recommend our Business plan. Would you like to schedule a demo?']
        ];
        
        foreach ($messages as $j => $msg) {
            $db->query("INSERT INTO ai_chat_messages 
                (id, conversation_id, sender_type, message, sent_at, date_entered, date_modified) 
                VALUES 
                ('" . createGuid() . "', '$convId', '{$msg[0]}', '{$msg[1]}', 
                 '" . randomDate('-10 days', '-1 day') . "', NOW(), NOW())");
        }
    }
    echo "  Created 5 AI chat conversations\n";
    
    echo "\n=== Seed data creation complete! ===\n";
    echo "Created:\n";
    echo "- 4 users (sales and customer success reps)\n";
    echo "- 20 leads\n";
    echo "- 15 contacts\n";
    echo "- 10 opportunities\n";
    echo "- 10 support tickets\n";
    echo "- 10 knowledge base articles\n";
    echo "- 3 forms\n";
    echo "- 25 activities (calls and meetings)\n";
    echo "- 10 visitor tracking records\n";
    echo "- 5 AI chat conversations\n";
    echo "\nDefault password for all users: password123\n";
    
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}