# Sassy CRM Backend Test Plan

## Overview
This document outlines the comprehensive testing strategy to ensure database schemas, models, controllers, services, and APIs are properly aligned before frontend integration.

## Critical Issues to Address

### 1. Field Naming Mismatches
**Problem**: Database uses SuiteCRM naming conventions while models use simplified names.

| Model Field | Database Field | Used In |
|------------|----------------|---------|
| email | email1 | leads, contacts, accounts |
| phone | phone_work, phone_mobile, phone_home | leads, contacts |
| - | email2 | contacts (secondary email) |

### 2. Solution: Field Mapping Strategy

#### Option A: Model Accessors (Recommended)
Add accessor methods to models to handle both naming conventions:

```php
// In Lead model
public function getEmailAttribute() {
    return $this->email1;
}

public function setEmailAttribute($value) {
    $this->email1 = $value;
}

public function getPhoneAttribute() {
    return $this->phone_work ?? $this->phone_mobile;
}
```

#### Option B: Update Database
Rename columns to match modern conventions (risky, may break SuiteCRM compatibility).

#### Option C: Request/Response Transformers
Create transformer classes to handle field mapping at API layer.

## Test Structure

### 1. Unit Tests

#### Model Tests (`tests/Unit/Models/`)
- Field accessibility tests
- Relationship tests
- Accessor/mutator tests
- Validation rules

#### Service Tests (`tests/Unit/Services/`)
- Business logic validation
- Data transformation
- Error handling

### 2. Integration Tests

#### Database Tests (`tests/Integration/`)
- CRUD operations
- Transaction handling
- Relationship integrity
- Field mapping verification

### 3. Feature Tests

#### API Endpoint Tests (`tests/Feature/Api/`)
- Authentication flow
- Request validation
- Response format consistency
- Error handling
- Field naming consistency

## Test Implementation Priority

### Phase 1: Field Alignment Tests (CRITICAL)
1. Create field mapping tests for all models
2. Verify controllers handle both field formats
3. Test API responses use consistent naming

### Phase 2: Core CRUD Tests
1. Test all Create operations
2. Test all Read operations
3. Test all Update operations
4. Test all Delete operations

### Phase 3: Relationship Tests
1. Test lead -> contact conversion
2. Test contact -> opportunity relationships
3. Test activity associations

### Phase 4: Special Features
1. AI scoring functionality
2. Chat conversations
3. Activity tracking
4. Form submissions

## Automated Test Suite

### PHPUnit Configuration
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         convertErrorsToExceptions="true"
         convertNoticesToExceptions="true"
         convertWarningsToExceptions="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### Running Tests
```bash
# Run all tests
docker-compose exec backend ./vendor/bin/phpunit

# Run specific test suite
docker-compose exec backend ./vendor/bin/phpunit --testsuite Unit

# Run with coverage
docker-compose exec backend ./vendor/bin/phpunit --coverage-html coverage
```

## API Documentation Generation

Use PHPDoc comments and generate OpenAPI specification:

```php
/**
 * @OA\Post(
 *     path="/api/crm/leads",
 *     summary="Create a new lead",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"first_name","last_name","email"},
 *             @OA\Property(property="first_name", type="string"),
 *             @OA\Property(property="last_name", type="string"),
 *             @OA\Property(property="email", type="string", description="Maps to email1 in database"),
 *             @OA\Property(property="phone", type="string", description="Maps to phone_work in database")
 *         )
 *     ),
 *     @OA\Response(response=201, description="Lead created successfully"),
 *     @OA\Response(response=422, description="Validation error")
 * )
 */
```

## Success Criteria

1. ✅ All field naming inconsistencies documented and handled
2. ✅ 100% model test coverage
3. ✅ All API endpoints return consistent field names
4. ✅ Request validation prevents bad data
5. ✅ Error responses follow consistent format
6. ✅ API documentation matches implementation
7. ✅ Performance: All endpoints < 200ms response time

## Next Steps

1. Implement field mapping in models
2. Create base test classes
3. Write critical field alignment tests
4. Generate API documentation
5. Run full test suite
6. Fix any failing tests
7. Handoff to frontend with confidence