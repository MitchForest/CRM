# Technical Debt Inventory

## Files to Delete

### Backend
- `/backend/custom/api/data.json` - Hardcoded mock data
- `/backend/custom/api/controllers/ActivityTrackingController_backup.php`
- `/backend/custom/api/controllers/ActivityTrackingController_fixed.php`
- `/backend/custom/api/controllers/ActivityTrackingController_original.php`
- Any other backup files found during implementation

### Frontend
- `/frontend/src/pages/DashboardOld.tsx`
- `/frontend/src/pages/LeadDebug.tsx` (if only for debugging)
- Any unused example components in `/frontend/src/examples/`

## Code Smells to Fix

### Authentication
- JWT secret hardcoded as 'your-secret-key-here-change-in-production'
- No token refresh implementation
- No token blacklist for logout
- Missing password reset functionality

### API Inconsistencies
- Some endpoints return different response formats
- Inconsistent error handling
- Mix of camelCase and snake_case in responses
- Some controllers using mock data, others using database

### Database Issues
- Some tables have unnecessary complexity
- Missing indexes on foreign keys
- Inconsistent naming conventions
- No proper migration system

### Frontend Issues
- Duplicate API client implementations
- Inconsistent state management
- Some components using local state instead of store
- Missing error boundaries
- Incomplete TypeScript types

## Modules to Hide/Disable

These SuiteCRM modules are not part of core functionality (hide from UI, don't implement in new frontend):
- Projects
- Campaigns  
- Documents
- Contracts
- Quotes
- Products
- Bugs
- Project Tasks
- Spots
- Surveys
- Events
- PDF Templates
- Process Audit
- jjwg Maps
- Workflow

## Complexity to Simplify

### Lead Statuses
Current: New, Assigned, In Process, Converted, Recycled, Dead
Simplify to: New, Contacted, Qualified

### Opportunity Stages  
Current: Prospecting, Qualification, Needs Analysis, Value Proposition, Id. Decision Makers, Perception Analysis, Proposal/Price Quote, Negotiation/Review, Closed Won, Closed Lost
Simplify to: Qualified, Proposal, Negotiation, Won, Lost

### Case Types (to Support Ticket Types)
Current: [Complex types]
Simplify to: Technical, Billing, Feature Request, Other

### User Roles
Current: [Many SuiteCRM roles]
Simplify to: Admin, Sales Rep, Customer Success Rep

## Performance Issues

- No caching strategy
- Loading all data without pagination
- No query optimization
- Missing database indexes
- Large bundle sizes in frontend

## Security Concerns

- Secrets in code
- No rate limiting
- Insufficient input validation
- CORS not properly configured
- Missing CSRF protection

## Missing Core Features

- Token refresh mechanism
- Password reset flow
- Proper session management
- Audit logging
- Health check endpoints

## Testing Gaps

- No integration tests for auth flow
- Missing API endpoint tests
- No end-to-end test suite
- Incomplete unit test coverage

## Documentation Needs

- API documentation outdated
- Missing deployment guide
- No troubleshooting guide
- Incomplete setup instructions
- Missing architecture diagrams

---

This inventory will be updated as more technical debt is discovered during Phase 5 implementation.