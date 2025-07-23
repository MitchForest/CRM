### 4. Demo Data Generation

#### 4.1 Create Comprehensive Demo Data Script
`custom/install/generate_demo_data.php`:
```php
<?php
require_once('include/utils.php');

class DemoDataGenerator
{
    private $industries = ['Technology', 'Healthcare', 'Finance', 'Retail', 'Manufacturing'];
    private $leadSources = ['Website', 'Referral', 'Social Media', 'Email', 'Chat', 'Partner'];
    private $titles = ['CEO', 'CTO', 'VP Sales', 'Director of IT', 'Manager', 'Developer'];
    
    public function generateAll()
    {
        echo "🚀 Starting demo data generation...\n\n";
        
        $this->generateLeads(100);
        $this->generateAccounts(30);
        $this->generateOpportunities(50);
        $this->generateCases(40);
        $this->generateActivities();
        $this->generateForms();
        $this->generateKnowledgeBase();
        $this->generateWebsiteSessions();
        $this->runAIScoring();
        $this->calculateHealthScores();
        
        echo "\n✅ Demo data generation complete!\n";
    }
    
    private function generateLeads($count)
    {
        echo "Creating $count leads...\n";
        
        require_once('modules/Leads/Lead.php');
        global $current_user;
        
        for ($i = 0; $i < $count; $i++) {
            $lead = new Lead();
            
            $lead->first_name = $this->getRandomFirstName();
            $lead->last_name = $this->getRandomLastName();
            $lead->email1 = strtolower($lead->first_name . '.' . $lead->last_name . '@' . $this->getRandomDomain());
            $lead->phone_mobile = $this->getRandomPhone();
            $lead->account_name = $this->getRandomCompanyName();
            $lead->title = $this->getRandomElement($this->titles);
            $lead->lead_source = $this->getRandomElement($this->leadSources);
            $lead->status = $this->getRandomElement(['New', 'Contacted', 'Qualified', 'Lost']);
            $lead->industry = $this->getRandomElement($this->industries);
            $lead->employees = $this->getRandomElement(['1-10', '11-50', '51-200', '201-500', '500+']);
            $lead->website = 'https://www.' . strtolower(str_replace(' ', '', $lead->account_name)) . '.com';
            $lead->description = $this->getRandomDescription('lead');
            $lead->assigned_user_id = $current_user->id;
            
            // Set random AI score for some leads
            if (rand(1, 100) > 30) {
                $lead->ai_score = rand(20, 95);
                $lead->ai_score_date = date('Y-m-d H:i:s', strtotime('-' . rand(0, 7) . ' days'));
                $lead->ai_score_factors = json_encode([
                    'company_size' => rand(10, 20),
                    'industry_match' => rand(10, 15),
                    'behavior_score' => rand(15, 25),
                    'engagement' => rand(10, 20),
                    'budget_signals' => rand(10, 20),
                ]);
            }
            
            $lead->save();
            
            if (($i + 1) % 10 == 0) echo "  Created " . ($i + 1) . " leads\n";
        }
    }
    
    private function generateAccounts($count)
    {
        echo "\nCreating $count accounts...\n";
        
        require_once('modules/Accounts/Account.php');
        global $current_user;
        
        for ($i = 0; $i < $count; $i++) {
            $account = new Account();
            
            $account->name = $this->getRandomCompanyName();
            $account->phone_office = $this->getRandomPhone();
            $account->website = 'https://www.' . strtolower(str_replace(' ', '', $account->name)) . '.com';
            $account->industry = $this->getRandomElement($this->industries);
            $account->annual_revenue = rand(100000, 50000000);
            $account->employees = $this->getRandomElement(['1-10', '11-50', '51-200', '201-500', '500+', '1000+']);
            $account->account_type = $this->getRandomElement(['Customer', 'Prospect', 'Partner']);
            $account->description = $this->getRandomDescription('account');
            $account->assigned_user_id = $current_user->id;
            
            // Set customer-specific fields
            if ($account->account_type == 'Customer') {
                $account->mrr = rand(500, 25000);
                $account->customer_since = date('Y-m-d', strtotime('-' . rand(30, 730) . ' days'));
                $account->health_score = rand(40, 95);
            }
            
            $account->save();
            
            // Create contacts for account
            $this->generateContactsForAccount($account->id, rand(1, 5));
        }
    }
    
    private function generateContactsForAccount($accountId, $count)
    {
        require_once('modules/Contacts/Contact.php');
        global $current_user;
        
        for ($i = 0; $i < $count; $i++) {
            $contact = new Contact();
            
            $contact->first_name = $this->getRandomFirstName();
            $contact->last_name = $this->getRandomLastName();
            $contact->email1 = strtolower($contact->first_name . '.' . $contact->last_name . '@example.com');
            $contact->phone_work = $this->getRandomPhone();
            $contact->phone_mobile = $this->getRandomPhone();
            $contact->title = $this->getRandomElement($this->titles);
            $contact->department = $this->getRandomElement(['Sales', 'IT', 'Marketing', 'Operations', 'Finance']);
            $contact->primary_address_street = rand(100, 9999) . ' ' . $this->getRandomElement(['Main St', 'Market St', 'Park Ave', 'Broadway']);
            $contact->primary_address_city = $this->getRandomElement(['New York', 'San Francisco', 'Chicago', 'Boston', 'Austin']);
            $contact->primary_address_state = $this->getRandomElement(['NY', 'CA', 'IL', 'MA', 'TX']);
            $contact->primary_address_postalcode = rand(10000, 99999);
            $contact->assigned_user_id = $current_user->id;
            
            $contact->save();
            
            // Link to account
            $contact->load_relationship('accounts');
            $contact->accounts->add($accountId);
        }
    }
    
    private function generateOpportunities($count)
    {
        echo "\nCreating $count opportunities...\n";
        
        require_once('modules/Opportunities/Opportunity.php');
        global $current_user, $db;
        
        // Get account IDs
        $accountQuery = "SELECT id FROM accounts WHERE deleted = 0 ORDER BY RAND() LIMIT $count";
        $accountResult = $db->query($accountQuery);
        $accountIds = [];
        while ($row = $db->fetchByAssoc($accountResult)) {
            $accountIds[] = $row['id'];
        }
        
        $stages = ['Qualification', 'Needs Analysis', 'Value Proposition', 'Decision Makers', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost'];
        
        for ($i = 0; $i < $count; $i++) {
            $opportunity = new Opportunity();
            
            $opportunity->name = $this->getRandomElement(['Enterprise', 'Pro', 'Starter']) . ' License - ' . $this->getRandomCompanyName();
            $opportunity->account_id = $accountIds[$i % count($accountIds)];
            $opportunity->sales_stage = $this->getRandomElement($stages);
            $opportunity->amount = rand(5000, 500000);
            $opportunity->date_closed = date('Y-m-d', strtotime('+' . rand(-30, 90) . ' days'));
            $opportunity->lead_source = $this->getRandomElement($this->leadSources);
            $opportunity->next_step = $this->getRandomElement([
                'Schedule demo',
                'Send proposal',
                'Follow up on proposal',
                'Contract negotiation',
                'Technical evaluation',
                'Executive approval',
            ]);
            $opportunity->description = $this->getRandomDescription('opportunity');
            $opportunity->competitors = implode(', ', array_rand(array_flip(['Salesforce', 'HubSpot', 'Pipedrive', 'Monday', 'Zoho']), rand(1, 3)));
            $opportunity->subscription_type = $this->getRandomElement(['monthly', 'annual', 'multi_year']);
            $opportunity->assigned_user_id = $current_user->id;
            
            $opportunity->save();
            
            if (($i + 1) % 10 == 0) echo "  Created " . ($i + 1) . " opportunities\n";
        }
    }
    
    private function generateCases($count)
    {
        echo "\nCreating $count cases...\n";
        
        require_once('modules/Cases/Case.php');
        global $current_user, $db;
        
        // Get account IDs
        $accountQuery = "SELECT id, name FROM accounts WHERE account_type = 'Customer' AND deleted = 0";
        $accountResult = $db->query($accountQuery);
        $accounts = [];
        while ($row = $db->fetchByAssoc($accountResult)) {
            $accounts[] = $row;
        }
        
        for ($i = 0; $i < $count; $i++) {
            $case = new aCase();
            
            $account = $this->getRandomElement($accounts);
            $case->account_id = $account['id'];
            $case->name = $this->getRandomElement([
                'Login issues',
                'Feature request',
                'Performance problems',
                'Data export needed',
                'Integration help',
                'Billing question',
                'Bug report',
                'Training request',
            ]);
            $case->status = $this->getRandomElement(['New', 'Assigned', 'In Progress', 'Pending Input', 'Closed']);
            $case->priority = $this->getRandomElement(['P1', 'P2', 'P3']);
            $case->type = $this->getRandomElement(['bug', 'feature_request', 'technical_support', 'account_issue', 'training']);
            $case->description = $this->getRandomDescription('case');
            $case->severity = $this->getRandomElement(['critical', 'major', 'minor']);
            $case->product_version = '2.5.' . rand(0, 3);
            $case->environment = $this->getRandomElement(['production', 'staging', 'development']);
            $case->assigned_user_id = $current_user->id;
            
            $case->save();
        }
    }
    
    private function generateActivities()
    {
        echo "\nCreating activities...\n";
        
        global $db, $current_user;
        
        // Get leads and opportunities
        $leadIds = [];
        $leadQuery = "SELECT id FROM leads WHERE deleted = 0 LIMIT 50";
        $leadResult = $db->query($leadQuery);
        while ($row = $db->fetchByAssoc($leadResult)) {
            $leadIds[] = $row['id'];
        }
        
        $oppIds = [];
        $oppQuery = "SELECT id FROM opportunities WHERE deleted = 0 LIMIT 30";
        $oppResult = $db->query($oppQuery);
        while ($row = $db->fetchByAssoc($oppResult)) {
            $oppIds[] = $row['id'];
        }
        
        // Create Calls
        require_once('modules/Calls/Call.php');
        for ($i = 0; $i < 30; $i++) {
            $call = new Call();
            $call->name = $this->getRandomElement(['Discovery Call', 'Follow-up Call', 'Demo Call', 'Check-in Call']);
            $call->status = $this->getRandomElement(['Planned', 'Held']);
            $call->direction = $this->getRandomElement(['Inbound', 'Outbound']);
            $call->date_start = date('Y-m-d H:i:s', strtotime(rand(-30, 7) . ' days ' . rand(9, 17) . ':' . rand(0, 59) . ':00'));
            $call->duration_hours = 0;
            $call->duration_minutes = $this->getRandomElement([15, 30, 45, 60]);
            $call->call_type = $this->getRandomElement(['discovery', 'demo', 'follow_up', 'support']);
            $call->description = $this->getRandomDescription('call');
            $call->assigned_user_id = $current_user->id;
            
            // Link to lead or opportunity
            if (rand(0, 1)) {
                $call->parent_type = 'Leads';
                $call->parent_id = $this->getRandomElement($leadIds);
            } else {
                $call->parent_type = 'Opportunities';
                $call->parent_id = $this->getRandomElement($oppIds);
            }
            
            $call->save();
        }
        
        // Create Meetings
        require_once('modules/Meetings/Meeting.php');
        for ($i = 0; $i < 20; $i++) {
            $meeting = new Meeting();
            $meeting->name = $this->getRandomElement(['Product Demo', 'Discovery Meeting', 'Contract Review', 'Technical Discussion']);
            $meeting->status = $this->getRandomElement(['Planned', 'Held']);
            $meeting->date_start = date('Y-m-d H:i:s', strtotime(rand(-30, 14) . ' days ' . rand(9, 17) . ':00:00'));
            $meeting->duration_hours = rand(0, 1);
            $meeting->duration_minutes = $meeting->duration_hours ? 0 : $this->getRandomElement([30, 45]);
            $meeting->location = $this->getRandomElement(['Zoom', 'Conference Room A', 'Customer Office', 'Google Meet']);
            $meeting->meeting_type = $this->getRandomElement(['demo', 'discovery', 'proposal', 'qbr']);
            $meeting->description = $this->getRandomDescription('meeting');
            $meeting->assigned_user_id = $current_user->id;
            
            if (rand(0, 1)) {
                $meeting->parent_type = 'Opportunities';
                $meeting->parent_id = $this->getRandomElement($oppIds);
            }
            
            $meeting->save();
        }
        
        // Create Tasks
        require_once('modules/Tasks/Task.php');
        for ($i = 0; $i < 40; $i++) {
            $task = new Task();
            $task->name = $this->getRandomElement([
                'Send proposal',
                'Follow up on demo',
                'Update CRM records',
                'Research competitor',
                'Prepare presentation',
                'Send contract',
            ]);
            $task->status = $this->getRandomElement(['Not Started', 'In Progress', 'Completed', 'Deferred']);
            $task->priority = $this->getRandomElement(['High', 'Medium', 'Low']);
            $task->date_due = date('Y-m-d', strtotime(rand(-7, 14) . ' days'));
            $task->task_type = $this->getRandomElement(['follow_up', 'proposal', 'research', 'internal']);
            $task->description = $this->getRandomDescription('task');
            $task->assigned_user_id = $current_user->id;
            
            if (rand(0, 1)) {
                $task->parent_type = 'Opportunities';
                $task->parent_id = $this->getRandomElement($oppIds);
            }
            
            $task->save();
        }
        
        echo "  Created 30 calls, 20 meetings, 40 tasks\n";
    }
    
    private function generateForms()
    {
        echo "\nCreating forms...\n";
        
        global $db, $current_user;
        
        $forms = [
            [
                'name' => 'Contact Us',
                'fields' => [
                    ['id' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                    ['id' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
                    ['id' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                    ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => false],
                    ['id' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true],
                ],
            ],
            [
                'name' => 'Demo Request',
                'fields' => [
                    ['id' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
                    ['id' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
                    ['id' => 'email', 'type' => 'email', 'label' => 'Business Email', 'required' => true],
                    ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => true],
                    ['id' => 'phone', 'type' => 'tel', 'label' => 'Phone', 'required' => false],
                    ['id' => 'employees', 'type' => 'select', 'label' => 'Company Size', 'required' => true, 
                     'options' => [
                         ['label' => '1-10', 'value' => '1-10'],
                         ['label' => '11-50', 'value' => '11-50'],
                         ['label' => '51-200', 'value' => '51-200'],
                         ['label' => '201-500', 'value' => '201-500'],
                         ['label' => '500+', 'value' => '500+'],
                     ]],
                ],
            ],
            [
                'name' => 'Newsletter Signup',
                'fields' => [
                    ['id' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true],
                    ['id' => 'interests', 'type' => 'checkbox', 'label' => 'I\'m interested in product updates', 'required' => false],
                ],
            ],
        ];
        
        foreach ($forms as $formData) {
            $id = create_guid();
            $embedCode = "<script src=\"https://yourcrm.com/forms/embed.js\"></script>\n<div data-form-id=\"$id\" data-form-container></div>";
            
            $query = "INSERT INTO form_builder_forms 
                     (id, name, fields, settings, embed_code, created_by, date_created, date_modified)
                     VALUES (
                        '$id',
                        '{$formData['name']}',
                        '" . json_encode($formData['fields']) . "',
                        '" . json_encode([
                            'submitButtonText' => 'Submit',
                            'successMessage' => 'Thank you for your submission!',
                            'notificationEmail' => $current_user->email1,
                            'styling' => [
                                'theme' => 'light',
                                'primaryColor' => '#3b82f6',
                                'fontFamily' => 'Inter',
                            ],
                        ]) . "',
                        '{$db->quote($embedCode)}',
                        '{$current_user->id}',
                        NOW(),
                        NOW()
                     )";
            
            $db->query($query);
            
            // Generate some submissions
            $this->generateFormSubmissions($id, rand(5, 20));
        }
        
        echo "  Created 3 forms with submissions\n";
    }
    
    private function generateFormSubmissions($formId, $count)
    {
        global $db;
        
        for ($i = 0; $i < $count; $i++) {
            $data = [
                'first_name' => $this->getRandomFirstName(),
                'last_name' => $this->getRandomLastName(),
                'email' => strtolower($this->getRandomFirstName() . '@' . $this->getRandomDomain()),
                'company' => $this->getRandomCompanyName(),
                'message' => 'I am interested in learning more about your product.',
            ];
            
            $id = create_guid();
            
            $query = "INSERT INTO form_submissions 
                     (id, form_id, data, ip_address, user_agent, date_submitted)
                     VALUES (
                        '$id',
                        '$formId',
                        '" . json_encode($data) . "',
                        '" . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . "',
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        '" . date('Y-m-d H:i:s', strtotime('-' . rand(0, 30) . ' days')) . "'
                     )";
            
            $db->query($query);
        }
    }
    
    private function generateKnowledgeBase()
    {
        echo "\nCreating knowledge base...\n";
        
        global $db, $current_user;
        
        // Create categories
        $categories = [
            ['name' => 'Getting Started', 'slug' => 'getting-started', 'icon' => 'book-open'],
            ['name' => 'Features', 'slug' => 'features', 'icon' => 'layers'],
            ['name' => 'Troubleshooting', 'slug' => 'troubleshooting', 'icon' => 'tool'],
            ['name' => 'API Documentation', 'slug' => 'api-docs', 'icon' => 'code'],
        ];
        
        $categoryIds = [];
        foreach ($categories as $cat) {
            $id = create_guid();
            $categoryIds[$cat['slug']] = $id;
            
            $query = "INSERT INTO kb_categories 
                     (id, name, slug, description, icon, created_by, date_created, date_modified)
                     VALUES (
                        '$id',
                        '{$cat['name']}',
                        '{$cat['slug']}',
                        'Articles about {$cat['name']}',
                        '{$cat['icon']}',
                        '{$current_user->id}',
                        NOW(),
                        NOW()
                     )";
            
            $db->query($query);
        }
        
        // Create articles
        $articles = [
            [
                'title' => 'How to Get Started with AI CRM',
                'category' => 'getting-started',
                'content' => '<h2>Welcome to AI CRM</h2><p>This guide will help you get started...</p>',
            ],
            [
                'title' => 'Understanding AI Lead Scoring',
                'category' => 'features',
                'content' => '<h2>AI Lead Scoring</h2><p>Our AI analyzes multiple factors...</p>',
            ],
            [
                'title' => 'Troubleshooting Login Issues',
                'category' => 'troubleshooting',
                'content' => '<h2>Login Troubleshooting</h2><p>If you cannot log in...</p>',
            ],
            [
                'title' => 'API Authentication Guide',
                'category' => 'api-docs',
                'content' => '<h2>API Authentication</h2><p>To authenticate with our API...</p>',
            ],
        ];
        
        foreach ($articles as $article) {
            $id = create_guid();
            $slug = strtolower(str_replace(' ', '-', $article['title']));
            
            $query = "INSERT INTO kb_articles 
                     (id, title, slug, content, excerpt, category_id, tags, is_public, author_id, date_created, date_modified)
                     VALUES (
                        '$id',
                        '{$article['title']}',
                        '$slug',
                        '{$db->quote($article['content'])}',
                        'Learn about {$article['title']}',
                        '{$categoryIds[$article['category']]}',
                        '[]',
                        1,
                        '{$current_user->id}',
                        NOW(),
                        NOW()
                     )";
            
            $db->query($query);
        }
        
        echo "  Created 4 categories and 4 articles\n";
    }
    
    private function generateWebsiteSessions()
    {
        echo "\nCreating website activity data...\n";
        
        global $db;
        
        // Get some lead IDs
        $leadIds = [];
        $leadQuery = "SELECT id FROM leads WHERE deleted = 0 LIMIT 30";
        $leadResult = $db->query($leadQuery);
        while ($row = $db->fetchByAssoc($leadResult)) {
            $leadIds[] = $row['id'];
        }
        
        // Create sessions
        for ($i = 0; $i < 100; $i++) {
            $sessionId = create_guid();
            $visitorId = 'visitor_' . uniqid();
            $leadId = (rand(1, 100) > 70 && !empty($leadIds)) ? $this->getRandomElement($leadIds) : null;
            
            $pages = [
                ['url' => '/', 'title' => 'Homepage'],
                ['url' => '/features', 'title' => 'Features'],
                ['url' => '/pricing', 'title' => 'Pricing'],
                ['url' => '/demo', 'title' => 'Request Demo'],
                ['url' => '/about', 'title' => 'About Us'],
                ['url' => '/contact', 'title' => 'Contact'],
            ];
            
            $sessionPages = [];
            $pageCount = rand(1, 5);
            for ($j = 0; $j < $pageCount; $j++) {
                $page = $this->getRandomElement($pages);
                $sessionPages[] = array_merge($page, [
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-' . rand(0, 30) . ' days')),
                    'time_on_page' => rand(10, 300),
                ]);
            }
            
            $query = "INSERT INTO website_sessions 
                     (id, visitor_id, lead_id, ip_address, user_agent, referrer, landing_page, pages_viewed, total_time, is_active, last_activity, date_created)
                     VALUES (
                        '$sessionId',
                        '$visitorId',
                        " . ($leadId ? "'$leadId'" : 'NULL') . ",
                        '" . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . "',
                        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        '" . $this->getRandomElement(['https://google.com', 'https://linkedin.com', 'direct', '']) . "',
                        '{$sessionPages[0]['url']}',
                        '" . json_encode($sessionPages) . "',
                        " . rand(60, 1800) . ",
                        " . (rand(1, 100) > 90 ? 1 : 0) . ",
                        '" . date('Y-m-d H:i:s', strtotime('-' . rand(0, 2) . ' hours')) . "',
                        '" . date('Y-m-d H:i:s', strtotime('-' . rand(0, 30) . ' days')) . "'
                     )";
            
            $db->query($query);
        }
        
        echo "  Created 100 website sessions\n";
    }
    
    private function runAIScoring()
    {
        echo "\nRunning AI scoring on leads...\n";
        
        global $db;
        
        // Get unscored leads
        $query = "SELECT id FROM leads WHERE ai_score IS NULL AND deleted = 0 LIMIT 50";
        $result = $db->query($query);
        
        $count = 0;
        while ($row = $db->fetchByAssoc($result)) {
            // Simulate AI scoring
            $score = rand(20, 95);
            $factors = [
                'company_size' => rand(10, 20),
                'industry_match' => rand(10, 15),
                'behavior_score' => rand(15, 25),
                'engagement' => rand(10, 20),
                'budget_signals' => rand(10, 20),
            ];
            
            $updateQuery = "UPDATE leads SET 
                           ai_score = $score,
                           ai_score_date = NOW(),
                           ai_score_factors = '" . json_encode($factors) . "'
                           WHERE id = '{$row['id']}'";
            
            $db->query($updateQuery);
            $count++;
        }
        
        echo "  Scored $count leads\n";
    }
    
    private function calculateHealthScores()
    {
        echo "\nCalculating health scores for accounts...\n";
        
        $healthService = new \Custom\Services\HealthScoreService();
        
        global $db;
        $query = "SELECT id FROM accounts WHERE account_type = 'Customer' AND deleted = 0 LIMIT 10";
        $result = $db->query($query);
        
        $count = 0;
        while ($row = $db->fetchByAssoc($result)) {
            try {
                $healthService->calculateHealthScore($row['id']);
                $count++;
            } catch (Exception $e) {
                // Skip on error
            }
        }
        
        echo "  Calculated health scores for $count accounts\n";
    }
    
    // Helper methods
    private function getRandomFirstName()
    {
        $names = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'Robert', 'Lisa', 'James', 'Jennifer',
                  'William', 'Mary', 'Richard', 'Patricia', 'Thomas', 'Linda', 'Charles', 'Barbara', 'Daniel', 'Elizabeth'];
        return $this->getRandomElement($names);
    }
    
    private function getRandomLastName()
    {
        $names = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
                  'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'];
        return $this->getRandomElement($names);
    }
    
    private function getRandomCompanyName()
    {
        $prefixes = ['Tech', 'Global', 'Digital', 'Smart', 'Future', 'Next', 'Cloud', 'Data', 'Cyber', 'Quantum'];
        $suffixes = ['Solutions', 'Systems', 'Corp', 'Inc', 'Labs', 'Works', 'Group', 'Technologies', 'Innovations', 'Dynamics'];
        return $this->getRandomElement($prefixes) . ' ' . $this->getRandomElement($suffixes);
    }
    
    private function getRandomDomain()
    {
        $domains = ['example.com', 'techcorp.com', 'business.io', 'company.net', 'enterprise.org'];
        return $this->getRandomElement($domains);
    }
    
    private function getRandomPhone()
    {
        return '(' . rand(200, 999) . ') ' . rand(200, 999) . '-' . rand(1000, 9999);
    }
    
    private function getRandomElement($array)
    {
        return $array[array_rand($array)];
    }
    
    private function getRandomDescription($type)
    {
        $templates = [
            'lead' => [
                'Interested in our enterprise solution for their growing team.',
                'Looking for a CRM to replace their current system.',
                'Evaluating multiple vendors for their sales automation needs.',
                'Requested information about pricing and features.',
            ],
            'account' => [
                'Leading provider of technology solutions in their market.',
                'Fast-growing company with operations in multiple regions.',
                'Established business looking to modernize their processes.',
                'Strategic partner with significant growth potential.',
            ],
            'opportunity' => [
                'Customer is evaluating our solution against competitors.',
                'Strong interest in AI features and automation capabilities.',
                'Budget approved, pending final technical evaluation.',
                'Expansion opportunity for existing customer.',
            ],
            'case' => [
                'Customer experiencing intermittent issues with the system.',
                'Request for assistance with integration setup.',
                'Feature working as designed, provided workaround.',
                'Escalated to engineering team for further investigation.',
            ],
            'call' => [
                'Discussed product capabilities and pricing options.',
                'Follow-up on demo, answered technical questions.',
                'Addressed concerns about implementation timeline.',
                'Reviewed contract terms and next steps.',
            ],
            'meeting' => [
                'Demonstrated key features and use cases.',
                'Discussed implementation plan and timeline.',
                'Reviewed proposal and addressed questions.',
                'Technical deep dive with customer IT team.',
            ],
            'task' => [
                'Prepare customized demo for customer requirements.',
                'Follow up on proposal sent last week.',
                'Research competitor pricing and features.',
                'Update opportunity with latest information.',
            ],
        ];
        
        return $this->getRandomElement($templates[$type] ?? ['No description available.']);
    }
}

// Run the generator
$generator = new DemoDataGenerator();
$generator->generateAll();
```

### 5. Public API Endpoints

#### 5.1 Create Public Endpoints for Marketing Site
`custom/api/v8/controllers/PublicController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class PublicController extends BaseController
{
    /**
     * Get public knowledge base articles
     */
    public function getPublicArticles(Request $request, Response $response, array $args)
    {
        global $db;
        
        $categorySlug = $request->getQueryParam('category');
        $search = $request->getQueryParam('search');
        $limit = min($request->getQueryParam('limit', 20), 100);
        $page = max($request->getQueryParam('page', 1), 1);
        $offset = ($page - 1) * $limit;
        
        $where = ["a.is_public = 1", "a.deleted = 0"];
        
        if ($categorySlug) {
            $where[] = "c.slug = '{$db->quote($categorySlug)}'";
        }
        
        if ($search) {
            $searchEscaped = $db->quote($search);
            $where[] = "(a.title LIKE '%$searchEscaped%' OR a.content LIKE '%$searchEscaped%')";
        }
        
        $whereClause = implode(' AND ', $where);
        
        $query = "SELECT a.id, a.title, a.slug, a.excerpt, a.views,
                 c.name as category_name, c.slug as category_slug
                 FROM kb_articles a
                 LEFT JOIN kb_categories c ON a.category_id = c.id
                 WHERE $whereClause
                 ORDER BY a.is_featured DESC, a.views DESC
                 LIMIT $limit OFFSET $offset";
        
        $result = $db->query($query);
        $articles = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $articles[] = $row;
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                      FROM kb_articles a
                      LEFT JOIN kb_categories c ON a.category_id = c.id
                      WHERE $whereClause";
        $countResult = $db->query($countQuery);
        $total = $db->fetchByAssoc($countResult)['total'];
        
        return $response->withJson([
            'data' => $articles,
            'meta' => [
                'total' => (int)$total,
                'page' => (int)$page,
                'per_page' => (int)$limit,
                'total_pages' => ceil($total / $limit),
            ],
        ]);
    }
    
    /**
     * Get public article by slug
     */
    public function getPublicArticle(Request $request, Response $response, array $args)
    {
        global $db;
        
        $slug = $args['slug'];
        
        $query = "SELECT a.*, c.name as category_name, c.slug as category_slug
                 FROM kb_articles a
                 LEFT JOIN kb_categories c ON a.category_id = c.id
                 WHERE a.slug = '$slug' 
                 AND a.is_public = 1 
                 AND a.deleted = 0";
        
        $result = $db->query($query);
        $article = $db->fetchByAssoc($result);
        
        if (!$article) {
            return $response->withJson(['error' => 'Article not found'], 404);
        }
        
        // Track view
        $updateQuery = "UPDATE kb_articles SET views = views + 1 WHERE id = '{$article['id']}'";
        $db->query($updateQuery);
        
        // Get related articles
        $relatedQuery = "SELECT id, title, slug, excerpt 
                        FROM kb_articles 
                        WHERE category_id = '{$article['category_id']}'
                        AND id != '{$article['id']}'
                        AND is_public = 1
                        AND deleted = 0
                        ORDER BY views DESC
                        LIMIT 5";
        
        $relatedResult = $db->query($relatedQuery);
        $related = [];
        
        while ($row = $db->fetchByAssoc($relatedResult)) {
            $related[] = $row;
        }
        
        $article['related_articles'] = $related;
        
        return $response->withJson($article);
    }
    
    /**
     * Get KB categories
     */
    public function getPublicCategories(Request $request, Response $response, array $args)
    {
        global $db;
        
        $query = "SELECT c.*, 
                 (SELECT COUNT(*) FROM kb_articles a 
                  WHERE a.category_id = c.id 
                  AND a.is_public = 1 
                  AND a.deleted = 0) as article_count
                 FROM kb_categories c
                 WHERE c.deleted = 0
                 ORDER BY c.sort_order, c.name";
        
        $result = $db->query($query);
        $categories = [];
        
        while ($row = $db->fetchByAssoc($result)) {
            $categories[] = $row;
        }
        
        return $response->withJson($categories);
    }
    
    /**
     * Public form submission
     */
    public function submitPublicForm(Request $request, Response $response, array $args)
    {
        global $db;
        
        $formId = $args['id'];
        $data = $request->getParsedBody();
        
        // Get form
        $formQuery = "SELECT * FROM form_builder_forms 
                     WHERE id = '$formId' 
                     AND is_active = 1 
                     AND deleted = 0";
        $formResult = $db->query($formQuery);
        $form = $db->fetchByAssoc($formResult);
        
        if (!$form) {
            return $response->withJson(['error' => 'Form not found'], 404);
        }
        
        // Basic validation
        $fields = json_decode($form['fields'], true);
        $errors = [];
        
        foreach ($fields as $field) {
            if ($field['required'] && empty($data[$field['id']])) {
                $errors[$field['id']] = "{$field['label']} is required";
            }
        }
        
        if (!empty($errors)) {
            return $response->withJson(['errors' => $errors], 400);
        }
        
        // Save submission
        $submissionId = create_guid();
        
        $query = "INSERT INTO form_submissions 
                 (id, form_id, data, ip_address, user_agent, referrer, date_submitted)
                 VALUES (
                    '$submissionId',
                    '$formId',
                    '{$db->quote(json_encode($data))}',
                    '{$_SERVER['REMOTE_ADDR']}',
                    '{$db->quote($_SERVER['HTTP_USER_AGENT'] ?? '')}',
                    '{$db->quote($_SERVER['HTTP_REFERER'] ?? '')}',
                    NOW()
                 )";
        
        $db->query($query);
        
        // Update submission count
        $db->query("UPDATE form_builder_forms SET submissions_count = submissions_count + 1 WHERE id = '$formId'");
        
        // Create lead if email provided
        if (!empty($data['email'])) {
            $this->createLeadFromSubmission($data, $formId);
        }
        
        // Send notification
        $settings = json_decode($form['settings'], true);
        if (!empty($settings['notificationEmail'])) {
            $this->sendFormNotification($settings['notificationEmail'], $form['name'], $data);
        }
        
        return $response->withJson([
            'success' => true,
            'message' => $settings['successMessage'] ?? 'Thank you for your submission!',
        ]);
    }
    
    private function createLeadFromSubmission($data, $formId)
    {
        $lead = \BeanFactory::newBean('Leads');
        
        $lead->first_name = $data['first_name'] ?? '';
        $lead->last_name = $data['last_name'] ?? '';
        $lead->email1 = $data['email'] ?? '';
        $lead->phone_mobile = $data['phone'] ?? '';
        $lead->account_name = $data['company'] ?? '';
        $lead->title = $data['title'] ?? '';
        $lead->lead_source = 'Web Form';
        $lead->status = 'New';
        $lead->description = "Form submission from: $formId\n" . json_encode($data);
        
        $lead->save();
        
        // Trigger AI scoring
        $alertService = new \Custom\Services\AlertService();
        $alertService->processAlertRules('form_submission', [
            'form_id' => $formId,
            'lead_id' => $lead->id,
            'lead_name' => $lead->first_name . ' ' . $lead->last_name,
            'email' => $lead->email1,
        ]);
    }
    
    private function sendFormNotification($email, $formName, $data)
    {
        require_once('modules/Emails/Email.php');
        $emailObj = new \Email();
        
        $body = "<h3>New Form Submission: $formName</h3><table>";
        foreach ($data as $field => $value) {
            $label = ucfirst(str_replace('_', ' ', $field));
            $body .= "<tr><td><strong>$label:</strong></td><td>$value</td></tr>";
        }
        $body .= "</table>";
        
        $emailObj->to_addrs = $email;
        $emailObj->type = 'out';
        $emailObj->status = 'sent';
        $emailObj->name = "New Form Submission: $formName";
        $emailObj->description_html = $body;
        $emailObj->from_addr = $GLOBALS['sugar_config']['notify_fromaddress'];
        $emailObj->from_name = 'CRM Forms';
        
        $emailObj->send();
    }
}
```

### 6. Final Integration and Deployment

#### 6.1 Update API Routes (Complete)
`custom/api/v8/routes/routes.php` (add Phase 4 routes):
```php
// Add these routes to existing file

// Health Score endpoints
'health_calculate' => [
    'method' => 'POST',
    'route' => '/health/calculate/{id}',
    'class' => 'Api\V8\Custom\Controller\HealthScoreController',
    'function' => 'calculateHealthScore',
    'secure' => true,
],
'health_score' => [
    'method' => 'GET',
    'route' => '/health/score/{id}',
    'class' => 'Api\V8\Custom\Controller\HealthScoreController',
    'function' => 'getHealthScore',
    'secure' => true,
],
'health_trends' => [
    'method' => 'GET',
    'route' => '/health/trends/{id}',
    'class' => 'Api\V8\Custom\Controller\HealthScoreController',
    'function' => 'getHealthTrends',
    'secure' => true,
],
'health_at_risk' => [
    'method' => 'GET',
    'route' => '/health/at-risk',
    'class' => 'Api\V8\Custom\Controller\HealthScoreController',
    'function' => 'getAtRiskAccounts',
    'secure' => true,
],

// Meeting Scheduler endpoints
'meeting_slots' => [
    'method' => 'POST',
    'route' => '/meetings/available-slots',
    'class' => 'Api\V8\Custom\Controller\AIController',
    'function' => 'getAvailableSlots',
    'secure' => false, // Allow chatbot access
],
'meeting_schedule' => [
    'method' => 'POST',
    'route' => '/meetings/schedule',
    'class' => 'Api\V8\Custom\Controller\AIController',
    'function' => 'scheduleMeeting',
    'secure' => false,
],

// Enhanced chat
'chat_enhanced' => [
    'method' => 'POST',
    'route' => '/ai/chat-enhanced',
    'class' => 'Api\V8\Custom\Controller\AIController',
    'function' => 'chatEnhanced',
    'secure' => false,
],

// Alert endpoints
'alerts_list' => [
    'method' => 'GET',
    'route' => '/alerts',
    'class' => 'Api\V8\Custom\Controller\AlertController',
    'function' => 'getAlerts',
    'secure' => true,
],
'alerts_read' => [
    'method' => 'PATCH',
    'route' => '/alerts/{id}/read',
    'class' => 'Api\V8\Custom\Controller\AlertController',
    'function' => 'markAsRead',
    'secure' => true,
],
'alerts_read_all' => [
    'method' => 'PATCH',
    'route' => '/alerts/read-all',
    'class' => 'Api\V8\Custom\Controller\AlertController',
    'function' => 'markAllAsRead',
    'secure' => true,
],
'alert_rules' => [
    'method' => 'GET',
    'route' => '/alerts/rules',
    'class' => 'Api\V8\Custom\Controller\AlertController',
    'function' => 'getAlertRules',
    'secure' => true,
],
'alert_rule_create' => [
    'method' => 'POST',
    'route' => '/alerts/rules',
    'class' => 'Api\V8\Custom\Controller\AlertController',
    'function' => 'createAlertRule',
    'secure' => true,
],
'alert_rule_update' => [
    'method' => 'PATCH',
    'route' => '/alerts/rules/{id}',
    'class' => 'Api\V8\Custom\Controller\AlertController',
    'function' => 'updateAlertRule',
    'secure' => true,
],
'alert_rule_delete' => [
    'method' => 'DELETE',
    'route' => '/alerts/rules/{id}',
    'class' => 'Api\V8\Custom\Controller\AlertController',
    'function' => 'deleteAlertRule',
    'secure' => true,
],
'alert_rule_test' => [
    'method' => 'POST',
    'route' => '/alerts/rules/test',
    'class' => 'Api\V8\Custom\Controller\AlertController',
    'function' => 'testAlertRule',
    'secure' => true,
],

// Public endpoints
'public_articles' => [
    'method' => 'GET',
    'route' => '/public/kb/articles',
    'class' => 'Api\V8\Custom\Controller\PublicController',
    'function' => 'getPublicArticles',
    'secure' => false,
],
'public_article' => [
    'method' => 'GET',
    'route' => '/public/kb/articles/{slug}',
    'class' => 'Api\V8\Custom\Controller\PublicController',
    'function' => 'getPublicArticle',
    'secure' => false,
],
'public_categories' => [
    'method' => 'GET',
    'route' => '/public/kb/categories',
    'class' => 'Api\V8\Custom\Controller\PublicController',
    'function' => 'getPublicCategories',
    'secure' => false,
],
'public_form_submit' => [
    'method' => 'POST',
    'route' => '/public/forms/{id}/submit',
    'class' => 'Api\V8\Custom\Controller\PublicController',
    'function' => 'submitPublicForm',
    'secure' => false,
],
```

#### 6.2 Create Alert Tables SQL
`custom/install/sql/phase4_tables.sql`:
```sql
-- Alert tables
CREATE TABLE IF NOT EXISTS alerts (
    id CHAR(36) NOT NULL PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    data JSON,
    read_flag TINYINT(1) DEFAULT 0,
    assigned_user_id CHAR(36),
    created_at DATETIME,
    expires_at DATETIME,
    INDEX idx_user (assigned_user_id, read_flag),
    INDEX idx_created (created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alert_rules (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    conditions JSON NOT NULL,
    actions JSON NOT NULL,
    enabled TINYINT(1) DEFAULT 1,
    created_by CHAR(36),
    date_created DATETIME,
    date_modified DATETIME,
    INDEX idx_type (type, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 6.3 Create Production Configuration
`custom/config/production.php`:
```php
<?php
// Production configuration overrides

// OpenAI Configuration
$sugar_config['ai']['openai']['api_key'] = getenv('OPENAI_API_KEY');

// Redis Configuration
$sugar_config['external_cache_disabled'] = false;
$sugar_config['external_cache']['redis']['host'] = getenv('REDIS_HOST') ?: 'redis';
$sugar_config['external_cache']['redis']['port'] = getenv('REDIS_PORT') ?: 6379;

// Security Settings
$sugar_config['api']['v8']['cors']['origin'] = explode(',', getenv('CORS_ORIGINS') ?: 'https://app.yourcrm.com');
$sugar_config['api']['v8']['jwt']['secret'] = getenv('JWT_SECRET') ?: 'change-this-in-production';

// Performance Settings
$sugar_config['developerMode'] = false;
$sugar_config['logger']['level'] = 'error';
$sugar_config['dump_slow_queries'] = false;
$sugar_config['slow_query_time_msec'] = 5000;

// File Upload Settings
$sugar_config['upload_maxsize'] = 30000000; // 30MB
$sugar_config['upload_dir'] = 'upload/';

// Session Settings
$sugar_config['session_gc_maxlifetime'] = 7200; // 2 hours
```

#### 6.4 Create Docker Production Configuration
`docker-compose.prod.yml`:
```yaml
version: '3.8'

services:
  mysql:
    image: mysql:5.7
    container_name: suitecrm_mysql_prod
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: suitecrm
      MYSQL_USER: suitecrm
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - crm_network

  suitecrm:
    build:
      context: .
      dockerfile: Dockerfile.prod
    container_name: suitecrm_app_prod
    restart: always
    depends_on:
      - mysql
      - redis
    environment:
      DATABASE_HOST: mysql
      DATABASE_NAME: suitecrm
      DATABASE_USER: suitecrm
      DATABASE_PASSWORD: ${MYSQL_PASSWORD}
      SITE_URL: ${SITE_URL}
      OPENAI_API_KEY: ${OPENAI_API_KEY}
      JWT_SECRET: ${JWT_SECRET}
      CORS_ORIGINS: ${CORS_ORIGINS}
    volumes:
      - app_data:/var/www/html/upload
      - ./custom:/var/www/html/custom:ro
    networks:
      - crm_network
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.suitecrm.rule=Host(`${DOMAIN}`)"
      - "traefik.http.routers.suitecrm.tls=true"
      - "traefik.http.routers.suitecrm.tls.certresolver=letsencrypt"

  redis:
    image: redis:alpine
    container_name: suitecrm_redis_prod
    restart: always
    networks:
      - crm_network
    volumes:
      - redis_data:/data

  traefik:
    image: traefik:v2.9
    container_name: traefik
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./traefik.yml:/etc/traefik/traefik.yml:ro
      - ./acme.json:/acme.json
      - /var/run/docker.sock:/var/run/docker.sock:ro
    networks:
      - crm_network

volumes:
  mysql_data:
  app_data:
  redis_data:

networks:
  crm_network:
    driver: bridge
```

#### 6.5 Create Deployment Script
`scripts/deploy.sh`:
```bash
#!/bin/bash

echo "🚀 Deploying AI CRM Platform..."

# Load environment variables
if [ -f .env.prod ]; then
    export $(cat .env.prod | xargs)
else
    echo "❌ .env.prod file not found!"
    exit 1
fi

# Pull latest code
echo "📥 Pulling latest code..."
git pull origin main

# Build containers
echo "🏗️ Building containers..."
docker-compose -f docker-compose.prod.yml build

# Stop current containers
echo "🛑 Stopping current containers..."
docker-compose -f docker-compose.prod.yml down

# Start new containers
echo "▶️ Starting new containers..."
docker-compose -f docker-compose.prod.yml up -d

# Wait for services
echo "⏳ Waiting for services to start..."
sleep 30

# Run database migrations
echo "🗄️ Running database migrations..."
docker exec suitecrm_app_prod php -f custom/install/install_phase3_tables.php
docker exec suitecrm_app_prod php -f custom/install/install_phase4_tables.php

# Quick Repair and Rebuild
echo "🔧 Running Quick Repair and Rebuild..."
docker exec suitecrm_app_prod php -f repair.php

# Generate demo data (if needed)
if [ "$GENERATE_DEMO_DATA" = "true" ]; then
    echo "📊 Generating demo data..."
    docker exec suitecrm_app_prod php -f custom/install/generate_demo_data.php
fi

# Clear caches
echo "🧹 Clearing caches..."
docker exec suitecrm_app_prod rm -rf cache/*
docker exec suitecrm_redis_prod redis-cli FLUSHALL

# Health check
echo "🏥 Running health check..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://${DOMAIN}/api/v8/login)

if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "401" ]; then
    echo "✅ Deployment successful!"
    echo "🌐 Access your CRM at: https://${DOMAIN}"
else
    echo "❌ Health check failed. HTTP Status: $HTTP_STATUS"
    echo "📋 Checking logs..."
    docker-compose -f docker-compose.prod.yml logs --tail=50 suitecrm
    exit 1
fi
```

### 7. Monitoring and Maintenance

#### 7.1 Create Health Check Endpoint
`custom/api/v8/controllers/HealthController.php`:
```php
<?php
namespace Api\V8\Custom\Controller;

use Api\V8\Controller\BaseController;
use Slim\Http\Request;
use Slim\Http\Response;

class HealthController extends BaseController
{
    public function healthCheck(Request $request, Response $response, array $args)
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'version' => '4.0.0',
            'services' => [],
        ];
        
        // Check database
        try {
            global $db;
            $db->query("SELECT 1");
            $health['services']['database'] = 'healthy';
        } catch (\Exception $e) {
            $health['services']['database'] = 'unhealthy';
            $health['status'] = 'unhealthy';
        }
        
        // Check Redis
        try {
            $redis = new \Predis\Client([
                'scheme' => 'tcp',
                'host'   => 'redis',
                'port'   => 6379,
            ]);
            $redis->ping();
            $health['services']['redis'] = 'healthy';
        } catch (\Exception $e) {
            $health['services']['redis'] = 'unhealthy';
        }
        
        // Check OpenAI
        try {
            $aiService = new \Custom\Services\OpenAIService();
            // Just check if service can be instantiated
            $health['services']['openai'] = 'healthy';
        } catch (\Exception $e) {
            $health['services']['openai'] = 'unhealthy';
        }
        
        $statusCode = $health['status'] === 'healthy' ? 200 : 503;
        
        return $response->withJson($health, $statusCode);
    }
}
```

Add route:
```php
'health_check' => [
    'method' => 'GET',
    'route' => '/health',
    'class' => 'Api\V8\Custom\Controller\HealthController',
    'function' => 'healthCheck',
    'secure' => false,
],
```

## Testing Phase 4

### Backend Integration Tests
`tests/backend/integration/Phase4ApiTest.php`:
```php
<?php
use PHPUnit\Framework\TestCase;

class Phase4ApiTest extends TestCase
{
    // ... test setup ...
    
    public function testHealthScoreCalculation()
    {
        // Create test account
        $account = \BeanFactory::newBean('Accounts');
        $account->name = 'Test Health Score Account';
        $account->account_type = 'Customer';
        $account->mrr = 5000;
        $account->save();
        
        // Calculate health score
        $response = $this->client->post("health/calculate/{$account->id}", [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('score', $data);
        $this->assertArrayHasKey('factors', $data);
        $this->assertArrayHasKey('risk_level', $data);
        $this->assertArrayHasKey('recommendations', $data);
    }
    
    public function testMeetingScheduler()
    {
        // Get available slots
        $response = $this->client->post('meetings/available-slots', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => [
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+7 days')),
            ],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $slots = json_decode($response->getBody(), true);
        $this->assertIsArray($slots);
        $this->assertNotEmpty($slots);
        
        // Find available slot
        $availableSlot = null;
        foreach ($slots as $slot) {
            if ($slot['available']) {
                $availableSlot = $slot;
                break;
            }
        }
        
        $this->assertNotNull($availableSlot);
        
        // Schedule meeting
        $response = $this->client->post('meetings/schedule', [
            'json' => [
                'date_start' => $availableSlot['start'],
                'duration' => 30,
                'type' => 'demo',
                'title' => 'Test Demo Meeting',
            ],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
    
    public function testAlertSystem()
    {
        // Create alert rule
        $response = $this->client->post('alerts/rules', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
            'json' => [
                'name' => 'High Score Lead Alert',
                'type' => 'lead_score',
                'conditions' => [
                    ['field' => 'score', 'operator' => 'greater_than', 'value' => 80],
                ],
                'actions' => [
                    [
                        'type' => 'notification',
                        'config' => [
                            'severity' => 'info',
                            'title' => 'High Score Lead',
                            'message' => 'Lead {{lead_name}} scored {{score}}',
                        ],
                    ],
                ],
                'enabled' => true,
            ],
        ]);
        
        $this->assertEquals(201, $response->getStatusCode());
        
        // Get alerts
        $response = $this->client->get('alerts?unread=true', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
}
```

## Definition of Success

### ✅ Phase 4 Backend Success Criteria:

1. **Customer Health Scoring**
   - [ ] Health score calculation includes all 6 factors
   - [ ] Scores saved to database with history
   - [ ] AI recommendations generated
   - [ ] High-risk alerts created automatically
   - [ ] Trends API returns historical data
   - [ ] At-risk accounts endpoint works

2. **Advanced Chatbot**
   - [ ] Meeting availability calculated correctly
   - [ ] Slots respect existing calendar
   - [ ] Meeting scheduling creates SuiteCRM record
   - [ ] Calendar invites sent with iCal attachment
   - [ ] Chat detects scheduling intent
   - [ ] Integration with lead creation

3. **Activity Alerts**
   - [ ] Alerts created for all event types
   - [ ] Real-time notifications via Redis
   - [ ] Email notifications for critical alerts
   - [ ] Alert rules evaluate conditions correctly
   - [ ] Actions execute (notification, email, webhook)
   - [ ] Read/unread tracking works

4. **Demo Data**
   - [ ] 100+ leads with varied scores
   - [ ] 30+ accounts with health scores
   - [ ] 50+ opportunities in pipeline
   - [ ] Complete activity history
   - [ ] Form submissions linked to leads
   - [ ] KB articles and categories
   - [ ] Website session tracking data