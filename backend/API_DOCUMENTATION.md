# SuiteCRM v8 API Documentation - Phase 1

## Overview
This document describes the v8 REST API endpoints available in Phase 1 of the B2B CRM implementation.

## Base URL
```
http://localhost:8080/Api/V8
```

## Authentication

### OAuth2 Password Grant
**Endpoint:** `POST /Api/access_token`

**Request:**
```bash
curl -X POST http://localhost:8080/Api/access_token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&client_id=suitecrm_client&client_secret=secret123&username=apiuser&password=apiuser123"
```

**Response:**
```json
{
  "token_type": "Bearer",
  "expires_in": 3600,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "refresh_token": "def50200e3b392fbe8b36aa..."
}
```

### Token Refresh
**Endpoint:** `POST /Api/access_token`

**Request:**
```bash
curl -X POST http://localhost:8080/Api/access_token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=refresh_token&refresh_token=YOUR_REFRESH_TOKEN&client_id=suitecrm_client&client_secret=secret123"
```

## API Endpoints

### 1. Get Modules List
**Endpoint:** `GET /meta/modules`
**Authorization:** Bearer token required

```bash
curl -X GET http://localhost:8080/Api/V8/meta/modules \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### 2. Get Module Records (Leads)
**Endpoint:** `GET /module/Leads`
**Authorization:** Bearer token required

**Parameters:**
- `page[size]` - Number of records per page (default: 20)
- `page[number]` - Page number (default: 1)
- `sort` - Sort field and direction (e.g., `-date_entered`)
- `filter` - Filter criteria

**Example:**
```bash
curl -X GET "http://localhost:8080/Api/V8/module/Leads?page[size]=10&page[number]=1" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/vnd.api+json"
```

**Response:**
```json
{
  "data": [
    {
      "type": "Leads",
      "id": "abc123",
      "attributes": {
        "first_name": "John",
        "last_name": "Smith",
        "email1": "john.smith@example.com",
        "ai_score": "85",
        "ai_score_date": "2025-07-23T19:36:00+00:00",
        "ai_insights": "High engagement with pricing page."
      }
    }
  ],
  "meta": {
    "total-pages": 5,
    "total-records": 50
  }
}
```

### 3. Get Single Record
**Endpoint:** `GET /module/{moduleName}/{id}`

```bash
curl -X GET http://localhost:8080/Api/V8/module/Leads/abc123 \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### 4. Create Record
**Endpoint:** `POST /module`

**Request:**
```bash
curl -X POST http://localhost:8080/Api/V8/module \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/vnd.api+json" \
  -d '{
    "data": {
      "type": "Leads",
      "attributes": {
        "first_name": "Jane",
        "last_name": "Doe",
        "email1": "jane.doe@example.com",
        "lead_source": "Website",
        "status": "New",
        "ai_score": 75
      }
    }
  }'
```

### 5. Update Record
**Endpoint:** `PATCH /module`

**Request:**
```bash
curl -X PATCH http://localhost:8080/Api/V8/module \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/vnd.api+json" \
  -d '{
    "data": {
      "type": "Leads",
      "id": "abc123",
      "attributes": {
        "status": "Qualified",
        "ai_score": 90,
        "ai_insights": "Ready for sales outreach"
      }
    }
  }'
```

### 6. Delete Record
**Endpoint:** `DELETE /module/{moduleName}/{id}`

```bash
curl -X DELETE http://localhost:8080/Api/V8/module/Leads/abc123 \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Custom Fields

### Leads Module
- `ai_score` (integer, 0-100) - AI-generated lead score
- `ai_score_date` (datetime) - When score was calculated
- `ai_insights` (text) - AI-generated insights

### Accounts Module
- `health_score` (integer, 0-100) - Customer health score
- `mrr` (currency) - Monthly Recurring Revenue
- `last_activity` (datetime) - Last customer activity

## CORS Configuration
The API is configured to accept requests from:
- `http://localhost:3000` (React frontend)
- `http://localhost:5173` (Vite dev server)

Allowed methods: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`
Allowed headers: `Content-Type`, `Authorization`, `X-Requested-With`

## Error Responses
The API follows JSON:API error format:

```json
{
  "errors": [
    {
      "status": "400",
      "title": "Bad Request",
      "detail": "The field 'invalid_field' is not valid for module Leads"
    }
  ]
}
```

Common error codes:
- `401` - Unauthorized (missing or invalid token)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `422` - Unprocessable Entity (validation errors)

## Rate Limiting
Currently no rate limiting is implemented. This will be added in Phase 2.

## Testing Credentials
- **Username:** apiuser
- **Password:** apiuser123
- **Client ID:** suitecrm_client
- **Client Secret:** secret123

## Postman Collection
A Postman collection is available at: `/backend/docs/SuiteCRM_API_Collection.json`

## Health Check Endpoints
- **Custom API Health:** `GET /custom/api/health`
- **v8 API Status:** Returns 401 on `GET /Api/V8/meta/modules` without auth (expected behavior)