<?php
// Simple standalone API that works without loading full SuiteCRM

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simple file-based token storage for MVP
$tokenFile = __DIR__ . '/tokens.json';
$tokens = [];
if (file_exists($tokenFile)) {
    $tokens = json_decode(file_get_contents($tokenFile), true) ?: [];
}

// Function to save tokens
function saveTokens($tokens) {
    global $tokenFile;
    file_put_contents($tokenFile, json_encode($tokens));
}

// Function to validate token
function validateToken($token) {
    global $tokens;
    if (!$token) return false;
    
    // Check if token exists and is not expired (24 hours)
    if (isset($tokens[$token])) {
        $tokenData = $tokens[$token];
        if (time() - $tokenData['created'] < 86400) { // 24 hours
            return $tokenData;
        }
    }
    return false;
}

// Get request path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/custom/api/standalone\.php#', '', $path);
$path = preg_replace('#^/api#', '', $path);
if (empty($path)) $path = '/';

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

// Get authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = '';
if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

// Public endpoints that don't require authentication
$publicEndpoints = [
    '/auth/login',
    '/auth/refresh',
    '/health',
    '/track/pageview',
    '/track/event',
    '/track/page-exit',
    '/track/conversion',
    '/track/identify',
    '/forms/submit',
    '/kb/search',
    '/ai/chat'
];

// Check if endpoint requires authentication
$requiresAuth = true;
foreach ($publicEndpoints as $endpoint) {
    if (strpos($path, $endpoint) === 0) {
        $requiresAuth = false;
        break;
    }
}

// TEMPORARILY DISABLED FOR DEMO - ALL ENDPOINTS ARE PUBLIC
// Validate token for protected endpoints
// if ($requiresAuth) {
//     $tokenData = validateToken($token);
//     if (!$tokenData) {
//         http_response_code(401);
//         echo json_encode(['error' => 'Unauthorized', 'message' => 'Invalid or expired token']);
//         exit;
//     }
// }

// Set fake token data for all requests
$tokenData = [
    'user' => [
        'id' => '1',
        'username' => 'admin',
        'email' => 'admin@example.com',
        'firstName' => 'Admin',
        'lastName' => 'User'
    ]
];

// Simple routing
if ($method === 'POST' && $path === '/auth/login') {
    // Simple login - just check if username is admin and password is admin123
    if ($data['username'] === 'admin' && $data['password'] === 'admin123') {
        $accessToken = 'simple-token-' . uniqid();
        $refreshToken = 'refresh-token-' . uniqid();
        
        // Store tokens
        $tokens[$accessToken] = [
            'type' => 'access',
            'refresh' => $refreshToken,
            'user' => [
                'id' => '1',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'firstName' => 'Admin',
                'lastName' => 'User'
            ],
            'created' => time()
        ];
        
        $tokens[$refreshToken] = [
            'type' => 'refresh',
            'access' => $accessToken,
            'user' => [
                'id' => '1',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'firstName' => 'Admin',
                'lastName' => 'User'
            ],
            'created' => time()
        ];
        
        saveTokens($tokens);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken,
                'user' => [
                    'id' => '1',
                    'username' => 'admin',
                    'email' => 'admin@example.com',
                    'firstName' => 'Admin',
                    'lastName' => 'User'
                ]
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
    }
    exit;
}

// Refresh token endpoint
if ($method === 'POST' && $path === '/auth/refresh') {
    $refreshToken = $data['refreshToken'] ?? '';
    
    if (!$refreshToken) {
        http_response_code(400);
        echo json_encode(['error' => 'Refresh token required']);
        exit;
    }
    
    $tokenData = validateToken($refreshToken);
    if (!$tokenData || $tokenData['type'] !== 'refresh') {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid refresh token']);
        exit;
    }
    
    // Generate new access token
    $newAccessToken = 'simple-token-' . uniqid();
    
    // Store new token
    $tokens[$newAccessToken] = [
        'type' => 'access',
        'refresh' => $refreshToken,
        'user' => $tokenData['user'],
        'created' => time()
    ];
    
    // Update refresh token to point to new access token
    $tokens[$refreshToken]['access'] = $newAccessToken;
    
    saveTokens($tokens);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'accessToken' => $newAccessToken,
            'refreshToken' => $refreshToken,
            'user' => $tokenData['user']
        ]
    ]);
    exit;
}

// Logout endpoint
if ($method === 'POST' && $path === '/auth/logout') {
    // Remove token from storage
    if ($token && isset($tokens[$token])) {
        $refreshToken = $tokens[$token]['refresh'] ?? null;
        unset($tokens[$token]);
        if ($refreshToken && isset($tokens[$refreshToken])) {
            unset($tokens[$refreshToken]);
        }
        saveTokens($tokens);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    exit;
}

// Get current user endpoint
if ($method === 'GET' && $path === '/auth/me') {
    if (!$requiresAuth || !$tokenData) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $tokenData['user']
    ]);
    exit;
}

// Dashboard endpoints
if ($method === 'GET' && strpos($path, '/dashboard/') === 0) {
    $endpoint = str_replace('/dashboard/', '', $path);
    
    switch ($endpoint) {
        case 'metrics':
            echo json_encode([
                'data' => [
                    'totalLeads' => 42,
                    'totalAccounts' => 15,
                    'newLeadsToday' => 3,
                    'pipelineValue' => 125000.00,
                ]
            ]);
            break;
            
        case 'pipeline':
            echo json_encode([
                'data' => [
                    ['stage' => 'Prospecting', 'count' => 12, 'value' => 45000],
                    ['stage' => 'Qualification', 'count' => 8, 'value' => 32000],
                    ['stage' => 'Needs Analysis', 'count' => 5, 'value' => 25000],
                    ['stage' => 'Proposal', 'count' => 3, 'value' => 18000],
                    ['stage' => 'Negotiation', 'count' => 2, 'value' => 15000],
                ]
            ]);
            break;
            
        case 'activities':
            echo json_encode([
                'data' => [
                    'callsToday' => 5,
                    'meetingsToday' => 3,
                    'tasksOverdue' => 7,
                    'upcomingActivities' => []
                ]
            ]);
            break;
            
        case 'cases':
            echo json_encode([
                'data' => [
                    'openCases' => 23,
                    'highPriorityCases' => 5,
                    'avgResolutionTime' => 48.5,
                    'casesByStatus' => [
                        ['status' => 'New', 'count' => 8],
                        ['status' => 'Assigned', 'count' => 10],
                        ['status' => 'Pending Input', 'count' => 5]
                    ]
                ]
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
    exit;
}

// Health check
if ($method === 'GET' && $path === '/health') {
    echo json_encode([
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'checks' => [
            'api' => ['status' => 'ok', 'message' => 'Standalone API is working']
        ]
    ]);
    exit;
}

// Get leads from persisted data or mock data
if (!isset($persistedData['leads']) || empty($persistedData['leads'])) {
    $persistedData['leads'] = isset($mockData['leads']) ? $mockData['leads'] : [];
    file_put_contents($dataFile, json_encode($persistedData));
}
    
    // AI score endpoints for leads
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
    
    if ($method === 'POST' && $path === '/leads/ai-score-batch') {
        $leadIds = $data['lead_ids'] ?? [];
        $results = [];
        foreach ($leadIds as $leadId) {
            $results[$leadId] = [
                'lead_id' => $leadId,
                'score' => rand(65, 95),
                'confidence' => rand(70, 90) / 100,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        echo json_encode(['success' => true, 'data' => $results]);
        exit;
    }
    
    if ($method === 'GET' && preg_match('#^/leads/(\w+)/score-history$#', $path, $matches)) {
        $leadId = $matches[1];
        echo json_encode([
            'success' => true,
            'data' => [
                [
                    'lead_id' => $leadId,
                    'score' => 85,
                    'confidence' => 0.88,
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-7 days'))
                ],
                [
                    'lead_id' => $leadId,
                    'score' => 87,
                    'confidence' => 0.90,
                    'timestamp' => date('Y-m-d H:i:s', strtotime('-3 days'))
                ],
                [
                    'lead_id' => $leadId,
                    'score' => 92,
                    'confidence' => 0.93,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]
        ]);
        exit;
    }

// Simple file storage for persistence
$dataFile = __DIR__ . '/data.json';
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([]));
}
$persistedData = json_decode(file_get_contents($dataFile), true) ?: [];

// Mock data for all modules
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
        ['id' => '3', 'name' => 'Tech Innovators', 'industry' => 'Software', 'type' => 'Customer', 'annualRevenue' => 3000000, 'employees' => 100, 'website' => 'www.techinnovators.com', 'phone' => '555-0102', 'billingCity' => 'Austin', 'billingState' => 'TX', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-20 days'))],
    ],
    'contacts' => [
        ['id' => '1', 'firstName' => 'John', 'lastName' => 'Smith', 'title' => 'CEO', 'email' => 'john.smith@acme.com', 'phone' => '555-0200', 'mobile' => '555-0300', 'accountId' => '1', 'accountName' => 'Acme Corporation', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-28 days'))],
        ['id' => '2', 'firstName' => 'Sarah', 'lastName' => 'Johnson', 'title' => 'VP Sales', 'email' => 'sarah@global.com', 'phone' => '555-0201', 'mobile' => '555-0301', 'accountId' => '2', 'accountName' => 'Global Industries', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-24 days'))],
        ['id' => '3', 'firstName' => 'Mike', 'lastName' => 'Wilson', 'title' => 'CTO', 'email' => 'mike@techinnovators.com', 'phone' => '555-0202', 'mobile' => '555-0302', 'accountId' => '3', 'accountName' => 'Tech Innovators', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-19 days'))],
    ],
    'opportunities' => [
        ['id' => '1', 'name' => 'Acme Cloud Migration', 'accountName' => 'Acme Corporation', 'amount' => 150000, 'salesStage' => 'Proposal', 'probability' => 70, 'closeDate' => date('Y-m-d', strtotime('+30 days')), 'description' => 'Cloud infrastructure migration project', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-15 days'))],
        ['id' => '2', 'name' => 'Global ERP Implementation', 'accountName' => 'Global Industries', 'amount' => 500000, 'salesStage' => 'Negotiation', 'probability' => 85, 'closeDate' => date('Y-m-d', strtotime('+15 days')), 'description' => 'Enterprise resource planning system', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-10 days'))],
        ['id' => '3', 'name' => 'Tech Innovators Security Audit', 'accountName' => 'Tech Innovators', 'amount' => 75000, 'salesStage' => 'Qualification', 'probability' => 40, 'closeDate' => date('Y-m-d', strtotime('+45 days')), 'description' => 'Comprehensive security assessment', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-5 days'))],
    ],
    'tasks' => [
        ['id' => '1', 'name' => 'Follow up with John Smith', 'status' => 'Not Started', 'priority' => 'High', 'dueDate' => date('Y-m-d', strtotime('+2 days')), 'assignedTo' => 'Admin User', 'relatedTo' => 'Acme Corporation', 'description' => 'Discuss cloud migration timeline', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-1 day'))],
        ['id' => '2', 'name' => 'Prepare ERP proposal', 'status' => 'In Progress', 'priority' => 'High', 'dueDate' => date('Y-m-d', strtotime('+5 days')), 'assignedTo' => 'Admin User', 'relatedTo' => 'Global Industries', 'description' => 'Complete pricing and timeline', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-3 days'))],
        ['id' => '3', 'name' => 'Schedule security audit kickoff', 'status' => 'Not Started', 'priority' => 'Medium', 'dueDate' => date('Y-m-d', strtotime('+7 days')), 'assignedTo' => 'Admin User', 'relatedTo' => 'Tech Innovators', 'description' => 'Set up initial meeting', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-2 days'))],
    ],
    'calls' => [
        ['id' => '1', 'name' => 'Initial discovery call', 'status' => 'Held', 'direction' => 'Outbound', 'duration' => '30 min', 'startDate' => date('Y-m-d H:i:s', strtotime('-7 days')), 'assignedTo' => 'Admin User', 'relatedTo' => 'John Smith', 'description' => 'Discussed cloud migration needs', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-7 days'))],
        ['id' => '2', 'name' => 'ERP requirements review', 'status' => 'Planned', 'direction' => 'Inbound', 'duration' => '60 min', 'startDate' => date('Y-m-d H:i:s', strtotime('+3 days')), 'assignedTo' => 'Admin User', 'relatedTo' => 'Sarah Johnson', 'description' => 'Review technical requirements', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-1 day'))],
    ],
    'meetings' => [
        ['id' => '1', 'name' => 'Quarterly Business Review', 'status' => 'Held', 'location' => 'Conference Room A', 'duration' => '2 hours', 'startDate' => date('Y-m-d H:i:s', strtotime('-14 days')), 'endDate' => date('Y-m-d H:i:s', strtotime('-14 days +2 hours')), 'assignedTo' => 'Admin User', 'relatedTo' => 'Acme Corporation', 'description' => 'Review Q3 performance', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-20 days'))],
        ['id' => '2', 'name' => 'ERP Demo Session', 'status' => 'Planned', 'location' => 'Virtual - Zoom', 'duration' => '90 min', 'startDate' => date('Y-m-d H:i:s', strtotime('+7 days')), 'endDate' => date('Y-m-d H:i:s', strtotime('+7 days +90 minutes')), 'assignedTo' => 'Admin User', 'relatedTo' => 'Global Industries', 'description' => 'Live demo of ERP features', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-2 days'))],
    ],
    'cases' => [
        ['id' => '1', 'name' => 'Login issues reported', 'status' => 'Open', 'priority' => 'High', 'type' => 'Technical', 'accountName' => 'Acme Corporation', 'assignedTo' => 'Admin User', 'description' => 'Users unable to login to portal', 'resolution' => '', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ['id' => '2', 'name' => 'Feature request - Reporting', 'status' => 'In Progress', 'priority' => 'Medium', 'type' => 'Feature Request', 'accountName' => 'Tech Innovators', 'assignedTo' => 'Admin User', 'description' => 'Need custom reporting dashboard', 'resolution' => '', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-5 days'))],
        ['id' => '3', 'name' => 'Billing discrepancy', 'status' => 'Closed', 'priority' => 'Low', 'type' => 'Billing', 'accountName' => 'Global Industries', 'assignedTo' => 'Admin User', 'description' => 'Invoice amount incorrect', 'resolution' => 'Credit issued', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-10 days'))],
    ],
    'notes' => [
        ['id' => '1', 'name' => 'Meeting notes - Acme', 'description' => 'Discussed Q4 expansion plans. They are interested in adding 50 more licenses.', 'relatedTo' => 'Acme Corporation', 'createdBy' => 'Admin User', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-3 days'))],
        ['id' => '2', 'name' => 'Call notes - Global Industries', 'description' => 'Confirmed budget approval for ERP project. Moving to contract negotiation.', 'relatedTo' => 'Global Industries', 'createdBy' => 'Admin User', 'dateEntered' => date('Y-m-d H:i:s', strtotime('-1 day'))],
    ]
];

// Handle all module endpoints
$modules = ['accounts', 'contacts', 'opportunities', 'tasks', 'calls', 'meetings', 'cases', 'notes', 'leads'];
foreach ($modules as $module) {
    if ($path === '/' . $module || strpos($path, '/' . $module . '/') === 0) {
        // Get module data - use persisted data if exists, otherwise use mock
        $moduleData = $persistedData[$module] ?? $mockData[$module] ?? [];
        
        if ($method === 'GET' && $path === '/' . $module) {
            echo json_encode([
                'data' => $moduleData,
                'pagination' => [
                    'page' => 1,
                    'pageSize' => 10,
                    'totalPages' => 1,
                    'totalItems' => count($moduleData)
                ]
            ]);
            exit;
        }
        
        // Get single item
        if ($method === 'GET' && preg_match("#^/$module/(\w+)$#", $path, $matches)) {
            $itemId = $matches[1];
            $items = $mockData[$module] ?? [];
            foreach ($items as $item) {
                if ($item['id'] === $itemId) {
                    echo json_encode(['data' => $item, 'success' => true]);
                    exit;
                }
            }
            // Return first item as fallback
            echo json_encode(['data' => $items[0] ?? ['id' => $itemId, 'name' => 'Sample ' . ucfirst($module)], 'success' => true]);
            exit;
        }
        
        // Create new item
        if ($method === 'POST' && $path === '/' . $module) {
            $newItem = array_merge(['id' => uniqid(), 'dateEntered' => date('Y-m-d H:i:s'), 'dateModified' => date('Y-m-d H:i:s')], $data ?? []);
            
            // Add to persisted data
            if (!isset($persistedData[$module])) {
                $persistedData[$module] = $moduleData;
            }
            $persistedData[$module][] = $newItem;
            
            // Save to file
            file_put_contents($dataFile, json_encode($persistedData));
            
            echo json_encode(['data' => $newItem, 'success' => true]);
            exit;
        }
        
        // Update item
        if ($method === 'PUT' && preg_match("#^/$module/(\w+)$#", $path, $matches)) {
            $itemId = $matches[1];
            
            // Update in persisted data
            if (!isset($persistedData[$module])) {
                $persistedData[$module] = $moduleData;
            }
            
            $found = false;
            foreach ($persistedData[$module] as &$item) {
                if ($item['id'] === $itemId) {
                    $item = array_merge($item, $data ?? [], ['id' => $itemId, 'dateModified' => date('Y-m-d H:i:s')]);
                    $found = true;
                    $updatedItem = $item;
                    break;
                }
            }
            
            if (!$found) {
                // Create new if not found
                $updatedItem = array_merge(['id' => $itemId, 'dateModified' => date('Y-m-d H:i:s')], $data ?? []);
                $persistedData[$module][] = $updatedItem;
            }
            
            // Save to file
            file_put_contents($dataFile, json_encode($persistedData));
            
            echo json_encode(['data' => $updatedItem, 'success' => true]);
            exit;
        }
        
        // Delete item
        if ($method === 'DELETE' && preg_match("#^/$module/(\w+)$#", $path, $matches)) {
            echo json_encode(['success' => true]);
            exit;
        }
    }
}

// Activity tracking endpoints
if ($path === '/track/pageview' || $path === '/track/event' || $path === '/track/page-exit' || $path === '/track/conversion') {
    // For tracking endpoints, just return success
    echo json_encode([
        'success' => true,
        'visitor_id' => $data['visitor_id'] ?? 'visitor-' . uniqid(),
        'session_id' => $data['session_id'] ?? 'session-' . uniqid()
    ]);
    exit;
}

// Activity tracking identify endpoint
if ($method === 'POST' && $path === '/track/identify') {
    echo json_encode([
        'success' => true,
        'message' => 'Visitor identified'
    ]);
    exit;
}

// Analytics endpoints (authenticated)
if (strpos($path, '/analytics/') === 0) {
    $endpoint = str_replace('/analytics/', '', $path);
    
    if ($endpoint === 'visitors' && $method === 'GET') {
        echo json_encode([
            'data' => [
                [
                    'id' => '1',
                    'visitor_id' => 'visitor-123',
                    'ip_address' => '192.168.1.1',
                    'start_time' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                    'last_activity' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                    'page_views' => 12,
                    'duration' => 1500,
                    'is_active' => true,
                    'location' => 'San Francisco, CA',
                    'device' => 'Desktop',
                    'browser' => 'Chrome',
                    'current_page' => '/app/leads'
                ],
                [
                    'id' => '2',
                    'visitor_id' => 'visitor-456',
                    'ip_address' => '10.0.0.1',
                    'start_time' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                    'last_activity' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'page_views' => 5,
                    'duration' => 600,
                    'is_active' => false,
                    'location' => 'New York, NY',
                    'device' => 'Mobile',
                    'browser' => 'Safari',
                    'current_page' => '/pricing'
                ]
            ],
            'total' => 2,
            'page' => 1,
            'limit' => 10
        ]);
        exit;
    }
    
    if ($endpoint === 'visitors/live' && $method === 'GET') {
        echo json_encode([
            'data' => [
                [
                    'id' => '1',
                    'visitor_id' => 'visitor-123',
                    'current_page' => '/app/dashboard',
                    'duration' => 120,
                    'location' => 'San Francisco, CA'
                ]
            ]
        ]);
        exit;
    }
    
    if ($endpoint === 'pages' && $method === 'GET') {
        echo json_encode([
            'data' => [
                'total_views' => 1250,
                'unique_visitors' => 342,
                'average_time_on_page' => 145,
                'bounce_rate' => 35.5,
                'exit_rate' => 28.3,
                'average_scroll_depth' => 67,
                'top_referrers' => [
                    ['source' => 'google.com', 'count' => 450],
                    ['source' => 'direct', 'count' => 380],
                    ['source' => 'linkedin.com', 'count' => 120]
                ],
                'device_breakdown' => [
                    ['type' => 'Desktop', 'count' => 750, 'percentage' => 60],
                    ['type' => 'Mobile', 'count' => 375, 'percentage' => 30],
                    ['type' => 'Tablet', 'count' => 125, 'percentage' => 10]
                ]
            ]
        ]);
        exit;
    }
}

// AI endpoints
if ($path === '/ai/chat' && $method === 'POST') {
    $message = strtolower($data['message'] ?? '');
    $context = $data['context'] ?? [];
    $visitorId = $data['visitor_id'] ?? null;
    $metadata = [];
    $intent = 'general';
    
    // Simple keyword-based responses
    $response = 'I can help you with our CRM features, pricing, and scheduling demos. What would you like to know?';
    $suggestedActions = [
        ['label' => 'Learn about features', 'action' => 'features'],
        ['label' => 'View pricing', 'action' => 'pricing'],
        ['label' => 'Schedule a demo', 'action' => 'demo']
    ];
    
    if (strpos($message, 'lead') !== false || strpos($message, 'score') !== false) {
        $response = 'Our AI Lead Scoring automatically analyzes leads based on multiple factors including company size, engagement, and behavior. Leads are scored from 0-100, with anything above 70 considered high priority. Would you like to see it in action?';
        $suggestedActions = [
            ['label' => 'See lead scoring demo', 'action' => 'demo'],
            ['label' => 'Learn more about AI features', 'action' => 'ai-features'],
            ['label' => 'View pricing', 'action' => 'pricing']
        ];
    } elseif (strpos($message, 'price') !== false || strpos($message, 'cost') !== false) {
        $response = 'Our CRM is completely free and open-source! You can self-host it on your own servers with no licensing fees. The only costs are your hosting infrastructure. Would you like help getting started?';
        $suggestedActions = [
            ['label' => 'Get started guide', 'action' => 'get-started'],
            ['label' => 'Technical requirements', 'action' => 'requirements'],
            ['label' => 'Schedule demo', 'action' => 'demo']
        ];
    } elseif (strpos($message, 'demo') !== false || strpos($message, 'trial') !== false) {
        $response = 'I\'d be happy to help you schedule a personalized demo! You can book a time that works for you, or I can answer any specific questions you have right now.';
        $suggestedActions = [
            ['label' => 'Book a demo', 'action' => 'demo'],
            ['label' => 'Ask a question', 'action' => 'question'],
            ['label' => 'View features', 'action' => 'features']
        ];
    } elseif (strpos($message, 'feature') !== false) {
        $response = 'Our key features include:\n• AI-powered lead scoring\n• Real-time activity tracking\n• Drag-and-drop pipeline management\n• Custom form builder\n• Knowledge base\n• Customer health monitoring\n\nWhich feature interests you most?';
        $suggestedActions = [
            ['label' => 'AI Lead Scoring', 'action' => 'lead-scoring'],
            ['label' => 'Activity Tracking', 'action' => 'tracking'],
            ['label' => 'See all features', 'action' => 'features']
        ];
    } elseif (strpos($message, 'support') !== false || strpos($message, 'help') !== false || strpos($message, 'issue') !== false || strpos($message, 'problem') !== false || strpos($message, 'bug') !== false) {
        // Check if the message is just mentioning support or actually describing an issue
        if (strpos($message, 'create') !== false && strpos($message, 'ticket') !== false) {
            // User explicitly wants to create a ticket
            $response = 'I\'ll help you create a support ticket. Please describe your issue in detail and I\'ll create it for you right away.';
            $intent = 'support_create';
        } elseif (strlen($message) > 50 && (strpos($message, 'not working') !== false || strpos($message, 'error') !== false || strpos($message, 'broken') !== false || strpos($message, 'can\'t') !== false || strpos($message, 'cannot') !== false)) {
            // User is describing a specific issue - auto-create ticket
            $newCase = [
                'id' => uniqid(),
                'name' => 'Support Request: ' . substr($message, 0, 50) . '...',
                'description' => $message,
                'status' => 'New',
                'priority' => 'Medium',
                'type' => 'technical',
                'assignedTo' => 'Support Team',
                'contactEmail' => $visitorId ? 'visitor-' . $visitorId . '@chat.example.com' : 'chat@example.com',
                'createdAt' => date('Y-m-d H:i:s')
            ];
            
            // Add to persisted cases
            if (!isset($persistedData['cases'])) {
                $persistedData['cases'] = [];
            }
            $persistedData['cases'][] = $newCase;
            file_put_contents($dataFile, json_encode($persistedData));
            
            $response = 'I\'ve created support ticket #' . $newCase['id'] . ' for your issue. Our support team will respond within 24 hours.\n\nTicket Summary:\n' . $newCase['name'] . '\n\nIn the meantime, you might find these resources helpful:';
            $intent = 'support_created';
            $suggestedActions = [
                ['label' => 'View knowledge base', 'action' => 'kb'],
                ['label' => 'Check ticket status', 'action' => 'ticket-status'],
                ['label' => 'Chat with sales', 'action' => 'sales']
            ];
            $metadata['ticket_created'] = true;
            $metadata['ticket_id'] = $newCase['id'];
        } else {
            // General support inquiry
            $response = 'I\'m sorry to hear you\'re experiencing an issue. I can help you create a support ticket right away. Would you like to:\n\n1. Create a support ticket through me\n2. Visit our support page\n3. Check our knowledge base first\n\nJust let me know what the issue is and I\'ll help you get it resolved!';
            $suggestedActions = [
                ['label' => 'Create support ticket', 'action' => 'create-ticket'],
                ['label' => 'Visit support page', 'action' => 'support'],
                ['label' => 'Search knowledge base', 'action' => 'kb']
            ];
        }
    }
    
    // Check if this looks like a lead capture opportunity
    $leadSignals = ['interested', 'contact', 'email', 'call', 'help', 'more info', 'information'];
    $isLeadCapture = false;
    foreach ($leadSignals as $signal) {
        if (strpos($message, $signal) !== false) {
            $isLeadCapture = true;
            break;
        }
    }
    
    // Build response
    $responseData = [
        'success' => true,
        'conversation_id' => $data['conversation_id'] ?? uniqid('conv_'),
        'message' => $response,
        'intent' => $intent ?? 'general',
        'suggested_actions' => array_map(function($action) {
            return $action['label'];
        }, $suggestedActions),
        'sentiment' => 'positive',
        'confidence' => 0.9,
        'metadata' => array_merge([
            'lead_captured' => $isLeadCapture
        ], $metadata ?? [])
    ];
    
    echo json_encode($responseData);
    exit;
}

if ($path === '/ai/score-lead' && $method === 'POST') {
    echo json_encode([
        'success' => true,
        'score' => rand(60, 95),
        'factors' => [
            ['factor' => 'Company Size', 'weight' => 0.3, 'score' => 85],
            ['factor' => 'Industry Match', 'weight' => 0.25, 'score' => 90],
            ['factor' => 'Engagement', 'weight' => 0.25, 'score' => 70],
            ['factor' => 'Budget', 'weight' => 0.2, 'score' => 80]
        ]
    ]);
    exit;
}

// AI Create Support Ticket
if ($path === '/ai/create-ticket' && $method === 'POST') {
    $issue = $data['issue'] ?? '';
    $userInfo = $data['userInfo'] ?? [];
    
    // Create a case in the system
    $newCase = [
        'id' => uniqid(),
        'name' => 'Support Request: ' . substr($issue, 0, 50) . '...',
        'description' => $issue,
        'status' => 'New',
        'priority' => 'Medium',
        'type' => 'Technical',
        'accountName' => $userInfo['name'] ?? 'Website Visitor',
        'contactEmail' => $userInfo['email'] ?? '',
        'dateEntered' => date('Y-m-d H:i:s'),
        'source' => 'AI Chat'
    ];
    
    // Add to persisted cases
    if (!isset($persistedData['cases'])) {
        $persistedData['cases'] = [];
    }
    $persistedData['cases'][] = $newCase;
    file_put_contents($dataFile, json_encode($persistedData));
    
    echo json_encode([
        'success' => true,
        'ticketId' => $newCase['id'],
        'message' => 'I\'ve created support ticket #' . $newCase['id'] . ' for you. Our team will respond within 24 hours.',
        'ticket' => $newCase
    ]);
    exit;
}

// Form builder endpoints
if (strpos($path, '/forms') === 0) {
    if ($method === 'GET' && $path === '/forms') {
        echo json_encode([
            'data' => [
                [
                    'id' => '1',
                    'name' => 'Contact Form',
                    'description' => 'General contact form for website visitors',
                    'status' => 'active',
                    'submissions' => 45,
                    'fields' => [
                        ['type' => 'text', 'name' => 'firstName', 'label' => 'First Name', 'required' => true],
                        ['type' => 'text', 'name' => 'lastName', 'label' => 'Last Name', 'required' => true],
                        ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true],
                        ['type' => 'textarea', 'name' => 'message', 'label' => 'Message', 'required' => false]
                    ],
                    'created_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
                ],
                [
                    'id' => '2',
                    'name' => 'Demo Request',
                    'description' => 'Request a product demo',
                    'status' => 'active',
                    'submissions' => 23,
                    'fields' => [
                        ['type' => 'text', 'name' => 'company', 'label' => 'Company', 'required' => true],
                        ['type' => 'text', 'name' => 'name', 'label' => 'Full Name', 'required' => true],
                        ['type' => 'email', 'name' => 'email', 'label' => 'Work Email', 'required' => true],
                        ['type' => 'select', 'name' => 'employees', 'label' => 'Company Size', 'options' => ['1-10', '11-50', '51-200', '200+'], 'required' => true]
                    ],
                    'created_at' => date('Y-m-d H:i:s', strtotime('-14 days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
                ]
            ],
            'pagination' => [
                'page' => 1,
                'pageSize' => 10,
                'totalPages' => 1,
                'totalItems' => 2
            ]
        ]);
        exit;
    }
    
    // Get single form
    if ($method === 'GET' && preg_match('#^/forms/(\w+)$#', $path, $matches)) {
        $formId = $matches[1];
        echo json_encode([
            'data' => [
                'id' => $formId,
                'name' => 'Sample Form',
                'description' => 'A sample form for demo',
                'status' => 'active',
                'submissions' => 10,
                'fields' => [
                    ['type' => 'text', 'name' => 'name', 'label' => 'Name', 'required' => true],
                    ['type' => 'email', 'name' => 'email', 'label' => 'Email', 'required' => true]
                ],
                'settings' => [
                    'submitButtonText' => 'Submit',
                    'successMessage' => 'Thank you for your submission!',
                    'requireAuth' => false
                ],
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ]
        ]);
        exit;
    }
    
    // Create form
    if ($method === 'POST' && $path === '/forms') {
        $newForm = array_merge([
            'id' => uniqid(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'submissions' => 0,
            'status' => 'draft'
        ], $data ?? []);
        echo json_encode(['data' => $newForm, 'success' => true]);
        exit;
    }
    
    // Update form
    if ($method === 'PUT' && preg_match('#^/forms/(\w+)$#', $path, $matches)) {
        $formId = $matches[1];
        $updatedForm = array_merge([
            'id' => $formId,
            'updated_at' => date('Y-m-d H:i:s')
        ], $data ?? []);
        echo json_encode(['data' => $updatedForm, 'success' => true]);
        exit;
    }
    
    // Delete form
    if ($method === 'DELETE' && preg_match('#^/forms/(\w+)$#', $path, $matches)) {
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Form submissions
    if ($method === 'GET' && preg_match('#^/forms/(\w+)/submissions$#', $path, $matches)) {
        echo json_encode([
            'data' => [
                [
                    'id' => '1',
                    'form_id' => $matches[1],
                    'data' => ['name' => 'John Doe', 'email' => 'john@example.com'],
                    'submitted_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
                ]
            ],
            'pagination' => ['page' => 1, 'pageSize' => 10, 'totalPages' => 1, 'totalItems' => 1]
        ]);
        exit;
    }
    
    // Submit form (public endpoint)
    if ($method === 'POST' && $path === '/forms/submit') {
        echo json_encode([
            'success' => true,
            'message' => 'Form submitted successfully',
            'submission_id' => uniqid()
        ]);
        exit;
    }
}

// Knowledge base endpoints
if (strpos($path, '/kb') === 0 || strpos($path, '/knowledge-base') === 0) {
    // Categories endpoint
    if ($path === '/knowledge-base/categories' || $path === '/kb/categories') {
        echo json_encode([
            'data' => [
                ['id' => '1', 'name' => 'Getting Started', 'slug' => 'getting-started', 'description' => 'Basic guides for new users', 'article_count' => 5],
                ['id' => '2', 'name' => 'Lead Management', 'slug' => 'lead-management', 'description' => 'Managing leads and conversions', 'article_count' => 8],
                ['id' => '3', 'name' => 'Reporting', 'slug' => 'reporting', 'description' => 'Creating and managing reports', 'article_count' => 6],
                ['id' => '4', 'name' => 'AI Features', 'slug' => 'ai-features', 'description' => 'Using AI-powered features', 'article_count' => 4]
            ]
        ]);
        exit;
    }
    
    // Articles endpoint
    if ($method === 'GET' && ($path === '/kb/articles' || $path === '/knowledge-base/articles')) {
        echo json_encode([
            'data' => [
                [
                    'id' => '1',
                    'title' => 'Getting Started with CRM',
                    'content' => '# Getting Started with CRM\n\nWelcome to our CRM system. This guide will help you get started...\n\n## Key Features\n- Lead Management\n- Contact Management\n- AI-Powered Insights\n- Activity Tracking',
                    'category_id' => '1',
                    'category' => 'Getting Started',
                    'slug' => 'getting-started-with-crm',
                    'views' => 234,
                    'helpful' => 45,
                    'not_helpful' => 2,
                    'status' => 'published',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
                ],
                [
                    'id' => '2',
                    'title' => 'How to Score Leads with AI',
                    'content' => '# AI Lead Scoring\n\nOur AI-powered lead scoring helps you prioritize...',
                    'category_id' => '4',
                    'category' => 'AI Features',
                    'slug' => 'ai-lead-scoring',
                    'views' => 189,
                    'helpful' => 67,
                    'not_helpful' => 1,
                    'status' => 'published',
                    'created_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
                    'updated_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
                ]
            ]
        ]);
        exit;
    }
    
    // Single article
    if ($method === 'GET' && preg_match('#^/(?:kb|knowledge-base)/articles/(\w+)$#', $path, $matches)) {
        $articleId = $matches[1];
        echo json_encode([
            'data' => [
                'id' => $articleId,
                'title' => 'Sample Article',
                'content' => '# Sample Article\n\nThis is a sample knowledge base article.',
                'category_id' => '1',
                'category' => 'Getting Started',
                'slug' => 'sample-article',
                'views' => 100,
                'helpful' => 25,
                'not_helpful' => 1,
                'status' => 'published',
                'created_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'updated_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ]
        ]);
        exit;
    }
    
    // Create article
    if ($method === 'POST' && ($path === '/kb/articles' || $path === '/knowledge-base/articles')) {
        $newArticle = array_merge([
            'id' => uniqid(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'views' => 0,
            'helpful' => 0,
            'not_helpful' => 0,
            'status' => 'draft'
        ], $data ?? []);
        echo json_encode(['data' => $newArticle, 'success' => true]);
        exit;
    }
    
    // Update article
    if ($method === 'PUT' && preg_match('#^/(?:kb|knowledge-base)/articles/(\w+)$#', $path, $matches)) {
        $articleId = $matches[1];
        $updatedArticle = array_merge([
            'id' => $articleId,
            'updated_at' => date('Y-m-d H:i:s')
        ], $data ?? []);
        echo json_encode(['data' => $updatedArticle, 'success' => true]);
        exit;
    }
    
    // Delete article
    if ($method === 'DELETE' && preg_match('#^/(?:kb|knowledge-base)/articles/(\w+)$#', $path, $matches)) {
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Search endpoint
    if ($method === 'GET' && ($path === '/kb/search' || $path === '/knowledge-base/search')) {
        echo json_encode([
            'data' => [
                [
                    'id' => '1',
                    'title' => 'How to Create a Lead',
                    'excerpt' => 'Learn how to create and manage leads in the CRM...',
                    'category' => 'Leads',
                    'relevance_score' => 0.95
                ]
            ]
        ]);
        exit;
    }
    
    // Public KB endpoint (for homepage)
    if ($method === 'GET' && ($path === '/kb/public' || $path === '/knowledge-base/public')) {
        echo json_encode([
            'data' => [
                [
                    'id' => '1',
                    'title' => 'Getting Started with AI CRM',
                    'slug' => 'getting-started',
                    'excerpt' => 'Learn how our AI-powered CRM can transform your sales process.',
                    'category' => 'Getting Started',
                    'content' => '# Getting Started\n\nOur AI CRM helps you...'
                ],
                [
                    'id' => '2',
                    'title' => 'Understanding AI Lead Scoring',
                    'slug' => 'ai-lead-scoring',
                    'excerpt' => 'How our AI analyzes and scores your leads automatically.',
                    'category' => 'AI Features',
                    'content' => '# AI Lead Scoring\n\nOur system uses advanced AI...'
                ],
                [
                    'id' => '3',
                    'title' => 'Setting Up Activity Tracking',
                    'slug' => 'activity-tracking',
                    'excerpt' => 'Track visitor behavior and engagement in real-time.',
                    'category' => 'Features',
                    'content' => '# Activity Tracking\n\nMonitor your visitors...'
                ]
            ]
        ]);
        exit;
    }
}

// Customer health endpoints
if (strpos($path, '/customer-health') === 0) {
    if ($path === '/customer-health/metrics' && $method === 'GET') {
        echo json_encode([
            'data' => [
                'overview' => [
                    'total_accounts' => 156,
                    'healthy' => 89,
                    'at_risk' => 45,
                    'critical' => 22
                ],
                'trend' => [
                    ['date' => date('Y-m-d', strtotime('-6 days')), 'healthy' => 85, 'at_risk' => 48, 'critical' => 23],
                    ['date' => date('Y-m-d', strtotime('-5 days')), 'healthy' => 86, 'at_risk' => 47, 'critical' => 23],
                    ['date' => date('Y-m-d', strtotime('-4 days')), 'healthy' => 87, 'at_risk' => 46, 'critical' => 23],
                    ['date' => date('Y-m-d', strtotime('-3 days')), 'healthy' => 88, 'at_risk' => 46, 'critical' => 22],
                    ['date' => date('Y-m-d', strtotime('-2 days')), 'healthy' => 88, 'at_risk' => 45, 'critical' => 23],
                    ['date' => date('Y-m-d', strtotime('-1 days')), 'healthy' => 89, 'at_risk' => 45, 'critical' => 22],
                    ['date' => date('Y-m-d'), 'healthy' => 89, 'at_risk' => 45, 'critical' => 22]
                ]
            ]
        ]);
        exit;
    }
    
    if ($path === '/customer-health/accounts' && $method === 'GET') {
        echo json_encode([
            'data' => [
                [
                    'id' => '1',
                    'name' => 'Acme Corporation',
                    'health_score' => 85,
                    'health_status' => 'healthy',
                    'last_contact' => date('Y-m-d', strtotime('-2 days')),
                    'open_cases' => 0,
                    'total_revenue' => 250000,
                    'engagement_score' => 92,
                    'churn_risk' => 'low',
                    'assigned_to' => 'Admin User'
                ],
                [
                    'id' => '2',
                    'name' => 'Global Industries',
                    'health_score' => 45,
                    'health_status' => 'at_risk',
                    'last_contact' => date('Y-m-d', strtotime('-15 days')),
                    'open_cases' => 3,
                    'total_revenue' => 500000,
                    'engagement_score' => 35,
                    'churn_risk' => 'high',
                    'assigned_to' => 'Admin User'
                ],
                [
                    'id' => '3',
                    'name' => 'Tech Innovators',
                    'health_score' => 25,
                    'health_status' => 'critical',
                    'last_contact' => date('Y-m-d', strtotime('-30 days')),
                    'open_cases' => 5,
                    'total_revenue' => 150000,
                    'engagement_score' => 15,
                    'churn_risk' => 'critical',
                    'assigned_to' => 'Admin User'
                ]
            ],
            'pagination' => [
                'page' => 1,
                'pageSize' => 10,
                'totalPages' => 1,
                'totalItems' => 3
            ]
        ]);
        exit;
    }
    
    if ($method === 'GET' && preg_match('#^/customer-health/accounts/(\w+)$#', $path, $matches)) {
        $accountId = $matches[1];
        echo json_encode([
            'data' => [
                'id' => $accountId,
                'name' => 'Sample Account',
                'health_score' => 75,
                'health_status' => 'healthy',
                'health_factors' => [
                    ['factor' => 'Product Usage', 'score' => 85, 'weight' => 0.3],
                    ['factor' => 'Support Tickets', 'score' => 60, 'weight' => 0.2],
                    ['factor' => 'Payment History', 'score' => 95, 'weight' => 0.2],
                    ['factor' => 'Engagement', 'score' => 70, 'weight' => 0.3]
                ],
                'recommendations' => [
                    'Schedule quarterly business review',
                    'Offer advanced training on new features',
                    'Check in on open support tickets'
                ]
            ]
        ]);
        exit;
    }
}

// Default 404
http_response_code(404);
echo json_encode(['error' => 'Route not found: ' . $method . ' ' . $path]);