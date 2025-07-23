# Leads API Documentation

The Leads API provides full CRUD operations for managing leads with support for AI-powered custom fields.

## Base URL
```
/custom/api/leads
```

## Authentication
All endpoints require Bearer token authentication:
```
Authorization: Bearer <token>
```

## AI Custom Fields

The Leads module includes three AI-related custom fields:

- **ai_score** (integer, 0-100): AI-generated lead score indicating conversion probability
- **ai_score_date** (datetime): Timestamp when the AI score was last calculated/updated
- **ai_insights** (text): AI-generated insights and recommendations about the lead

These fields are included in all responses as part of the lead attributes, not in a separate customFields object.

## Endpoints

### List Leads
```
GET /leads
```

Query Parameters:
- `page` (int): Page number (default: 1)
- `limit` (int): Records per page (default: 20, max: 100)
- `orderBy` (string): Sort field and direction (default: "date_entered DESC")
- `filter[field][operator]` (various): Filter conditions

Example with AI score filtering:
```
GET /leads?filter[ai_score][gte]=80&filter[status]=New&limit=10
```

Response:
```json
{
  "data": [
    {
      "id": "123",
      "first_name": "John",
      "last_name": "Doe",
      "email1": "john@example.com",
      "ai_score": 85,
      "ai_score_date": "2024-01-15T10:30:00+00:00",
      "ai_insights": "High conversion probability based on engagement",
      ...
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 150,
    "pages": 8
  }
}
```

### Get Single Lead
```
GET /leads/{id}
```

Response:
```json
{
  "data": {
    "id": "123",
    "first_name": "John",
    "last_name": "Doe",
    "ai_score": 85,
    "ai_score_date": "2024-01-15T10:30:00+00:00",
    "ai_insights": "High conversion probability",
    ...
  }
}
```

### Create Lead
```
POST /leads
```

Request Body:
```json
{
  "first_name": "Jane",
  "last_name": "Smith",
  "email1": "jane@example.com",
  "phone_mobile": "555-0123",
  "status": "New",
  "lead_source": "Web Site",
  "ai_score": 75,
  "ai_insights": "Medium conversion probability"
}
```

Note: When `ai_score` is provided, `ai_score_date` is automatically set to the current timestamp.

### Update Lead
```
PUT /leads/{id}
```

Updates all provided fields. Use PATCH for partial updates.

### Partial Update Lead
```
PATCH /leads/{id}
```

Request Body (example updating only AI fields):
```json
{
  "ai_score": 92,
  "ai_insights": "Updated: Very high conversion probability"
}
```

### Delete Lead
```
DELETE /leads/{id}
```

## Filter Operators

Supported operators for filtering:
- `eq` (equals) - default if no operator specified
- `ne` (not equals)
- `gt` (greater than)
- `lt` (less than)
- `gte` (greater than or equal)
- `lte` (less than or equal)
- `like` (contains)
- `in` (in array)
- `between` (between two values)

Examples:
```
?filter[ai_score][gte]=80
?filter[status]=New
?filter[email1][like]=@company.com
?filter[date_entered][between][]=2024-01-01&filter[date_entered][between][]=2024-01-31
```

## Error Responses

All errors follow a consistent format:
```json
{
  "error": "Error message",
  "code": "ERROR_CODE",
  "statusCode": 400,
  "details": {},
  "validation": {
    "field_name": "Validation error message"
  }
}
```

Common error codes:
- `VALIDATION_FAILED`: Input validation errors
- `NOT_FOUND`: Resource not found
- `UNAUTHORIZED`: Authentication required
- `FORBIDDEN`: Access denied
- `INTERNAL_ERROR`: Server error

## Field Validation

- `last_name`: Required
- `email1`, `email2`: Must be valid email format if provided
- `ai_score`: Must be between 0 and 100 if provided