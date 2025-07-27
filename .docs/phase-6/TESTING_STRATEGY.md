# Backend Testing Strategy - Sassy CRM

## Overview
Comprehensive testing strategy to ensure data integrity, API reliability, and prevent frontend integration issues.

## Testing Pyramid

```
         /\
        /  \  E2E Tests (10%)
       /----\  - Full user flows
      /      \  - Critical paths only
     /--------\  Integration Tests (30%)
    /          \  - API endpoint tests
   /            \  - Database operations
  /--------------\  Unit Tests (60%)
 /                \  - Model validation
/                  \  - Service logic
                     - Helper functions
```

## Test Categories

### 1. Model Tests (Unit)

#### Purpose
Validate model behavior, relationships, and data integrity.

#### Test Cases
```php
// tests/Unit/Models/LeadTest.php
class LeadTest extends TestCase {
    /** @test */
    public function it_creates_lead_with_valid_data() {
        $lead = Lead::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email1' => 'john@example.com',
            'phone_work' => '555-1234'
        ]);
        
        $this->assertDatabaseHas('leads', [
            'email1' => 'john@example.com'
        ]);
    }
    
    /** @test */
    public function it_validates_required_fields() {
        $this->expectException(ValidationException::class);
        
        Lead::create([]); // Should fail - last_name required
    }
    
    /** @test */
    public function it_handles_field_mapping_correctly() {
        $lead = new Lead();
        $lead->email = 'test@example.com'; // Uses accessor
        
        $this->assertEquals('test@example.com', $lead->email1);
    }
    
    /** @test */
    public function it_calculates_ai_score_correctly() {
        $lead = Lead::factory()->create();
        LeadScore::create([
            'lead_id' => $lead->id,
            'score' => 0.85
        ]);
        
        $this->assertEquals(0.85, $lead->latest_score);
    }
}
```

### 2. Service Tests (Unit)

#### Purpose
Test business logic in isolation.

#### Test Cases
```php
// tests/Unit/Services/LeadScoringServiceTest.php
class LeadScoringServiceTest extends TestCase {
    private LeadScoringService $service;
    
    protected function setUp(): void {
        parent::setUp();
        $this->service = new LeadScoringService(
            $this->mock(OpenAIService::class)
        );
    }
    
    /** @test */
    public function it_calculates_score_based_on_engagement() {
        $lead = Lead::factory()->create();
        
        // Mock activity data
        ActivityTrackingSession::factory()
            ->count(5)
            ->create(['lead_id' => $lead->id]);
            
        $score = $this->service->calculateScore($lead);
        
        $this->assertGreaterThan(0.5, $score);
    }
}
```

### 3. Controller Tests (Integration)

#### Purpose
Test HTTP requests, responses, and middleware.

#### Test Cases
```php
// tests/Feature/Controllers/LeadsControllerTest.php
class LeadsControllerTest extends TestCase {
    use RefreshDatabase;
    
    private $user;
    
    protected function setUp(): void {
        parent::setUp();
        $this->user = User::factory()->create();
    }
    
    /** @test */
    public function it_returns_paginated_leads() {
        Lead::factory()->count(25)->create();
        
        $response = $this->actingAs($this->user)
            ->getJson('/api/crm/leads?page=1&limit=20');
            
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email1',
                        'ai_score'
                    ]
                ],
                'pagination' => [
                    'total',
                    'page',
                    'limit'
                ]
            ]);
            
        $this->assertCount(20, $response->json('data'));
    }
    
    /** @test */
    public function it_creates_lead_with_field_mapping() {
        $data = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com', // Frontend sends 'email'
            'phone' => '555-5678' // Frontend sends 'phone'
        ];
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/crm/leads', $data);
            
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('leads', [
            'email1' => 'jane@example.com', // Stored as email1
            'phone_work' => '555-5678' // Stored as phone_work
        ]);
    }
    
    /** @test */
    public function it_validates_email_format() {
        $data = [
            'last_name' => 'Invalid',
            'email' => 'not-an-email'
        ];
        
        $response = $this->actingAs($this->user)
            ->postJson('/api/crm/leads', $data);
            
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
```

### 4. API Integration Tests

#### Purpose
Test complete API flows including authentication.

#### Test Cases
```php
// tests/Feature/API/AuthenticationTest.php
class AuthenticationTest extends TestCase {
    /** @test */
    public function it_authenticates_with_valid_credentials() {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password')
        ]);
        
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password'
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'user' => ['id', 'name', 'email']
            ]);
    }
    
    /** @test */
    public function it_refreshes_token() {
        $user = User::factory()->create();
        $refreshToken = $this->generateRefreshToken($user);
        
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure(['access_token']);
    }
}
```

### 5. Database Tests

#### Purpose
Ensure data integrity and relationships.

#### Test Cases
```php
// tests/Feature/Database/RelationshipTest.php
class RelationshipTest extends TestCase {
    /** @test */
    public function it_maintains_referential_integrity() {
        $lead = Lead::factory()->create();
        $contact = Contact::factory()->create();
        
        // Convert lead to contact
        $lead->converted = 1;
        $lead->converted_contact_id = $contact->id;
        $lead->save();
        
        // Delete contact should not be allowed
        $this->expectException(QueryException::class);
        $contact->delete();
    }
}
```

### 6. Field Alignment Tests

#### Purpose
Specifically test field mapping issues.

#### Test Cases
```php
// tests/Feature/FieldAlignmentTest.php
class FieldAlignmentTest extends TestCase {
    /** @test */
    public function it_handles_all_email_fields() {
        $lead = Lead::create([
            'last_name' => 'Test',
            'email1' => 'primary@example.com',
            'email2' => 'secondary@example.com'
        ]);
        
        $this->assertEquals('primary@example.com', $lead->email1);
        $this->assertEquals('secondary@example.com', $lead->email2);
    }
    
    /** @test */
    public function it_handles_all_phone_fields() {
        $contact = Contact::create([
            'last_name' => 'Test',
            'phone_work' => '555-1111',
            'phone_mobile' => '555-2222',
            'phone_home' => '555-3333',
            'phone_other' => '555-4444'
        ]);
        
        $this->assertNotNull($contact->phone_work);
        $this->assertNotNull($contact->phone_mobile);
    }
}
```

## Testing Tools & Setup

### 1. PHPUnit Configuration
```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         verbose="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="mysql"/>
        <env name="DB_DATABASE" value="crm_test"/>
    </php>
</phpunit>
```

### 2. Test Database Setup
```bash
# Create test database
docker exec sassycrm-mysql mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS crm_test;"

# Run migrations on test DB
php artisan migrate --database=mysql_test

# Run tests
./vendor/bin/phpunit
```

### 3. Factory Setup
```php
// database/factories/LeadFactory.php
class LeadFactory extends Factory {
    public function definition() {
        return [
            'id' => $this->faker->uuid(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email1' => $this->faker->unique()->safeEmail(),
            'phone_work' => $this->faker->phoneNumber(),
            'company' => $this->faker->company(),
            'status' => 'new',
            'deleted' => 0
        ];
    }
}
```

## Test Data Scenarios

### 1. Edge Cases
- Empty strings vs NULL values
- Very long strings (max length)
- Special characters in names/emails
- International phone formats
- Duplicate emails
- Soft deleted records

### 2. Business Logic
- Lead conversion flow
- AI scoring with no activity
- Concurrent updates
- Permission checks
- Rate limiting

### 3. Performance
- Bulk operations (100+ records)
- Complex queries with joins
- Search functionality
- Pagination edge cases

## CI/CD Integration

### GitHub Actions Workflow
```yaml
name: Backend Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: crm_test
        ports:
          - 3306:3306
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install Dependencies
        run: composer install
        
      - name: Run Tests
        run: ./vendor/bin/phpunit --coverage-text
        
      - name: Upload Coverage
        uses: codecov/codecov-action@v1
```

## Test Execution Plan

### Phase 1: Critical Path (Day 1)
1. Authentication tests
2. Lead CRUD tests
3. Field mapping tests
4. Basic validation tests

### Phase 2: Core Features (Day 2)
1. All model tests
2. Service layer tests
3. Relationship tests
4. AI feature tests

### Phase 3: Edge Cases (Day 3)
1. Error handling tests
2. Permission tests
3. Performance tests
4. Data migration tests

### Phase 4: Integration (Day 4)
1. Full API flow tests
2. Frontend integration tests
3. Third-party service mocks
4. Load testing

## Success Metrics

1. **Code Coverage**: Minimum 80% coverage
2. **Test Speed**: All unit tests < 5 seconds
3. **Reliability**: Zero flaky tests
4. **Documentation**: Every test has clear purpose
5. **Maintainability**: Tests updated with code changes

## Common Testing Patterns

### 1. API Response Assertion
```php
$response->assertJson([
    'data' => [
        'id' => $lead->id,
        'email' => $lead->email1 // Note field mapping
    ]
]);
```

### 2. Database Transaction
```php
use RefreshDatabase; // Rolls back after each test
```

### 3. Mock External Services
```php
$this->mock(OpenAIService::class, function ($mock) {
    $mock->shouldReceive('complete')
        ->once()
        ->andReturn(['score' => 0.75]);
});
```

## Next Steps

1. Set up test database
2. Create base test classes
3. Implement factories
4. Write critical path tests first
5. Add to CI/CD pipeline
6. Monitor test coverage