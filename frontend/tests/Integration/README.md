# Integration Tests for Phase 2 CRM

This directory contains comprehensive integration tests for the Phase 2 CRM implementation, covering all modules and their interactions with both the SuiteCRM V8 API and custom Phase 2 API endpoints.

## Test Structure

```
tests/integration/
├── helpers/
│   ├── test-auth.ts      # Authentication helpers
│   └── test-data.ts      # Test data fixtures
├── dashboard.integration.test.ts    # Dashboard metrics tests
├── opportunities.integration.test.ts # Opportunities & Kanban tests
├── activities.integration.test.ts    # Activities (Calls, Meetings, Tasks, Notes)
├── cases.integration.test.ts         # Cases with priorities & SLA
├── e2e-workflows.test.ts            # End-to-end workflow scenarios
└── README.md                        # This file
```

## Prerequisites

1. **Backend Services Running**
   ```bash
   cd backend
   docker-compose up -d
   ```

2. **Test User Setup**
   - Username: `admin`
   - Password: `admin123`
   - Ensure user has full admin privileges

3. **Frontend Dependencies**
   ```bash
   cd frontend
   npm install
   ```

## Running Tests

### Run All Integration Tests
```bash
npm run test:integration
```

### Run Specific Test Suite
```bash
# Dashboard tests only
npm run test:integration dashboard.integration.test.ts

# Opportunities tests only
npm run test:integration opportunities.integration.test.ts

# Activities tests only
npm run test:integration activities.integration.test.ts

# Cases tests only
npm run test:integration cases.integration.test.ts

# E2E workflow tests
npm run test:integration e2e-workflows.test.ts
```

### Run with Coverage
```bash
npm run test:integration:coverage
```

## Test Configuration

Add to `package.json`:
```json
{
  "scripts": {
    "test:integration": "vitest run tests/integration",
    "test:integration:watch": "vitest tests/integration",
    "test:integration:coverage": "vitest run --coverage tests/integration"
  }
}
```

Create `vitest.config.integration.ts`:
```typescript
import { defineConfig } from 'vitest/config'
import path from 'path'

export default defineConfig({
  test: {
    environment: 'node',
    globals: true,
    setupFiles: ['./tests/integration/setup.ts'],
    testTimeout: 30000, // 30 seconds for API calls
    hookTimeout: 30000,
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
})
```

## Test Coverage

### Dashboard Tests
- ✅ Metrics API (leads, accounts, pipeline value)
- ✅ Pipeline data by stage
- ✅ Activity metrics (calls, meetings, tasks)
- ✅ Case metrics (priorities, SLA)
- ✅ Cross-API consistency
- ✅ Performance benchmarks

### Opportunities Tests
- ✅ CRUD operations
- ✅ Kanban drag-drop simulation
- ✅ Automatic probability updates
- ✅ Stage progression
- ✅ Pipeline analytics integration
- ✅ Filtering and search
- ✅ Relationships (Account linking)

### Activities Tests
- ✅ Call management
- ✅ Meeting scheduling
- ✅ Task tracking with priorities
- ✅ Notes with attachments
- ✅ Parent record linking
- ✅ Dashboard integration
- ✅ Overdue task tracking

### Cases Tests
- ✅ Priority-based cases (P1, P2, P3)
- ✅ SLA calculation and tracking
- ✅ Status workflow
- ✅ Case assignment
- ✅ Metrics integration
- ✅ Critical case alerts
- ✅ Resolution tracking

### E2E Workflows
- ✅ Lead to Customer conversion
- ✅ Opportunity pipeline progression
- ✅ Customer support case lifecycle
- ✅ Activity management workflow
- ✅ Cross-module data consistency

## API Endpoints Tested

### Custom API (Phase 2)
- `POST /custom-api/auth/login`
- `GET /custom-api/dashboard/metrics`
- `GET /custom-api/dashboard/pipeline`
- `GET /custom-api/dashboard/activities`
- `GET /custom-api/dashboard/cases`

### SuiteCRM V8 API
- `POST /Api/access_token`
- `GET /Api/V8/module/{module}`
- `POST /Api/V8/module`
- `PATCH /Api/V8/module`
- `DELETE /Api/V8/module/{module}/{id}`

## Common Issues & Solutions

### Authentication Failures
```
Error: 401 Unauthorized
```
**Solution**: Ensure admin user exists with correct password. Check OAuth client credentials.

### API Connection Issues
```
Error: ECONNREFUSED
```
**Solution**: Verify Docker containers are running and healthy:
```bash
docker ps
docker logs suitecrm-backend
```

### Test Timeouts
```
Error: Test timeout of 30000ms exceeded
```
**Solution**: Increase timeout in test configuration or check if API is responding slowly.

### Data Cleanup Issues
```
Warning: Test data not cleaned up
```
**Solution**: Tests use afterAll hooks, but check for orphaned test data:
```sql
DELETE FROM leads WHERE first_name LIKE 'Test%';
DELETE FROM accounts WHERE name LIKE 'Test%';
```

## Writing New Tests

### Test Structure Template
```typescript
describe('Module Integration Tests', () => {
  let tokens: AuthTokens
  let suitecrmClient: any
  let customApiClient: any
  
  beforeAll(async () => {
    tokens = await getTestAuthTokens()
    suitecrmClient = createAuthenticatedClient(...)
    customApiClient = createAuthenticatedClient(...)
  })
  
  afterAll(async () => {
    // Clean up test data
  })
  
  describe('Feature Area', () => {
    it('should perform specific action', async () => {
      // Test implementation
    })
  })
})
```

### Best Practices
1. **Isolate Test Data**: Use unique names/emails to avoid conflicts
2. **Clean Up**: Always clean up created records in afterAll
3. **Wait for Propagation**: Add delays when testing cross-API consistency
4. **Check Multiple Aspects**: Verify both API response and side effects
5. **Use Realistic Data**: Test with production-like data scenarios

## Continuous Integration

### GitHub Actions Example
```yaml
name: Integration Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: suitecrm
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Install dependencies
        run: |
          cd frontend
          npm ci
      
      - name: Run integration tests
        run: |
          cd frontend
          npm run test:integration
        env:
          API_URL: http://localhost:8080
```

## Monitoring Test Health

### Key Metrics
- **Pass Rate**: Should be 100% for all tests
- **Execution Time**: Dashboard < 5s, CRUD < 3s, E2E < 10s
- **Flaky Tests**: Track and fix intermittent failures
- **Coverage**: Aim for >80% of API endpoints

### Regular Maintenance
1. Update test data when schema changes
2. Add tests for new features
3. Remove obsolete tests
4. Update timeouts based on performance
5. Review and refactor complex tests

## Support

For issues or questions:
1. Check test output for detailed error messages
2. Review backend logs: `docker logs suitecrm-backend`
3. Verify API is accessible: `curl http://localhost:8080/custom-api/health`
4. Check database state if needed