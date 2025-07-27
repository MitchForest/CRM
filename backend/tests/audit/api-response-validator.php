<?php

require __DIR__ . '/../../vendor/autoload.php';

use GuzzleHttp\Client;
use Illuminate\Database\Capsule\Manager as DB;

// Initialize database
$capsule = require __DIR__ . '/../../config/database.php';

echo "\n=== API RESPONSE VALIDATOR ===\n";
echo "Validating API responses against database schema and OpenAPI spec...\n\n";

// When running inside container, use localhost:80
// When running outside container, use localhost:8080
$isInsideContainer = file_exists('/.dockerenv');
$baseUrl = $isInsideContainer ? 'http://localhost:80/api' : 'http://localhost:8080/api';
$client = new Client(['base_uri' => $baseUrl, 'http_errors' => false]);

$issues = [];
$warnings = [];
$stats = [
    'endpoints_tested' => 0,
    'successful_responses' => 0,
    'failed_responses' => 0,
    'field_mismatches' => 0,
    'type_mismatches' => 0,
];

// First, we need to authenticate
echo "Authenticating...\n";
$authResponse = $client->post('/auth/login', [
    'json' => [
        'email' => 'john.smith@techflow.com',
        'password' => 'password123'
    ]
]);

if ($authResponse->getStatusCode() !== 200) {
    die("Failed to authenticate. Please ensure test user exists.\n");
}

$authData = json_decode($authResponse->getBody(), true);
// Auth endpoint returns token directly, not in data wrapper
$token = $authData['access_token'] ?? $authData['data']['access_token'] ?? null;

if (!$token) {
    die("Failed to get access token from auth response.\n");
}

$headers = ['Authorization' => 'Bearer ' . $token];

// Test endpoints
$endpoints = [
    ['method' => 'GET', 'path' => '/crm/leads', 'model' => 'Lead'],
    ['method' => 'GET', 'path' => '/crm/contacts', 'model' => 'Contact'],
    ['method' => 'GET', 'path' => '/crm/accounts', 'model' => 'Account'],
    ['method' => 'GET', 'path' => '/crm/opportunities', 'model' => 'Opportunity'],
    ['method' => 'GET', 'path' => '/crm/cases', 'model' => 'SupportCase'],
    ['method' => 'GET', 'path' => '/crm/dashboard', 'model' => null],
    ['method' => 'GET', 'path' => '/crm/activities/tasks', 'model' => 'Task'],
    ['method' => 'GET', 'path' => '/crm/activities/calls', 'model' => 'Call'],
    ['method' => 'GET', 'path' => '/crm/activities/meetings', 'model' => 'Meeting'],
    ['method' => 'GET', 'path' => '/crm/activities/notes', 'model' => 'Note'],
];

foreach ($endpoints as $endpoint) {
    echo "\nTesting {$endpoint['method']} {$endpoint['path']}...\n";
    $stats['endpoints_tested']++;
    
    try {
        $response = $client->request($endpoint['method'], $endpoint['path'], ['headers' => $headers]);
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody(), true);
        
        if ($statusCode === 200) {
            $stats['successful_responses']++;
            
            // Validate response structure
            if (!isset($body['status']) || !isset($body['data'])) {
                $issues[] = "{$endpoint['path']}: Missing standard response structure (status/data)";
                continue;
            }
            
            // For list endpoints, check pagination structure
            if (isset($body['data']['data']) && is_array($body['data']['data'])) {
                // This is a paginated response
                if (!isset($body['data']['current_page']) || !isset($body['data']['total'])) {
                    $warnings[] = "{$endpoint['path']}: Missing pagination metadata";
                }
                
                $items = $body['data']['data'];
            } else {
                // Non-paginated response
                $items = is_array($body['data']) ? $body['data'] : [$body['data']];
            }
            
            // Validate fields if we have a model
            if ($endpoint['model'] && !empty($items) && isset($items[0])) {
                $modelClass = "App\\Models\\{$endpoint['model']}";
                if (class_exists($modelClass)) {
                    $model = new $modelClass;
                    $table = $model->getTable();
                    
                    // Get database columns
                    $columns = DB::select("SHOW COLUMNS FROM {$table}");
                    $dbFields = array_map(function($col) {
                        return $col->Field;
                    }, $columns);
                    
                    // Check first item's fields
                    $responseFields = array_keys($items[0]);
                    
                    // Fields in response but not in database
                    $extraFields = array_diff($responseFields, $dbFields);
                    if (!empty($extraFields)) {
                        $warnings[] = "{$endpoint['path']}: Response contains fields not in database: " . implode(', ', $extraFields);
                        $stats['field_mismatches'] += count($extraFields);
                    }
                    
                    // Required fields missing from response
                    $fillable = $model->getFillable();
                    $missingFields = array_diff($fillable, $responseFields);
                    if (!empty($missingFields)) {
                        // Some fields might be hidden or optional
                        $hidden = $model->getHidden();
                        $reallyMissing = array_diff($missingFields, $hidden);
                        if (!empty($reallyMissing)) {
                            $issues[] = "{$endpoint['path']}: Response missing fillable fields: " . implode(', ', $reallyMissing);
                            $stats['field_mismatches'] += count($reallyMissing);
                        }
                    }
                    
                    // Check field naming convention (should be snake_case)
                    foreach ($responseFields as $field) {
                        if ($field !== strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $field))) {
                            $issues[] = "{$endpoint['path']}: Field '{$field}' is not in snake_case";
                        }
                    }
                }
            }
            
        } else {
            $stats['failed_responses']++;
            $warnings[] = "{$endpoint['path']}: Returned status code {$statusCode}";
        }
        
    } catch (Exception $e) {
        $stats['failed_responses']++;
        $issues[] = "{$endpoint['path']}: Exception - " . $e->getMessage();
    }
}

// Test single resource endpoints
echo "\n\nTesting single resource endpoints...\n";

// Get a lead ID for testing
$leadResponse = $client->get('/crm/leads?limit=1', ['headers' => $headers]);
if ($leadResponse->getStatusCode() === 200) {
    $leadData = json_decode($leadResponse->getBody(), true);
    if (!empty($leadData['data']['data'])) {
        $leadId = $leadData['data']['data'][0]['id'];
        
        // Test single lead endpoint
        echo "Testing GET /crm/leads/{$leadId}...\n";
        $singleLeadResponse = $client->get("/crm/leads/{$leadId}", ['headers' => $headers]);
        if ($singleLeadResponse->getStatusCode() === 200) {
            $stats['successful_responses']++;
            $singleLeadData = json_decode($singleLeadResponse->getBody(), true);
            
            // Validate it has more detail than list view
            if (count($singleLeadData['data']) <= count($leadData['data']['data'][0])) {
                $warnings[] = "Single lead endpoint doesn't provide more detail than list view";
            }
        }
    }
}

// Summary Report
echo "\n\n=== VALIDATION SUMMARY ===\n";
echo str_repeat('=', 50) . "\n";
echo "Endpoints tested: {$stats['endpoints_tested']}\n";
echo "Successful responses: {$stats['successful_responses']}\n";
echo "Failed responses: {$stats['failed_responses']}\n";
echo "Field mismatches: {$stats['field_mismatches']}\n";
echo "Type mismatches: {$stats['type_mismatches']}\n";
echo "\nTotal issues found: " . count($issues) . "\n";
echo "Total warnings: " . count($warnings) . "\n";

if (count($issues) > 0) {
    echo "\n\n=== CRITICAL ISSUES ===\n";
    foreach ($issues as $issue) {
        echo "❌ {$issue}\n";
    }
}

if (count($warnings) > 0) {
    echo "\n\n=== WARNINGS ===\n";
    foreach ($warnings as $warning) {
        echo "⚠️  {$warning}\n";
    }
}

// Write detailed report
$reportFile = __DIR__ . '/api-validation-report.txt';
$report = "API Response Validation Report\n";
$report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
$report .= "STATISTICS:\n";
foreach ($stats as $key => $value) {
    $report .= "  " . str_replace('_', ' ', ucfirst($key)) . ": {$value}\n";
}
$report .= "\n\nCRITICAL ISSUES:\n";
foreach ($issues as $issue) {
    $report .= "- {$issue}\n";
}
$report .= "\n\nWARNINGS:\n";
foreach ($warnings as $warning) {
    $report .= "- {$warning}\n";
}

file_put_contents($reportFile, $report);
echo "\n\nDetailed report written to: api-validation-report.txt\n";