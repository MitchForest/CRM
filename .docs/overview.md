# SuiteCRM Headless AI-Powered B2C Software Sales CRM - Technical Overview

## Executive Summary

This document outlines the architecture and technical approach for transforming SuiteCRM into a headless, AI-powered CRM specifically optimized for B2C software sales teams. We create a modern React frontend while leveraging SuiteCRM's robust backend, focusing on the essential modules that matter for direct-to-consumer software sales.

### Key Architecture Decisions
- **B2C Focus**: No Accounts module - Contacts represent individual customers
- **Frontend**: React with Vite for fast development and simple deployment
- **Backend**: Minimal changes - add JSON API layer while keeping core SuiteCRM intact
- **Modules**: Use only 6 core modules, others remain disabled
- **Activities**: Unified timeline view aggregating all customer interactions

## System Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Docker Container                        │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐        ┌─────────────────────────┐    │
│  │  React SPA      │        │   SuiteCRM Backend      │    │
│  │  (Vite)         │◄──────►│   (API Layer)           │    │
│  │                 │  JSON  │                         │    │
│  │  - React 18     │        │  - Existing SugarBeans  │    │
│  │  - TypeScript   │        │  - Custom API routes    │    │
│  │  - Shadcn/ui    │        │  - JWT Auth            │    │
│  │  - Zustand      │        │  - MySQL Database      │    │
│  │  - React Query  │        │  - Redis Cache         │    │
│  └─────────────────┘        └──────────┬──────────────┘    │
│                                        │                    │
│                                        ▼                    │
│                            ┌─────────────────────┐         │
│                            │   AI Service Layer  │         │
│                            │  - OpenAI API       │         │
│                            │  - Enrichment APIs  │         │
│                            └─────────────────────┘         │
└─────────────────────────────────────────────────────────────┘
```

### Project Structure

```
suite-b2c-crm/
├── frontend/                # React Vite app
│   ├── src/
│   │   ├── pages/          # Page components
│   │   │   ├── Dashboard.tsx
│   │   │   ├── Leads.tsx
│   │   │   ├── Contacts.tsx
│   │   │   ├── Opportunities.tsx
│   │   │   └── Settings.tsx
│   │   ├── components/     # Reusable components
│   │   │   ├── activities/
│   │   │   │   └── ActivityTimeline.tsx
│   │   │   ├── ui/        # shadcn components
│   │   │   └── layout/
│   │   ├── api/           # API client
│   │   ├── stores/        # Zustand stores
│   │   └── hooks/         # Custom hooks
│   └── vite.config.ts
│
├── backend/                # SuiteCRM with custom API
│   ├── custom/
│   │   └── api/
│   │       ├── routes.php
│   │       ├── auth/
│   │       │   └── JWTAuth.php
│   │       └── controllers/
│   │           ├── ContactsApi.php
│   │           ├── LeadsApi.php
│   │           ├── OpportunitiesApi.php
│   │           └── ActivitiesApi.php
│   └── [existing SuiteCRM files]
│
├── docker/
│   ├── frontend/
│   │   └── Dockerfile
│   ├── backend/
│   │   └── Dockerfile
│   └── docker-compose.yml
│
└── README.md
```

## Module Strategy

### Active Modules (What We Use)

1. **Contacts** - Individual customers in our B2C model
2. **Leads** - Prospects before they become customers
3. **Opportunities** - Active sales and upgrade opportunities
4. **Tasks** - Follow-ups and to-dos
5. **Emails** - Email communications
6. **Calls** - Phone interactions
7. **Meetings** - Scheduled meetings
8. **Notes** - General observations
9. **Quotes** (AOS_Quotes) - Pricing proposals
10. **Cases** - Customer support tickets

### Disabled Modules (Not Used)

The following modules should be disabled in the SuiteCRM admin panel:
- Accounts (not needed for B2C)
- Targets & Target Lists
- Campaigns
- Surveys
- Bugs
- Projects
- Events
- Knowledge Base
- PDF Templates
- Workflow
- Contracts
- Invoices
- Products (using simplified pricing in Quotes)

**Note**: These modules remain in the codebase but are hidden from the UI. Users who fork this project can re-enable any modules they need through the admin panel.

## Frontend Application

### Technology Stack
- **Framework**: React 18 with TypeScript
- **Build Tool**: Vite (for fast HMR and builds)
- **UI Components**: shadcn/ui (Radix UI + Tailwind CSS)
- **State Management**: Zustand
- **Data Fetching**: TanStack Query (React Query)
- **Forms**: React Hook Form + Zod validation
- **Tables**: TanStack Table
- **Charts**: Recharts
- **Testing**: Vitest + React Testing Library

### Application Pages

1. **Dashboard** (`/`)
   - Active trials ending soon
   - High-value customers at risk (AI-powered)
   - Upcoming activities
   - Revenue metrics
   - Recent sign-ups

2. **Leads** (`/leads`)
   - List view with AI scoring
   - Quick convert to contact action
   - Lead source analytics

3. **Contacts** (`/contacts`)
   - Customer list with search and filters
   - Detail view featuring:
     - Customer information
     - **Activity Timeline** (unified view of all interactions)
     - Active opportunities
     - Support tickets
     - AI insights panel

4. **Opportunities** (`/opportunities`)
   - Pipeline kanban board
   - Grouped by product/stage
   - AI win probability indicators

5. **Activities** (`/activities`)
   - Global activity feed
   - Filter by type/status/contact
   - Bulk actions

6. **Settings** (`/settings`)
   - User profile
   - API configuration
   - AI preferences

### Key Frontend Features

#### Activity Timeline Component
The Activity Timeline is a unified view showing all interactions with a contact in chronological order:
- Emails sent/received
- Tasks created/completed
- Calls logged
- Meetings scheduled
- Notes added

This provides a complete interaction history at a glance, crucial for B2C customer relationships.

## Backend API Layer

### API Architecture

The API layer is implemented in the `custom/api/` directory, preserving the core SuiteCRM functionality while adding modern REST endpoints.

### Core API Endpoints

#### Authentication
```
POST   /api/auth/login     - JWT token generation
POST   /api/auth/refresh   - Token refresh
POST   /api/auth/logout    - Token invalidation
```

#### Contacts (B2C Customers)
```
GET    /api/contacts                    - List all contacts
GET    /api/contacts/:id                - Get contact details
POST   /api/contacts                    - Create new contact
PUT    /api/contacts/:id                - Update contact
DELETE /api/contacts/:id                - Delete contact
GET    /api/contacts/:id/activities     - Get contact's activity timeline
POST   /api/contacts/:id/enrich         - Trigger AI enrichment
GET    /api/contacts/:id/insights       - Get AI insights
```

#### Leads
```
GET    /api/leads                       - List all leads
GET    /api/leads/:id                   - Get lead details
POST   /api/leads                       - Create new lead
PUT    /api/leads/:id                   - Update lead
POST   /api/leads/:id/convert           - Convert to contact
POST   /api/leads/:id/score             - Calculate AI score
```

#### Opportunities
```
GET    /api/opportunities               - List opportunities
GET    /api/opportunities/:id           - Get opportunity details
POST   /api/opportunities               - Create opportunity
PUT    /api/opportunities/:id           - Update opportunity
POST   /api/opportunities/:id/analyze   - AI analysis
```

#### Activities (Aggregated)
```
GET    /api/activities                  - List all activities
GET    /api/activities/upcoming         - Upcoming activities
POST   /api/activities                  - Create activity (any type)
PUT    /api/activities/:id              - Update activity
```

### Data Models

#### Contact Model (B2C Customer)
```typescript
interface Contact {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  phone?: string;
  customerSince?: Date;
  lifetimeValue: number;
  subscriptionStatus?: 'trial' | 'active' | 'cancelled' | 'expired';
  productInterests: string[];
  lastActivityDate: Date;
  engagementScore: number;          // AI-calculated
  churnRisk?: 'low' | 'medium' | 'high'; // AI-predicted
  preferredContactMethod?: 'email' | 'phone' | 'chat';
}
```

#### Activity Model (Unified)
```typescript
interface Activity {
  id: string;
  type: 'task' | 'email' | 'call' | 'meeting' | 'note';
  subject: string;
  description?: string;
  contactId: string;
  status: 'pending' | 'completed';
  dueDate?: Date;
  completedDate?: Date;
  // Type-specific fields
  emailDirection?: 'inbound' | 'outbound';
  emailSentiment?: 'positive' | 'neutral' | 'negative'; // AI
  callDuration?: number;
  callOutcome?: string;
}
```

## AI Integration

### AI-Powered Features

1. **Contact Enrichment**
   - Social profile discovery
   - Company information (if applicable)
   - Interest detection based on behavior

2. **Lead Scoring**
   - Email engagement analysis
   - Website behavior tracking
   - Trial usage patterns
   - Conversion probability

3. **Churn Prediction**
   - Usage pattern analysis
   - Support ticket sentiment
   - Engagement trend detection

4. **Email Intelligence**
   - Smart response generation
   - Optimal send time prediction
   - Subject line optimization
   - Sentiment analysis

5. **Next Best Action**
   - Optimal contact timing
   - Personalized offer recommendations
   - Upgrade opportunity detection

### AI Service Integration

The AI layer integrates with:
- OpenAI API for natural language processing
- Email enrichment services
- Behavioral analytics
- Predictive modeling

## Technical Implementation Details

### Backend Modifications

The core SuiteCRM remains largely untouched. We add:

1. **Custom API Router** (`custom/api/index.php`)
   - Handles all REST endpoints
   - JWT authentication middleware
   - Request/response formatting

2. **API Controllers** (`custom/api/controllers/`)
   - Thin controllers that use existing SugarBeans
   - JSON response formatting
   - Error handling

3. **Authentication Layer** (`custom/api/auth/`)
   - JWT token generation and validation
   - Session management
   - API key handling

### What Stays the Same
- Database structure
- SugarBean ORM
- Business logic
- ACL/Security
- Module relationships
- Custom fields

### What We Don't Use
- Smarty templates
- Legacy JavaScript
- HTML generation
- Traditional view classes

## Deployment

### Docker Configuration

The entire application runs in Docker containers for easy deployment:

```yaml
version: '3.8'

services:
  frontend:
    build: ./docker/frontend
    ports:
      - "3000:3000"
    environment:
      - VITE_API_URL=http://localhost:8080
    volumes:
      - ./frontend:/app

  backend:
    build: ./docker/backend
    ports:
      - "8080:80"
    environment:
      - DATABASE_URL=mysql://root:password@db:3306/suitecrm
      - OPENAI_API_KEY=${OPENAI_API_KEY}
    depends_on:
      - db
      - cache

  db:
    image: mariadb:10.11
    volumes:
      - db_data:/var/lib/mysql

  cache:
    image: redis:7-alpine

volumes:
  db_data:
```

### Quick Start

```bash
# Clone repository
git clone https://github.com/yourcompany/suite-b2c-crm.git

# Configure environment
cp .env.example .env
# Add your OpenAI API key and other settings

# Start everything
docker-compose up

# Access
# Frontend: http://localhost:3000
# API: http://localhost:8080/api
# SuiteCRM Admin: http://localhost:8080/admin (if needed)
```

## Performance Targets

- **API Response Time**: < 100ms for list views, < 50ms for single records
- **Frontend Load Time**: < 2 seconds initial load
- **Activity Timeline**: Smooth scrolling with 1000+ activities
- **Search**: < 200ms for full-text contact search
- **AI Features**: < 3 seconds for enrichment/scoring

## Security Considerations

- JWT tokens with refresh mechanism
- API rate limiting per user
- Input validation on all endpoints
- SQL injection prevention via parameterized queries
- XSS protection in React
- CORS configuration for production
- Environment-based configuration

## Benefits of This Architecture

1. **Minimal Risk**: Core SuiteCRM functionality preserved
2. **Modern UX**: Beautiful, responsive React interface
3. **Developer Friendly**: Hot reload, TypeScript, modern tooling
4. **B2C Optimized**: Simplified for individual customers
5. **AI-Powered**: Intelligent insights throughout
6. **Easy Deployment**: Single Docker command to run
7. **Extensible**: Easy to add modules or features later

This architecture provides a modern, efficient CRM perfect for B2C software companies while maintaining the reliability and extensibility of SuiteCRM's proven backend.