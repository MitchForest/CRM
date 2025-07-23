# Type System and Field Mapping

This directory contains the TypeScript type definitions for the CRM application, with automatic field name mapping between the frontend (camelCase) and SuiteCRM backend (snake_case).

## Overview

SuiteCRM uses snake_case field naming (e.g., `first_name`, `phone_work`), while our React frontend follows JavaScript conventions using camelCase (e.g., `firstName`, `phoneWork`). To bridge this gap, we have an automatic field mapping system.

## File Structure

- **`frontend.types.ts`** - Frontend types with camelCase field names
- **`suitecrm.types.ts`** - SuiteCRM types with snake_case field names (matches database)
- **`api.generated.ts`** - Auto-generated types from OpenAPI spec
- **`api.schemas.ts`** - Zod schemas for runtime validation
- **`index.ts`** - Main export file

## How It Works

### 1. Frontend Types (camelCase)

```typescript
// In your components, use frontend types with camelCase
import { Contact } from '@/types';

const contact: Contact = {
  id: '123',
  firstName: 'John',      // camelCase
  lastName: 'Doe',        // camelCase
  phoneWork: '555-1234',  // camelCase
  dateModified: '2024-01-01', // camelCase
};
```

### 2. Automatic Conversion

The `api-transformers.ts` file handles conversion automatically:

```typescript
// When fetching from API (snake_case → camelCase)
const response = await apiClient.get('/contacts/123');
const contact = transformFromJsonApi<Contact>(response.data.data);
// contact.firstName is available (converted from first_name)

// When sending to API (camelCase → snake_case)
const jsonApiData = transformToJsonApi('contacts', contact);
// jsonApiData.attributes.first_name is sent (converted from firstName)
```

### 3. Field Mapping Functions

Located in `lib/field-mappers.ts`:

- `toCamelCase()` - Converts snake_case to camelCase
- `toSnakeCase()` - Converts camelCase to snake_case
- `mapSuiteCRMToFrontend()` - With special field handling
- `mapFrontendToSuiteCRM()` - With special field handling

## Usage Examples

### Fetching Data

```typescript
// The API returns snake_case, but you work with camelCase
const { data } = await apiClient.get('/contacts');
const contacts = data.data.map(resource => 
  transformFromJsonApi<Contact>(resource)
);

// Now use camelCase fields
contacts.forEach(contact => {
  console.log(contact.firstName); // not first_name
  console.log(contact.phoneWork); // not phone_work
});
```

### Creating/Updating Data

```typescript
// Define data with camelCase
const newLead: Partial<Lead> = {
  firstName: 'Jane',
  lastName: 'Smith',
  email1: 'jane@example.com',
  leadSource: 'Website',
  status: 'New'
};

// Send to API (automatically converted to snake_case)
const response = await apiClient.post('/leads', {
  data: transformToJsonApi('leads', newLead, false)
});
```

### Filtering and Sorting

```typescript
// Filters are automatically converted
const filters = {
  firstName: 'John',    // becomes filter[first_name]
  accountName: 'Acme',  // becomes filter[account_name]
};

const params = buildJsonApiFilters(filters);

// Sorting is also converted
const sortParam = buildJsonApiSort('lastName', 'asc'); // becomes 'last_name'
```

## Common Field Mappings

| Frontend (camelCase) | SuiteCRM (snake_case) |
|---------------------|----------------------|
| firstName | first_name |
| lastName | last_name |
| phoneWork | phone_work |
| phoneMobile | phone_mobile |
| dateEntered | date_entered |
| dateModified | date_modified |
| assignedUserId | assigned_user_id |
| accountName | account_name |
| billingAddressStreet | billing_address_street |
| primaryAddressCity | primary_address_city |

## Type Safety

The system maintains full TypeScript type safety:

1. Frontend components use `Contact`, `Lead`, etc. (camelCase)
2. API transformers handle the conversion
3. SuiteCRM receives properly formatted snake_case data
4. No manual string manipulation needed

## Adding New Fields

When adding new fields:

1. Add to `frontend.types.ts` using camelCase
2. Add to `suitecrm.types.ts` using snake_case
3. The field mappers will handle conversion automatically

For special cases that don't follow standard naming:

```typescript
// In field-mappers.ts
const SPECIAL_FIELD_MAPPINGS: Record<string, string> = {
  'some_unusual_field': 'customFieldName',
  // Add special cases here
};
```

## Benefits

1. **Developer Experience**: Work with familiar camelCase in React
2. **Type Safety**: Full TypeScript support with proper types
3. **Automatic Conversion**: No manual field mapping needed
4. **Maintainability**: Clear separation between frontend and backend formats
5. **Consistency**: Follows JavaScript/TypeScript naming conventions