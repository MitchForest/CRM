<?php
// Standalone API for CRM functionality
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace(['/custom/api/standalone.php', '/api'], '', $path);
$path = '/' . trim($path, '/');

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Simple file storage for persistence
$dataFile = __DIR__ . '/data.json';
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([]));
}
$persistedData = json_decode(file_get_contents($dataFile), true) ?: [];

// Initialize mock data
$mockData = [
    'leads' => [
        ['id' => '1', 'firstName' => 'John', 'lastName' => 'Doe', 'email' => 'john@example.com', 'phone' => '555-0123', 'company' => 'ABC Corp', 'status' => 'New', 'leadSource' => 'Website', 'score' => 85, 'dateEntered' => date('Y-m-d H:i:s', strtotime('-2 days')), 'dateModified' => date('Y-m-d H:i:s', strtotime('-1 day'))],
        ['id' => '2', 'firstName' => 'Jane', 'lastName' => 'Smith', 'email' => 'jane@example.com', 'phone' => '555-0124', 'company' => 'XYZ Inc', 'status' => 'Contacted', 'leadSource' => 'Email', 'score' => 72, 'dateEntered' => date('Y-m-d H:i:s', strtotime('-5 days')), 'dateModified' => date('Y-m-d H:i:s', strtotime('-3 days'))],
        ['id' => '3', 'firstName' => 'Bob', 'lastName' => 'Johnson', 'email' => 'bob@example.com', 'phone' => '555-0125', 'company' => 'Tech Solutions', 'status' => 'Qualified', 'leadSource' => 'Referral', 'score' => 92, 'dateEntered' => date('Y-m-d H:i:s', strtotime('-7 days')), 'dateModified' => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ['id' => '4', 'firstName' => 'Alice', 'lastName' => 'Williams', 'email' => 'alice@example.com', 'phone' => '555-0126', 'company' => 'Finance Corp', 'status' => 'New', 'leadSource' => 'Trade Show', 'score' => 78, 'dateEntered' => date('Y-m-d H:i:s', strtotime('-1 day')), 'dateModified' => date('Y-m-d H:i:s')],
        ['id' => '5', 'firstName' => 'Charlie', 'lastName' => 'Brown', 'email' => 'charlie@example.com', 'phone' => '555-0127', 'company' => 'Retail Plus', 'status' => 'Working', 'leadSource' => 'Partner', 'score' => 88, 'dateEntered' => date('Y-m-d H:i:s', strtotime('-10 days')), 'dateModified' => date('Y-m-d H:i:s', strtotime('-4 days'))]
    ],
    'accounts' => [
        ['id' => '1', 'name' => 'Acme Corporation', 'industry' => 'Technology', 'type' => 'Customer', 'annualRevenue' => 5000000, 'employees' => 250, 'website' => 'www.acme.com', 'phone' => '555-0100', 'billingCity' => 'San Francisco', 'billingState' => 'CA', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-30 days'))],
        ['id' => '2', 'name' => 'Global Industries', 'industry' => 'Manufacturing', 'type' => 'Prospect', 'annualRevenue' => 10000000, 'employees' => 500, 'website' => 'www.global.com', 'phone' => '555-0101', 'billingCity' => 'New York', 'billingState' => 'NY', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-25 days'))],
        ['id' => '3', 'name' => 'Tech Innovators', 'industry' => 'Software', 'type' => 'Customer', 'annualRevenue' => 3000000, 'employees' => 100, 'website' => 'www.techinnovators.com', 'phone' => '555-0102', 'billingCity' => 'Austin', 'billingState' => 'TX', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-20 days'))]
    ],
    'contacts' => [
        ['id' => '1', 'firstName' => 'John', 'lastName' => 'Smith', 'title' => 'CEO', 'email' => 'john.smith@acme.com', 'phone' => '555-0200', 'mobile' => '555-0300', 'accountId' => '1', 'accountName' => 'Acme Corporation', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-28 days'))],
        ['id' => '2', 'firstName' => 'Sarah', 'lastName' => 'Johnson', 'title' => 'VP Sales', 'email' => 'sarah@global.com', 'phone' => '555-0201', 'mobile' => '555-0301', 'accountId' => '2', 'accountName' => 'Global Industries', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-24 days'))],
        ['id' => '3', 'firstName' => 'Mike', 'lastName' => 'Wilson', 'title' => 'CTO', 'email' => 'mike@techinnovators.com', 'phone' => '555-0202', 'mobile' => '555-0302', 'accountId' => '3', 'accountName' => 'Tech Innovators', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-19 days'))]
    ],
    'opportunities' => [
        ['id' => '1', 'name' => 'Acme Corp - Enterprise Deal', 'accountId' => '1', 'accountName' => 'Acme Corporation', 'salesStage' => 'Proposal', 'amount' => 150000, 'probability' => 75, 'closeDate' => date('Y-m-d', strtotime('+30 days')), 'type' => 'New Business', 'leadSource' => 'Website', 'description' => 'Enterprise software upgrade', 'assignedTo' => 'Admin User', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-14 days'))],
        ['id' => '2', 'name' => 'Global Industries - Expansion', 'accountId' => '2', 'accountName' => 'Global Industries', 'salesStage' => 'Negotiation', 'amount' => 250000, 'probability' => 60, 'closeDate' => date('Y-m-d', strtotime('+45 days')), 'type' => 'Existing Business', 'leadSource' => 'Referral', 'description' => 'Expansion to new facilities', 'assignedTo' => 'Admin User', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-10 days'))],
        ['id' => '3', 'name' => 'Tech Innovators - Pilot', 'accountId' => '3', 'accountName' => 'Tech Innovators', 'salesStage' => 'Qualification', 'amount' => 50000, 'probability' => 30, 'closeDate' => date('Y-m-d', strtotime('+60 days')), 'type' => 'New Business', 'leadSource' => 'Cold Call', 'description' => 'Pilot program for new product', 'assignedTo' => 'Admin User', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-5 days'))]
    ],
    'cases' => [
        ['id' => '1', 'name' => 'Login Issue', 'accountId' => '1', 'accountName' => 'Acme Corporation', 'status' => 'Open', 'priority' => 'High', 'type' => 'Technical', 'description' => 'User cannot login to the system', 'assignedTo' => 'Support Team', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ['id' => '2', 'name' => 'Feature Request', 'accountId' => '2', 'accountName' => 'Global Industries', 'status' => 'Pending', 'priority' => 'Medium', 'type' => 'Feature', 'description' => 'Request for custom reporting', 'assignedTo' => 'Product Team', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-5 days'))],
        ['id' => '3', 'name' => 'Billing Question', 'accountId' => '3', 'accountName' => 'Tech Innovators', 'status' => 'Closed', 'priority' => 'Low', 'type' => 'Billing', 'description' => 'Question about invoice', 'assignedTo' => 'Finance Team', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-10 days'))]
    ],
    'activities' => [
        ['id' => '1', 'type' => 'Call', 'subject' => 'Follow-up call', 'status' => 'Completed', 'priority' => 'High', 'dateStart' => date('Y-m-d H:i:s', strtotime('-1 day')), 'duration' => 30, 'contactId' => '1', 'contactName' => 'John Smith', 'assignedTo' => 'Admin User'],
        ['id' => '2', 'type' => 'Meeting', 'subject' => 'Product Demo', 'status' => 'Scheduled', 'priority' => 'High', 'dateStart' => date('Y-m-d H:i:s', strtotime('+2 days')), 'duration' => 60, 'contactId' => '2', 'contactName' => 'Sarah Johnson', 'assignedTo' => 'Admin User'],
        ['id' => '3', 'type' => 'Task', 'subject' => 'Send proposal', 'status' => 'In Progress', 'priority' => 'Medium', 'dateStart' => date('Y-m-d H:i:s'), 'dueDate' => date('Y-m-d H:i:s', strtotime('+3 days')), 'contactId' => '3', 'contactName' => 'Mike Wilson', 'assignedTo' => 'Admin User']
    ]
];

// Initialize data if empty
foreach ($mockData as $module => $records) {
    if (!isset($persistedData[$module]) || empty($persistedData[$module])) {
        $persistedData[$module] = $records;
    }
}
file_put_contents($dataFile, json_encode($persistedData));

// Generic CRUD handler
function handleCRUD($module, $method, $path, $data, &$persistedData, $dataFile) {
    $records = $persistedData[$module] ?? [];
    
    // List with pagination
    if ($method === 'GET' && $path === "/$module") {
        $page = $_GET['page'] ?? 1;
        $pageSize = $_GET['pageSize'] ?? 10;
        $offset = ($page - 1) * $pageSize;
        
        $pagedRecords = array_slice($records, $offset, $pageSize);
        
        echo json_encode([
            'data' => $pagedRecords,
            'pagination' => [
                'page' => (int)$page,
                'pageSize' => (int)$pageSize,
                'totalPages' => ceil(count($records) / $pageSize),
                'totalItems' => count($records)
            ]
        ]);
        return true;
    }
    
    // Get single record
    if ($method === 'GET' && preg_match("#^/$module/(\w+)$#", $path, $matches)) {
        $id = $matches[1];
        $record = null;
        foreach ($records as $r) {
            if ($r['id'] === $id) {
                $record = $r;
                break;
            }
        }
        
        if ($record) {
            echo json_encode(['data' => $record]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Record not found']);
        }
        return true;
    }
    
    // Create
    if ($method === 'POST' && $path === "/$module") {
        $newRecord = array_merge(['id' => uniqid(), 'dateEntered' => date('Y-m-d H:i:s'), 'dateModified' => date('Y-m-d H:i:s')], $data);
        $persistedData[$module][] = $newRecord;
        file_put_contents($dataFile, json_encode($persistedData));
        echo json_encode(['success' => true, 'data' => $newRecord]);
        return true;
    }
    
    // Update
    if ($method === 'PUT' && preg_match("#^/$module/(\w+)$#", $path, $matches)) {
        $id = $matches[1];
        $updated = false;
        foreach ($persistedData[$module] as &$record) {
            if ($record['id'] === $id) {
                $record = array_merge($record, $data, ['dateModified' => date('Y-m-d H:i:s')]);
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            file_put_contents($dataFile, json_encode($persistedData));
            echo json_encode(['success' => true, 'data' => $record]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Record not found']);
        }
        return true;
    }
    
    // Delete
    if ($method === 'DELETE' && preg_match("#^/$module/(\w+)$#", $path, $matches)) {
        $id = $matches[1];
        $newRecords = [];
        $deleted = false;
        foreach ($persistedData[$module] as $record) {
            if ($record['id'] !== $id) {
                $newRecords[] = $record;
            } else {
                $deleted = true;
            }
        }
        
        if ($deleted) {
            $persistedData[$module] = $newRecords;
            file_put_contents($dataFile, json_encode($persistedData));
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Record not found']);
        }
        return true;
    }
    
    return false;
}

// Health check
if ($method === 'GET' && $path === '/health') {
    echo json_encode(['status' => 'healthy', 'timestamp' => date('Y-m-d H:i:s')]);
    exit;
}

// Auth endpoints
if ($path === '/auth/login' && $method === 'POST') {
    echo json_encode([
        'success' => true,
        'data' => [
            'accessToken' => 'demo-token-' . time(),
            'refreshToken' => 'demo-refresh-' . time(),
            'user' => [
                'id' => '1',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'firstName' => 'Admin',
                'lastName' => 'User'
            ]
        ]
    ]);
    exit;
}

// Handle standard CRUD for all modules
$modules = ['leads', 'accounts', 'contacts', 'opportunities', 'cases', 'activities'];
foreach ($modules as $module) {
    if (strpos($path, "/$module") === 0) {
        if (handleCRUD($module, $method, $path, $data, $persistedData, $dataFile)) {
            exit;
        }
    }
}

// AI Lead Scoring
if ($method === 'POST' && preg_match('#^/leads/(\w+)/ai-score$#', $path, $matches)) {
    $leadId = $matches[1];
    echo json_encode([
        'success' => true,
        'data' => [
            'lead_id' => $leadId,
            'score' => rand(65, 95),
            'confidence' => rand(70, 90) / 100,
            'factors' => [
                ['name' => 'Company Size', 'value' => 85, 'weight' => 0.25],
                ['name' => 'Industry Match', 'value' => 90, 'weight' => 0.20],
                ['name' => 'Engagement Level', 'value' => 75, 'weight' => 0.20],
                ['name' => 'Budget Fit', 'value' => 80, 'weight' => 0.15],
                ['name' => 'Timeline', 'value' => 70, 'weight' => 0.10],
                ['name' => 'Decision Authority', 'value' => 85, 'weight' => 0.10]
            ],
            'recommendation' => 'High priority lead - schedule follow-up within 24 hours',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;
}

// Dashboard endpoints
if ($method === 'GET' && strpos($path, '/dashboard/') === 0) {
    $endpoint = str_replace('/dashboard/', '', $path);
    
    switch ($endpoint) {
        case 'stats':
            echo json_encode([
                'data' => [
                    'leads' => ['total' => count($persistedData['leads'] ?? []), 'new' => 5, 'converted' => 2],
                    'opportunities' => ['total' => count($persistedData['opportunities'] ?? []), 'value' => 450000, 'won' => 1],
                    'activities' => ['total' => count($persistedData['activities'] ?? []), 'completed' => 8, 'overdue' => 2],
                    'revenue' => ['current' => 125000, 'target' => 500000, 'growth' => 0.15]
                ]
            ]);
            break;
    }
    exit;
}

// AI Chat endpoint
if ($path === '/ai/chat' && $method === 'POST') {
    $message = strtolower($data['message'] ?? '');
    $visitorId = $data['visitor_id'] ?? null;
    $metadata = [];
    $intent = 'general';
    
    // Simple keyword-based responses
    $response = 'I can help you with our CRM features, pricing, and scheduling demos. What would you like to know?';
    $suggestedActions = ['Learn about features', 'View pricing', 'Schedule a demo'];
    
    // Handle different intents
    if (strpos($message, 'hello') !== false || strpos($message, 'hi') !== false || strpos($message, 'hey') !== false) {
        $response = 'Hello! I\'m here to help you learn about our AI-powered CRM. What would you like to know about?';
        $suggestedActions = ['Tell me about AI features', 'How does lead scoring work?', 'Show me pricing'];
    } elseif (strpos($message, 'price') !== false || strpos($message, 'cost') !== false || strpos($message, 'pricing') !== false) {
        $response = 'Our CRM is completely free and open-source! You can self-host it on your own servers with no licensing fees. The only costs are your hosting infrastructure.';
        $suggestedActions = ['How do I install it?', 'What are the requirements?', 'Schedule a demo'];
    } elseif (strpos($message, 'demo') !== false) {
        $response = 'I\'d be happy to help you schedule a personalized demo! You can see all our features in action including AI lead scoring, activity tracking, and more.';
        $suggestedActions = ['Book a demo now', 'Tell me more about features', 'View documentation'];
    } elseif (strpos($message, 'feature') !== false || strpos($message, 'can') !== false || strpos($message, 'what') !== false) {
        $response = 'Our key features include:\n• AI-powered lead scoring\n• Real-time activity tracking\n• Drag-and-drop pipeline\n• Custom form builder\n• Knowledge base\n• Customer health monitoring';
        $suggestedActions = ['Tell me about AI scoring', 'How does tracking work?', 'Book a demo'];
    } elseif (strpos($message, 'lead') !== false || strpos($message, 'score') !== false || strpos($message, 'scoring') !== false) {
        $response = 'Our AI Lead Scoring automatically analyzes leads based on company size, engagement, behavior, and more. Leads are scored 0-100, with anything above 70 considered high priority.';
        $suggestedActions = ['See scoring in action', 'How is it calculated?', 'Book a demo'];
    } elseif (strpos($message, 'support') !== false || strpos($message, 'issue') !== false || strpos($message, 'problem') !== false) {
        if (strlen($message) > 50 && (strpos($message, 'not working') !== false || strpos($message, 'error') !== false)) {
            // Auto-create ticket
            $newCase = [
                'id' => uniqid(),
                'name' => 'Support Request: ' . substr($message, 0, 50) . '...',
                'description' => $message,
                'status' => 'New',
                'priority' => 'Medium',
                'type' => 'Technical',
                'createdAt' => date('Y-m-d H:i:s')
            ];
            $persistedData['cases'][] = $newCase;
            file_put_contents($dataFile, json_encode($persistedData));
            
            $response = "I've created support ticket #{$newCase['id']} for you. Our team will respond within 24 hours.";
            $intent = 'support_created';
            $metadata['ticket_created'] = true;
            $metadata['ticket_id'] = $newCase['id'];
        } else {
            $response = "I can help you create a support ticket. Please describe your issue in detail.";
            $intent = 'support';
        }
    }
    
    echo json_encode([
        'success' => true,
        'conversation_id' => $data['conversation_id'] ?? uniqid('conv_'),
        'message' => $response,
        'intent' => $intent,
        'suggested_actions' => $suggestedActions,
        'metadata' => $metadata,
        'confidence' => 0.9
    ]);
    exit;
}

// Knowledge Base
$kbArticles = [
    [
        'id' => '1',
        'title' => 'Getting Started with AI CRM',
        'slug' => 'getting-started',
        'content' => "# Getting Started with AI CRM\n\nWelcome to our AI-powered CRM system! This guide will help you get up and running quickly.\n\n## Quick Start\n\n1. **Login** - Use your credentials to access the system\n2. **Dashboard** - View your key metrics and activities\n3. **Add Leads** - Start adding leads manually or through forms\n4. **AI Scoring** - Watch as AI automatically scores your leads\n\n## Key Features\n\n### Lead Management\n- Automatic lead capture from forms\n- AI-powered lead scoring\n- Lead assignment and routing\n- Conversion tracking\n\n### Contact Management\n- Complete contact profiles\n- Activity history\n- Communication tracking\n- Relationship mapping\n\n### Opportunity Pipeline\n- Visual pipeline management\n- Drag-and-drop stages\n- Probability tracking\n- Revenue forecasting\n\n## Next Steps\n\nExplore our other guides to learn about specific features in detail.",
        'excerpt' => 'Learn how our AI-powered CRM can transform your sales process.',
        'category' => 'Getting Started',
        'category_id' => '1',
        'views' => 234,
        'helpful' => 45,
        'status' => 'published'
    ],
    [
        'id' => '2',
        'title' => 'Understanding AI Lead Scoring',
        'slug' => 'ai-lead-scoring',
        'content' => "# Understanding AI Lead Scoring\n\nOur AI lead scoring system helps you prioritize leads based on their likelihood to convert.\n\n## How It Works\n\n### Data Collection\nThe AI analyzes multiple data points:\n- Company information\n- Engagement history\n- Website behavior\n- Email interactions\n- Form submissions\n\n### Scoring Factors\n\n1. **Company Size** (25% weight)\n   - Employee count\n   - Annual revenue\n   - Industry type\n\n2. **Engagement Level** (20% weight)\n   - Email opens/clicks\n   - Website visits\n   - Content downloads\n\n3. **Budget Fit** (15% weight)\n   - Stated budget\n   - Company revenue\n   - Previous purchases\n\n4. **Timeline** (10% weight)\n   - Urgency indicators\n   - Project timelines\n   - Decision timeframe\n\n## Score Interpretation\n\n- **90-100**: Hot lead - immediate follow-up required\n- **70-89**: Warm lead - high priority\n- **50-69**: Qualified lead - regular follow-up\n- **Below 50**: Needs nurturing\n\n## Best Practices\n\n1. Review scores daily\n2. Act on high scores immediately\n3. Update lead information regularly\n4. Use scores to prioritize outreach",
        'excerpt' => 'How our AI analyzes and scores your leads automatically.',
        'category' => 'AI Features',
        'category_id' => '2',
        'views' => 189,
        'helpful' => 67,
        'status' => 'published'
    ],
    [
        'id' => '3',
        'title' => 'Setting Up Activity Tracking',
        'slug' => 'activity-tracking',
        'content' => "# Setting Up Activity Tracking\n\nTrack all visitor and customer activities in real-time.\n\n## Installation\n\n### 1. Add Tracking Code\n```javascript\n<!-- Add before closing </head> tag -->\n<script src=\"https://crm.yoursite.com/tracking.js\"></script>\n```\n\n### 2. Initialize Tracking\n```javascript\nCRMTracking.init({\n  apiKey: 'your-api-key',\n  domain: 'yoursite.com'\n});\n```\n\n## What We Track\n\n### Page Views\n- URL visited\n- Time on page\n- Scroll depth\n- Exit pages\n\n### Events\n- Button clicks\n- Form submissions\n- Downloads\n- Video plays\n\n### Visitor Information\n- Location\n- Device type\n- Browser\n- Referral source\n\n## Using the Data\n\n1. **Lead Scoring** - Activity feeds into AI scoring\n2. **Segmentation** - Create segments based on behavior\n3. **Personalization** - Tailor outreach based on interests\n4. **Alerts** - Get notified of key activities",
        'excerpt' => 'Track visitor behavior and engagement in real-time.',
        'category' => 'Features',
        'category_id' => '3',
        'views' => 156,
        'helpful' => 34,
        'status' => 'published'
    ],
    [
        'id' => '4',
        'title' => 'Managing Your Sales Pipeline',
        'slug' => 'sales-pipeline',
        'content' => "# Managing Your Sales Pipeline\n\nVisualize and manage your opportunities through their lifecycle.\n\n## Pipeline Stages\n\n### Default Stages\n1. **Qualification** - Initial opportunity assessment\n2. **Needs Analysis** - Understanding requirements\n3. **Proposal** - Presenting solution\n4. **Negotiation** - Terms and pricing\n5. **Closed Won/Lost** - Final outcome\n\n## Using the Pipeline\n\n### Drag and Drop\n- Click and drag opportunities between stages\n- Changes are saved automatically\n- Updates trigger notifications\n\n### Opportunity Details\n- Amount and probability\n- Expected close date\n- Associated contacts\n- Activity history\n\n## Best Practices\n\n1. Update stages promptly\n2. Keep amounts current\n3. Add notes for each interaction\n4. Review pipeline weekly",
        'excerpt' => 'Learn how to effectively manage your sales opportunities.',
        'category' => 'Sales',
        'category_id' => '4',
        'views' => 198,
        'helpful' => 56,
        'status' => 'published'
    ],
    [
        'id' => '5',
        'title' => 'Form Builder Guide',
        'slug' => 'form-builder',
        'content' => "# Form Builder Guide\n\nCreate custom forms to capture leads from any source.\n\n## Creating a Form\n\n1. Navigate to Forms > Create New\n2. Choose a template or start blank\n3. Drag fields from the sidebar\n4. Configure field properties\n5. Set up actions and notifications\n\n## Field Types\n\n- **Text** - Single line input\n- **Email** - Validated email field\n- **Phone** - Phone number with formatting\n- **Select** - Dropdown options\n- **Radio** - Single choice\n- **Checkbox** - Multiple choices\n- **Textarea** - Multi-line text\n- **File** - File uploads\n\n## Form Actions\n\n### On Submission\n- Create lead automatically\n- Send email notifications\n- Trigger AI scoring\n- Add to campaigns\n- Webhook integration\n\n## Embedding Forms\n\n### iFrame Method\n```html\n<iframe src=\"https://crm.site.com/form/123\" \n        width=\"100%\" height=\"600\"></iframe>\n```\n\n### JavaScript Method\n```html\n<div id=\"crm-form\"></div>\n<script src=\"https://crm.site.com/form.js\"></script>\n<script>CRMForm.load('123', '#crm-form');</script>\n```",
        'excerpt' => 'Build custom forms to capture and qualify leads.',
        'category' => 'Features',
        'category_id' => '3',
        'views' => 143,
        'helpful' => 38,
        'status' => 'published'
    ]
];

// Public KB endpoint - list articles
if ($path === '/knowledge-base/public' && $method === 'GET') {
    $category = $_GET['category'] ?? null;
    $articles = $kbArticles;
    
    if ($category) {
        $articles = array_filter($articles, function($article) use ($category) {
            return $article['category_id'] === $category;
        });
    }
    
    echo json_encode(['data' => array_values($articles)]);
    exit;
}

// Get single article by slug
if (preg_match('#^/knowledge-base/public/(.+)$#', $path, $matches) && $method === 'GET') {
    $slug = $matches[1];
    $article = null;
    
    foreach ($kbArticles as $a) {
        if ($a['slug'] === $slug) {
            $article = $a;
            break;
        }
    }
    
    if ($article) {
        // Add related articles
        $related = array_filter($kbArticles, function($a) use ($article) {
            return $a['category_id'] === $article['category_id'] && $a['id'] !== $article['id'];
        });
        $article['related'] = array_slice(array_values($related), 0, 3);
        
        echo json_encode(['data' => $article]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Article not found']);
    }
    exit;
}

// KB Categories
if ($path === '/knowledge-base/categories' && $method === 'GET') {
    echo json_encode([
        'data' => [
            ['id' => '1', 'name' => 'Getting Started', 'slug' => 'getting-started', 'article_count' => 1],
            ['id' => '2', 'name' => 'AI Features', 'slug' => 'ai-features', 'article_count' => 1],
            ['id' => '3', 'name' => 'Features', 'slug' => 'features', 'article_count' => 2],
            ['id' => '4', 'name' => 'Sales', 'slug' => 'sales', 'article_count' => 1]
        ]
    ]);
    exit;
}

// KB Articles (admin)
if ($path === '/knowledge-base/articles' && $method === 'GET') {
    echo json_encode([
        'data' => $kbArticles,
        'pagination' => [
            'page' => 1,
            'pageSize' => 10,
            'totalPages' => 1,
            'totalItems' => count($kbArticles)
        ]
    ]);
    exit;
}

// Popular articles
if ($path === '/knowledge-base/articles/popular' && $method === 'GET') {
    $popular = $kbArticles;
    usort($popular, function($a, $b) {
        return $b['views'] - $a['views'];
    });
    
    echo json_encode(['data' => array_slice($popular, 0, 5)]);
    exit;
}

// Featured articles
if ($path === '/knowledge-base/articles/featured' && $method === 'GET') {
    $limit = $_GET['limit'] ?? 6;
    // Return the most helpful articles as featured
    $featured = $kbArticles;
    usort($featured, function($a, $b) {
        return $b['helpful'] - $a['helpful'];
    });
    
    echo json_encode(['data' => array_slice($featured, 0, $limit)]);
    exit;
}

// Search KB
if ($path === '/knowledge-base/search' && $method === 'GET') {
    $query = strtolower($_GET['q'] ?? '');
    $results = [];
    
    if ($query) {
        foreach ($kbArticles as $article) {
            if (strpos(strtolower($article['title']), $query) !== false || 
                strpos(strtolower($article['content']), $query) !== false) {
                $results[] = [
                    'article' => $article,
                    'relevance_score' => 0.95
                ];
            }
        }
    }
    
    echo json_encode(['data' => $results]);
    exit;
}

// Forms
if ($path === '/forms' && $method === 'GET') {
    echo json_encode([
        'data' => [
            ['id' => '1', 'name' => 'Contact Form', 'status' => 'active', 'submissions' => 45],
            ['id' => '2', 'name' => 'Demo Request', 'status' => 'active', 'submissions' => 23]
        ]
    ]);
    exit;
}

// Activity Tracking
if (strpos($path, '/analytics/') === 0) {
    $endpoint = str_replace('/analytics/', '', $path);
    
    if ($endpoint === 'visitors' && $method === 'GET') {
        echo json_encode([
            'data' => [
                ['visitor_id' => 'v1', 'pages_viewed' => 5, 'duration' => 300, 'location' => 'San Francisco, CA'],
                ['visitor_id' => 'v2', 'pages_viewed' => 3, 'duration' => 180, 'location' => 'New York, NY']
            ]
        ]);
        exit;
    }
}

// Customer Health
if ($path === '/customer-health/accounts' && $method === 'GET') {
    echo json_encode([
        'data' => [
            ['id' => '1', 'name' => 'Acme Corporation', 'health_score' => 85, 'health_status' => 'healthy'],
            ['id' => '2', 'name' => 'Global Industries', 'health_score' => 45, 'health_status' => 'at_risk'],
            ['id' => '3', 'name' => 'Tech Innovators', 'health_score' => 92, 'health_status' => 'healthy']
        ]
    ]);
    exit;
}

// Default 404
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found: ' . $method . ' ' . $path]);