# Master Plan: Sassy CRM - Modernizing SuiteCRM for Software Sales Teams

## Executive Summary

We are modernizing SuiteCRM's monolithic architecture to create a headless CRM specifically designed for software sales teams. By decoupling the layers and adding AI-powered features, we're building a modern CRM that tracks the entire customer journey from first website visit through support tickets.

## Vision: Modern CRM for Software Sales

### Target Market: Software Sales Teams
- **Pain Points**: Generic CRMs don't track pre-lead behavior, lack embedded tools, and have poor AI integration
- **Our Solution**: Complete customer journey tracking with embeddable tools and AI assistance
- **Key Differentiator**: See everything - website sessions, chat conversations, form submissions, all in one timeline

### Currently Implemented Features
1. **Marketing Website** - Fully built, showcases the product and uses our own tracking
2. **Embeddable Tools**:
   - Session tracking script (tracks page visits, duration, behavior)
   - Lead capture forms (demos, contact, support)
   - AI chatbot for sales and support
3. **CRM Core**:
   - Dashboard with high-level stats
   - Leads management with AI scoring
   - Opportunities pipeline
   - Contacts (customers) management
   - Support tickets
4. **Unified Timeline** - Click any contact to see their complete history
5. **Admin Section**:
   - Knowledge base editor (WYSIWYG + AI assistance)
   - Form builder for embeddable forms
   - Chatbot configuration
   - Tracking script setup

## Architecture Transformation

### Old Architecture (SuiteCRM Monolithic)
```
┌─────────────────────────────────────────┐
│          Presentation Layer             │
│    (Views, Templates, UI Logic)         │
├─────────────────────────────────────────┤
│         Business Logic Layer            │
│  (Controllers, SugarBeans, Modules)     │
├─────────────────────────────────────────┤
│           Data Layer                    │
│    (Database, File Storage)             │
└─────────────────────────────────────────┘
All tightly coupled in /modules/* structure
```

**Problems**:
- Can't build modern UIs (locked into Sugar templates)
- Can't scale horizontally (monolithic PHP)
- Can't add AI features easily (proprietary ORM)
- Can't support mobile/desktop apps (server-side rendering only)

### New Architecture (Headless AI-Native)
```
┌─────────────────────────────────────────┐
│        Frontend Applications            │
│   React Web | Mobile | Desktop | CLI    │
└─────────────────────────────────────────┘
                    ↓ API
┌─────────────────────────────────────────┐
│          Modern REST API Layer          │
│         /api/crm/* endpoints            │
├─────────────────────────────────────────┤
│         Business Logic Layer            │
│   Controllers (Decoupled from Sugar)    │
├─────────────────────────────────────────┤
│            ORM Layer                    │
│     Eloquent (Modern, Flexible)         │
├─────────────────────────────────────────┤
│          Data Layer (Preserved)         │
│   SuiteCRM Tables + AI Tables           │
│   Vector DB for Embeddings              │
└─────────────────────────────────────────┘
```

## What We're Keeping vs Replacing

### Keep (From SuiteCRM)
1. **Core Database Tables** - leads, contacts, accounts, opportunities, cases, users
2. **Relationship Tables** - accounts_contacts, opportunities_contacts, etc.
3. **Basic Field Structure** - Proven CRM field definitions

### Replace
1. **SugarBean ORM** → **Eloquent ORM** (Modern, well-documented, easy to use)
2. **Module System** → **Clean MVC Controllers** (Simple, maintainable)
3. **V8 API** → **Modern REST API** (Simple JSON, JWT auth)
4. **Server-side Rendering** → **Headless API** (React frontend)

### Our Custom Tables (Already Built)
1. **activity_tracking** - Website session tracking
2. **ai_conversations** - Chat history
3. **ai_chat_messages** - Individual messages
4. **form_submissions** - Form data
5. **ai_lead_scores** - Historical scoring
6. **kb_articles** - Knowledge base content

## Why Eloquent ORM?

### Comparison of Modern ORMs

| Feature | Eloquent | Doctrine | TypeORM | Prisma |
|---------|----------|----------|---------|---------|
| Learning Curve | Easy | Hard | Medium | Easy |
| PHP Native | ✅ | ✅ | ❌ | ❌ |
| Standalone Usage | ✅ | ✅ | N/A | N/A |
| Active Record | ✅ | ❌ | ✅ | ❌ |
| Query Builder | Excellent | Good | Good | Excellent |
| Relationships | Simple | Complex | Simple | Simple |
| Documentation | Excellent | Good | Good | Excellent |
| Community | Huge | Large | Medium | Growing |

**Winner: Eloquent** - Best balance of simplicity, features, and PHP ecosystem support

## Technology Stack

### Backend
- **API Framework**: Slim 4 (lightweight, PSR-7 compliant)
- **ORM**: Eloquent (standalone, no Laravel required)
- **Authentication**: JWT tokens
- **Embeddings**: OpenAI Ada-2 or local embeddings
- **Vector Storage**: PostgreSQL with pgvector (or dedicated vector DB)

### Frontend
- **Framework**: React 18+ with TypeScript
- **State Management**: Zustand or Redux Toolkit
- **UI Components**: Tailwind CSS + Headless UI
- **API Client**: Axios with interceptors

## Project Structure

```
/backend
  /models          # Eloquent models (from SuiteCRM tables)
    Lead.php
    Contact.php
    Account.php
    Opportunity.php
  /controllers     # Business logic
    LeadController.php
    AIController.php
    EmbeddingController.php
  /api            # API routes and middleware
    /routes
    /middleware
    index.php
  /services       # AI and business services
    OpenAIService.php
    EmbeddingService.php
    ScoringService.php
  /config         # Configuration files
  /database       # Migrations and seeds

/frontend         # React application
/docs            # Documentation
```

## Benefits of This Architecture

1. **True Headless CRM** - Build any UI (web, mobile, desktop, CLI)
2. **AI-First Design** - Embeddings and ML models integrated at the core
3. **SaaS-Optimized** - Features specific to software sales
4. **Modern Development** - Use latest tools and practices
5. **Scalable** - Can separate API and frontend, add caching, scale horizontally
6. **Maintainable** - Clean separation of concerns, modern ORM

## DevOps & Open Source Strategy

### One-Command Deployment
We're making deployment dead simple with Docker containerization:

```bash
# Clone the repo
git clone https://github.com/yourcompany/ai-crm
cd ai-crm

# Start everything
docker-compose up -d

# Run initial setup
docker-compose exec api php artisan migrate --seed
```

That's it! The system is ready to use with:
- Pre-configured MySQL database
- API server with all dependencies
- React frontend
- Demo data for immediate testing
- Default admin user

### Open Source Benefits
1. **Transparent** - Companies can audit the code
2. **Customizable** - Extend for specific needs
3. **No Vendor Lock-in** - Own your data and deployment
4. **Community Driven** - Contributions improve the product
5. **Trust** - See exactly how your data is handled

### Production-Ready from Day One
- Environment variables for configuration
- SSL/TLS ready with Let's Encrypt
- Backup scripts included
- Monitoring with health checks
- Horizontal scaling support

## Success Metrics

1. **Developer Experience** - Build new features 5x faster
2. **AI Accuracy** - 90%+ relevant responses from embedded knowledge
3. **Sales Efficiency** - 30% reduction in time to qualify leads
4. **API Performance** - <100ms response time for standard queries
5. **Adoption** - Easy onboarding for SaaS sales teams
6. **Deployment Time** - Under 5 minutes from zero to running CRM

## Next Steps

See `implementation-plan.md` for detailed execution steps.