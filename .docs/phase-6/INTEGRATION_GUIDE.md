# Frontend-Backend Integration Guide

## Overview

This guide documents how the frontend integrates with the backend API using TypeScript for full type safety.

## Architecture

```
Backend (PHP)                    Frontend (React/TypeScript)
    │                                      │
    ├─ Database Schema ──────────┐        │
    │  (Snake_case fields)       │        │
    │                            ▼        │
    ├─ Eloquent Models      OpenAPI Spec  │
    │  (Exact DB fields)         │        │
    │                            │        │
    ├─ Controllers               │        │
    │  (Return snake_case)       │        │
    │                            ▼        │
    └─ OpenAPI Annotations   TypeScript   │
       (Document all APIs)    Client Gen   │
                                 │        │
                                 ▼        │
                            Generated API │
                              Client      │
                                 │        │
                                 ▼        │
                            React Hooks   │
                            & Components  │
```

## Type Generation Flow

### 1. Database Types Generation

The backend exposes a schema endpoint that generates TypeScript interfaces directly from the database:

```bash
# Generate database types
npm run generate:types
```

This creates:
- `/frontend/src/types/database.types.ts` - All DB interfaces (LeadDB, ContactDB, etc.)
- All fields use snake_case exactly as in the database
- No transformation or mapping needed

### 2. API Client Generation

From the OpenAPI specification, we generate a complete TypeScript client:

```bash
# Generate API client from OpenAPI
npm run generate:api-client
```

This creates:
- `/frontend/src/api/generated/` - Full API client with all endpoints
- Type-safe request/response interfaces
- Automatic authentication handling

### 3. Combined Generation

```bash
# Generate everything
npm run generate:all
```

## Using the Generated Types

### Database Types

```typescript
import type { LeadDB, ContactDB, OpportunityDB } from '@/types/database.types';

// All fields are snake_case
const lead: LeadDB = {
  id: '123',
  first_name: 'John',
  last_name: 'Doe',
  email1: 'john@example.com',
  phone_work: '555-1234',
  date_entered: '2024-01-01',
  // ... etc
};
```

### API Client

```typescript
import { leadsApi, contactsApi } from '@/api/client';

// Fully typed API calls
const response = await leadsApi.getLeads({
  page: 1,
  limit: 20,
  filter: {
    status: 'new'
  }
});

// response.data is typed as ListResponse<LeadDB>
```

### React Hooks

```typescript
import { useQuery } from '@tanstack/react-query';
import { leadsApi } from '@/api/client';

export function useLeads(params?: QueryParams) {
  return useQuery({
    queryKey: ['leads', params],
    queryFn: () => leadsApi.getLeads(params)
  });
}
```

## Field Naming Convention

### ❌ NEVER Use CamelCase

```typescript
// WRONG
lead.firstName
lead.phoneWork
lead.dateEntered
lead.assignedUserId
```

### ✅ ALWAYS Use Snake_Case

```typescript
// CORRECT
lead.first_name
lead.phone_work
lead.date_entered
lead.assigned_user_id
```

### Common Field Mappings

| Wrong (camelCase) | Correct (snake_case) | Notes |
|-------------------|---------------------|--------|
| email | email1 | Primary email field |
| phone | phone_work | Work phone number |
| mobile | phone_mobile | Mobile phone |
| company | account_name | Company name |
| assignedUserName | assigned_user_id | Only ID exists |
| createdAt | date_entered | Creation timestamp |
| updatedAt | date_modified | Update timestamp |

## API Response Structure

All API responses follow this structure:

### List Responses
```typescript
{
  data: T[],           // Array of items
  pagination: {
    page: number,      // Current page
    limit: number,     // Items per page (NOT pageSize!)
    total: number,     // Total items
    totalPages: number // Total pages
  }
}
```

### Single Item Responses
```typescript
{
  data: T  // Single item
}
```

### Error Responses
```typescript
{
  error: string,
  code?: string,
  details?: any
}
```

## Authentication

The generated API client automatically handles authentication:

```typescript
// Auth is handled via the auth store
import { getStoredAuth } from '@/stores/auth-store';

// The API client reads the token automatically
const auth = getStoredAuth();
// Bearer token is added to all requests
```

## Validation

Use Zod schemas for form validation:

```typescript
import { z } from 'zod';

const leadSchema = z.object({
  first_name: z.string().optional(),
  last_name: z.string().min(1),
  email1: z.string().email().optional(),
  phone_work: z.string().optional(),
  // etc...
});
```

## Best Practices

### 1. Always Regenerate After Schema Changes

```bash
# When backend changes
npm run generate:all
```

### 2. Never Transform Field Names

```typescript
// ❌ WRONG - Don't transform
const displayLead = {
  name: `${lead.first_name} ${lead.last_name}`,
  email: lead.email1  // Don't rename!
};

// ✅ CORRECT - Use exact fields
const displayLead = lead; // Use as-is
// Format in UI: {lead.first_name} {lead.last_name}
```

### 3. Use Type Guards

```typescript
function isLeadDB(obj: any): obj is LeadDB {
  return obj && typeof obj.id === 'string' && 'email1' in obj;
}
```

### 4. Handle Nullable Fields

```typescript
// Many fields are nullable
const phone = lead.phone_work || 'No phone';
const email = lead.email1 || 'No email';
```

## Troubleshooting

### Type Errors After Backend Changes

1. Regenerate types: `npm run generate:all`
2. Check for field renames in error messages
3. Update component to use new field names

### API Client Errors

1. Ensure backend is running
2. Check OpenAPI spec is accessible at `/api-docs/openapi.json`
3. Regenerate client: `npm run generate:api-client`

### Authentication Errors

1. Check token in auth store
2. Ensure token is not expired
3. Try logging out and back in

## Migration Checklist

When updating a component to use generated types:

- [ ] Import types from `@/types/database.types`
- [ ] Import API client from `@/api/client`
- [ ] Replace all camelCase field names with snake_case
- [ ] Update form field names to match database
- [ ] Update validation schemas to use snake_case
- [ ] Test CRUD operations
- [ ] Check TypeScript has no errors

## Future Improvements

1. **GraphQL Integration** - For more flexible queries
2. **Real-time Updates** - WebSocket support
3. **Offline Support** - Local caching with sync
4. **Field Mapping Layer** - For backward compatibility (if needed)