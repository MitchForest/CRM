<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Phase2ApiTest extends TestCase
{
    protected static $client;
    protected static $token;
    protected static $baseUrl;
    
    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = getenv('API_BASE_URL') ?: 'http://localhost:8080/custom-api';
        self::$client = new Client([
            'base_uri' => self::$baseUrl,
            'timeout' => 10.0,
            'http_errors' => false,
        ]);
        
        // Authenticate once for all tests
        $response = self::$client->post('/auth/login', [
            'json' => [
                'username' => 'admin',
                'password' => 'admin123',
            ]
        ]);
        
        $data = json_decode($response->getBody(), true);
        self::$token = $data['data']['token'] ?? null;
        
        if (!self::$token) {
            throw new \Exception('Failed to authenticate for tests');
        }
    }
    
    protected function getAuthHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . self::$token,
            'Content-Type' => 'application/json',
        ];
    }
    
    /**
     * Test Dashboard Metrics Endpoint
     */
    public function testDashboardMetrics()
    {
        $response = self::$client->get('/dashboard/metrics', [
            'headers' => $this->getAuthHeaders(),
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total_leads', $data['data']);
        $this->assertArrayHasKey('total_accounts', $data['data']);
        $this->assertArrayHasKey('new_leads_today', $data['data']);
        $this->assertArrayHasKey('pipeline_value', $data['data']);
        
        // Verify data types
        $this->assertIsInt($data['data']['total_leads']);
        $this->assertIsInt($data['data']['total_accounts']);
        $this->assertIsInt($data['data']['new_leads_today']);
        $this->assertIsNumeric($data['data']['pipeline_value']);
    }
    
    /**
     * Test Pipeline Data Endpoint
     */
    public function testPipelineData()
    {
        $response = self::$client->get('/dashboard/pipeline', [
            'headers' => $this->getAuthHeaders(),
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        
        // Verify all stages are present
        $expectedStages = [
            'Qualification',
            'Needs Analysis',
            'Value Proposition',
            'Decision Makers',
            'Proposal',
            'Negotiation',
            'Closed Won',
            'Closed Lost'
        ];
        
        $stages = array_column($data['data'], 'stage');
        foreach ($expectedStages as $stage) {
            $this->assertContains($stage, $stages);
        }
        
        // Verify stage data structure
        foreach ($data['data'] as $stageData) {
            $this->assertArrayHasKey('stage', $stageData);
            $this->assertArrayHasKey('count', $stageData);
            $this->assertArrayHasKey('value', $stageData);
            $this->assertIsInt($stageData['count']);
            $this->assertIsNumeric($stageData['value']);
        }
    }
    
    /**
     * Test Activity Metrics Endpoint
     */
    public function testActivityMetrics()
    {
        $response = self::$client->get('/dashboard/activities', [
            'headers' => $this->getAuthHeaders(),
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('calls_today', $data['data']);
        $this->assertArrayHasKey('meetings_today', $data['data']);
        $this->assertArrayHasKey('tasks_overdue', $data['data']);
        $this->assertArrayHasKey('upcoming_activities', $data['data']);
        
        $this->assertIsInt($data['data']['calls_today']);
        $this->assertIsInt($data['data']['meetings_today']);
        $this->assertIsInt($data['data']['tasks_overdue']);
        $this->assertIsArray($data['data']['upcoming_activities']);
        
        // Verify upcoming activity structure if any exist
        if (!empty($data['data']['upcoming_activities'])) {
            $activity = $data['data']['upcoming_activities'][0];
            $this->assertArrayHasKey('id', $activity);
            $this->assertArrayHasKey('name', $activity);
            $this->assertArrayHasKey('type', $activity);
            $this->assertArrayHasKey('date_start', $activity);
        }
    }
    
    /**
     * Test Case Metrics Endpoint
     */
    public function testCaseMetrics()
    {
        $response = self::$client->get('/dashboard/cases', [
            'headers' => $this->getAuthHeaders(),
        ]);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('open_cases', $data['data']);
        $this->assertArrayHasKey('critical_cases', $data['data']);
        $this->assertArrayHasKey('avg_resolution_time', $data['data']);
        $this->assertArrayHasKey('cases_by_priority', $data['data']);
        
        $this->assertIsInt($data['data']['open_cases']);
        $this->assertIsInt($data['data']['critical_cases']);
        $this->assertIsNumeric($data['data']['avg_resolution_time']);
        $this->assertIsArray($data['data']['cases_by_priority']);
    }
    
    /**
     * Test Email View Endpoint with Invalid ID
     */
    public function testEmailViewInvalidId()
    {
        $response = self::$client->get('/emails/invalid-id/view', [
            'headers' => $this->getAuthHeaders(),
        ]);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid email ID', $data['error']);
    }
    
    /**
     * Test Email View Endpoint with Non-existent ID
     */
    public function testEmailViewNotFound()
    {
        $validButNonExistentId = '12345678-1234-1234-1234-123456789012';
        
        $response = self::$client->get("/emails/{$validButNonExistentId}/view", [
            'headers' => $this->getAuthHeaders(),
        ]);
        
        $this->assertEquals(404, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Email not found', $data['error']);
    }
    
    /**
     * Test Document Download Endpoint with Invalid ID
     */
    public function testDocumentDownloadInvalidId()
    {
        $response = self::$client->get('/documents/invalid-id/download', [
            'headers' => $this->getAuthHeaders(),
        ]);
        
        $this->assertEquals(400, $response->getStatusCode());
        
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid document ID', $data['error']);
    }
    
    /**
     * Test Unauthorized Access
     */
    public function testUnauthorizedAccess()
    {
        $response = self::$client->get('/dashboard/metrics', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        
        $this->assertEquals(401, $response->getStatusCode());
    }
    
    /**
     * Test All Dashboard Endpoints Return Valid JSON
     */
    public function testAllEndpointsReturnValidJson()
    {
        $endpoints = [
            '/dashboard/metrics',
            '/dashboard/pipeline',
            '/dashboard/activities',
            '/dashboard/cases',
        ];
        
        foreach ($endpoints as $endpoint) {
            $response = self::$client->get($endpoint, [
                'headers' => $this->getAuthHeaders(),
            ]);
            
            $this->assertEquals(200, $response->getStatusCode());
            
            $body = (string) $response->getBody();
            $data = json_decode($body, true);
            
            $this->assertNotNull($data, "Endpoint {$endpoint} did not return valid JSON");
            $this->assertArrayHasKey('data', $data, "Endpoint {$endpoint} missing 'data' key");
        }
    }
}