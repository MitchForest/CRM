# Practical Testing Plan - 80/20 Approach

## Goal: Verify the App Actually Works

No unit tests, no mocks, no testing theater. Just real API calls that prove the system works end-to-end.

## 1. Quick Test Setup (30 minutes)

```bash
# Create test database
docker exec sassycrm-mysql mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS crm_test;"

# Copy schema
docker exec sassycrm-mysql mysqldump -u root -proot crm --no-data | docker exec -i sassycrm-mysql mysql -u root -proot crm_test

# Add test user
docker exec sassycrm-mysql mysql -u root -proot crm_test -e "
INSERT INTO users (id, user_name, user_hash, first_name, last_name, email1, status, is_admin, deleted) 
VALUES ('test-user-1', 'admin', '\$2y\$10\$X5QzqygM8CA6j7Y2rpmYl.3pJ9MYJb4J9CxvLpPaNmW9DxzN8rGXO', 'Test', 'Admin', 'admin@test.com', 'Active', 1, 0);
"
```

## 2. Critical Path API Tests (tests/Integration/CriticalPathTest.php)

```php
<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class CriticalPathTest extends TestCase
{
    private $baseUrl = 'http://localhost:8080/api';
    private $token;
    private $createdLeadId;
    
    public function testCompleteUserJourney()
    {
        // 1. Can I log in?
        $this->testLogin();
        
        // 2. Can I create a lead with the actual field names we use?
        $this->testCreateLead();
        
        // 3. Can I retrieve that lead and get the data back correctly?
        $this->testGetLead();
        
        // 4. Can I update the lead?
        $this->testUpdateLead();
        
        // 5. Can I see it in the list?
        $this->testListLeads();
        
        // 6. Can I convert it to a contact?
        $this->testConvertLead();
        
        // 7. Does the dashboard show correct metrics?
        $this->testDashboardMetrics();
    }
    
    private function testLogin()
    {
        $response = $this->apiCall('POST', '/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'admin'
        ]);
        
        $this->assertArrayHasKey('access_token', $response);
        $this->assertArrayHasKey('user', $response);
        $this->token = $response['access_token'];
        
        echo "✅ Login works\n";
    }
    
    private function testCreateLead()
    {
        $response = $this->apiCall('POST', '/crm/leads', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email1' => 'john@example.com',
            'phone_work' => '555-1234',
            'account_name' => 'Acme Corp',
            'lead_source' => 'website'
        ]);
        
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('john@example.com', $response['data']['email1']);
        $this->createdLeadId = $response['data']['id'];
        
        echo "✅ Lead creation works with correct field names\n";
    }
    
    private function testGetLead()
    {
        $response = $this->apiCall('GET', "/crm/leads/{$this->createdLeadId}");
        
        $this->assertEquals('John', $response['data']['first_name']);
        $this->assertEquals('555-1234', $response['data']['phone_work']);
        
        echo "✅ Lead retrieval works\n";
    }
    
    private function testUpdateLead()
    {
        $response = $this->apiCall('PUT', "/crm/leads/{$this->createdLeadId}", [
            'status' => 'qualified',
            'ai_score' => 0.85
        ]);
        
        $updated = $this->apiCall('GET', "/crm/leads/{$this->createdLeadId}");
        $this->assertEquals('qualified', $updated['data']['status']);
        $this->assertEquals(0.85, $updated['data']['ai_score']);
        
        echo "✅ Lead update works\n";
    }
    
    private function testListLeads()
    {
        $response = $this->apiCall('GET', '/crm/leads?page=1&limit=10');
        
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('pagination', $response);
        $this->assertGreaterThan(0, count($response['data']));
        
        // Verify our lead is in the list
        $found = false;
        foreach ($response['data'] as $lead) {
            if ($lead['id'] === $this->createdLeadId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Created lead should be in list');
        
        echo "✅ Lead listing with pagination works\n";
    }
    
    private function testConvertLead()
    {
        $response = $this->apiCall('POST', "/crm/leads/{$this->createdLeadId}/convert");
        
        $this->assertArrayHasKey('contact_id', $response['data']);
        
        // Verify contact was created
        $contact = $this->apiCall('GET', "/crm/contacts/{$response['data']['contact_id']}");
        $this->assertEquals('John', $contact['data']['first_name']);
        
        echo "✅ Lead to contact conversion works\n";
    }
    
    private function testDashboardMetrics()
    {
        $response = $this->apiCall('GET', '/crm/dashboard/metrics');
        
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('total_leads', $response['data']);
        $this->assertArrayHasKey('total_contacts', $response['data']);
        $this->assertGreaterThan(0, $response['data']['total_leads']);
        
        echo "✅ Dashboard metrics work\n";
    }
    
    private function apiCall($method, $endpoint, $data = null)
    {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        $headers = ['Content-Type: application/json'];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new \Exception("API call failed: $method $endpoint returned $httpCode - $response");
        }
        
        return json_decode($response, true);
    }
}
```

## 3. Run the Test

```bash
cd backend
./vendor/bin/phpunit tests/Integration/CriticalPathTest.php --verbose
```

## 4. What This Tests

1. **Authentication**: Can users actually log in and get tokens?
2. **Field Names**: Are we using the correct snake_case fields everywhere?
3. **CRUD Operations**: Can we create, read, update, delete?
4. **Business Logic**: Does lead conversion actually work?
5. **API Consistency**: Do responses match what frontend expects?
6. **Real Database**: No mocks, real MySQL queries

## 5. Frontend Quick Test

```javascript
// Quick browser console test
const testAPI = async () => {
  // Login
  const loginRes = await fetch('/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'admin@test.com', password: 'admin' })
  });
  const { access_token } = await loginRes.json();
  
  // Create lead
  const leadRes = await fetch('/api/crm/leads', {
    method: 'POST',
    headers: { 
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${access_token}`
    },
    body: JSON.stringify({
      first_name: 'Jane',
      last_name: 'Smith',
      email1: 'jane@example.com',
      phone_work: '555-5678'
    })
  });
  
  console.log('Lead created:', await leadRes.json());
};

testAPI();
```

## 6. What We're NOT Testing

- Unit tests for getters/setters
- Mock objects
- 100% code coverage
- Edge cases that never happen
- Testing framework features

## 7. Success Criteria

✅ Can log in and get a token
✅ Can create a lead with real field names
✅ Can retrieve and update that lead
✅ Can see it in paginated lists
✅ Can convert lead to contact
✅ Dashboard shows real metrics
✅ Frontend can call all APIs successfully

If these work, the app works. Ship it.

## Total Time: 2-3 hours

Not 5 days. Not 100 test files. Just proof that the critical paths work end-to-end.