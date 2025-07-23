# B2B Software Sales CRM - Product Requirements Document

## 🎯 Executive Summary

Transform SuiteCRM into a modern, headless B2B Software Sales CRM platform with AI-powered features and self-service capabilities. This open-source solution enables software companies to manage their entire customer lifecycle - from website visitor to loyal customer - with a beautiful React frontend while maintaining SuiteCRM's stability and upgradeability.

### Key Principles
- **90/30 Approach**: Focus on essential features that deliver 90% of value with 30% effort
- **Headless Architecture**: SuiteCRM backend with custom React frontend
- **AI-Enhanced**: OpenAI integration for lead scoring and customer support
- **Self-Service First**: Knowledge base and chatbot reduce support burden
- **Open Source**: Docker-based deployment for easy self-hosting

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Customer's Website                       │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │  Tracking   │  │  AI Chatbot  │  │   Lead Forms    │  │
│  │   Script    │  │    Widget    │  │   (Embedded)    │  │
│  └──────┬──────┘  └──────┬───────┘  └────────┬─────────┘  │
└─────────┼─────────────────┼──────────────────┼─────────────┘
          │                 │                  │
          ▼                 ▼                  ▼
┌─────────────────────────────────────────────────────────────┐
│                    CRM Platform (Docker)                     │
│  ┌─────────────────────────────────────────────────────┐   │
│  │               React Frontend (Port 3000)             │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────────────┐ │   │
│  │  │Dashboard │  │   CRM    │  │  Admin Tools     │ │   │
│  │  │& Reports │  │ Modules  │  │  (Form Builder)  │ │   │
│  │  └──────────┘  └──────────┘  └──────────────────┘ │   │
│  └─────────────────────────┬───────────────────────────┘   │
│                           │ API                             │
│  ┌─────────────────────────▼───────────────────────────┐   │
│  │          SuiteCRM Backend (Port 8080)               │   │
│  │  ┌──────────────┐  ┌─────────────────────────────┐ │   │
│  │  │  Core CRM    │  │    Custom Extensions       │ │   │
│  │  │  Modules     │  │  • AI Services             │ │   │
│  │  │              │  │  • Form Builder            │ │   │
│  │  │              │  │  • Knowledge Base          │ │   │
│  │  │              │  │  • Activity Tracking       │ │   │
│  │  └──────────────┘  └─────────────────────────────┘ │   │
│  └─────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                   Data Layer                         │   │
│  │  ┌────────────┐  ┌──────────────┐  ┌────────────┐ │   │
│  │  │   MySQL    │  │    Redis     │  │  Storage   │ │   │
│  │  │    DB      │  │   (Cache)    │  │  (Files)   │ │   │
│  │  └────────────┘  └──────────────┘  └────────────┘ │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

## 📋 Core Requirements

### Technology Stack
- **Backend**: SuiteCRM 7.14.6 with v8 REST API
- **Frontend**: React 19 + TypeScript + Vite
- **UI**: shadcn/ui + Tailwind CSS
- **State Management**: React Query + Zustand
- **Forms**: React Hook Form + Zod
- **Deployment**: Docker Compose
- **AI**: OpenAI API (GPT-3.5/GPT-4)

### Core CRM Modules (From SuiteCRM)
1. **Accounts** - Customer companies
2. **Contacts** - People at companies
3. **Leads** - Unqualified prospects
4. **Opportunities** - Sales pipeline
5. **Cases** - Support tickets
6. **Activities** - Calls, Meetings, Tasks, Notes
7. **Documents** - Files and attachments
8. **Users** - Team members
9. **Emails** - Communication history (view-only)

### Custom Features (6 Additional)
1. **AI Lead Scoring** - Automatic qualification based on behavior and firmographics
2. **AI Chatbot** - Embeddable widget for lead capture and support
3. **Form Builder** - Drag-drop form creation with embed codes
4. **Knowledge Base** - Self-service documentation portal
5. **Website Activity Tracking** - Visitor behavior analytics
6. **Customer Health Scoring** - Churn prediction and account monitoring

## 🔄 User Journey & Demo Flow

### Visitor Journey (Marketing Site)
```
1. Visitor lands on marketing site
   ↓ (Activity tracked)
2. Browses features, pricing, docs
   ↓ (AI analyzes behavior)
3. Searches Knowledge Base
   ↓ (Intent captured)
4. Engages with AI Chatbot
   ↓ (Qualified as lead)
5. Fills out demo request form
   ↓ (Lead created in CRM)
6. Books demo meeting
   ↓ (Activity scheduled)
```

### Sales Rep Journey (CRM)
```
1. Receives lead notification
   ↓ (AI score: 85/100)
2. Reviews visitor activity history
   ↓ (Pricing page: 5 visits)
3. Qualifies lead → Creates Opportunity
   ↓ (Links to Account)
4. Conducts demo meeting
   ↓ (Logs activity)
5. Sends proposal
   ↓ (Updates stage)
6. Closes deal
   ↓ (Account → Customer)
```

### Customer Success Journey
```
1. Customer assigned to CS rep
   ↓ (Onboarding begins)
2. Monitors health score
   ↓ (Usage, engagement)
3. Customer opens support ticket
   ↓ (Via chatbot)
4. CS creates KB article
   ↓ (Reduces future tickets)
5. Identifies upsell opportunity
   ↓ (Creates opportunity)
```

## 🎨 Frontend Requirements

### Core Pages (MVP)

#### 1. **Dashboard**
- Pipeline funnel visualization
- Today's activities
- Lead alerts (new high-score leads)
- Key metrics (MRR, pipeline value)
- Recent visitor activity

#### 2. **Leads Management**
- Table view with AI scores
- Quick actions (qualify, assign, disqualify)
- Activity timeline
- Bulk operations

#### 3. **Accounts & Contacts**
- Company 360° view
- Related contacts grid
- Subscription details
- Health score visualization
- Activity history

#### 4. **Opportunities Pipeline**
- Kanban board (drag-drop)
- Stage progression
- Forecast view
- Quick edit inline

#### 5. **Cases (Support)**
- Ticket list with priorities
- Customer context
- KB article suggestions
- SLA tracking

#### 6. **Form Builder**
- Drag-drop interface
- Field types: text, email, select, checkbox
- Conditional logic
- Style customization
- Embed code generator

#### 7. **Knowledge Base**
- Article editor (rich text)
- Categories & tags
- Search functionality
- Public/private toggle
- Analytics (views, helpfulness)

#### 8. **Activity Tracking**
- Live visitor feed
- Session timeline
- Page engagement metrics
- Click heatmaps
- Visitor identification

### Design System
- **Theme**: Modern, clean, professional
- **Colors**: Blue primary, gray secondary
- **Components**: shadcn/ui primitives
- **Responsive**: Mobile-first design
- **Animations**: Subtle, purposeful
- **Accessibility**: WCAG 2.1 AA compliant

## 🔧 Backend Requirements

### SuiteCRM Extensions Structure
```
backend/custom/
├── Extension/
│   └── modules/
│       ├── Leads/
│       │   └── Ext/
│       │       └── Vardefs/
│       │           └── ai_score_fields.php
│       └── Accounts/
│           └── Ext/
│               └── Vardefs/
│                   └── health_score_fields.php
├── modules/
│   ├── FormBuilder/
│   ├── KnowledgeBase/
│   ├── ActivityTracking/
│   └── AIChat/
└── api/
    ├── v8/
    │   └── routes/
    │       ├── ai-scoring.php
    │       ├── chatbot.php
    │       ├── forms.php
    │       ├── kb.php
    │       └── tracking.php
    └── services/
        ├── OpenAIService.php
        ├── TrackingService.php
        └── HealthScoringService.php
```

### API Endpoints (v8 REST API)

#### Authentication
- `POST /api/v8/login` - JWT authentication
- `POST /api/v8/refresh` - Refresh token

#### Core CRM
- `GET/POST/PUT/DELETE /api/v8/modules/{module}` - Standard CRUD
- `GET /api/v8/modules/{module}/relationships/{link}` - Related records

#### Custom Endpoints
- `POST /api/v8/ai/score-lead` - AI lead scoring
- `POST /api/v8/ai/chat` - Chatbot conversation
- `GET/POST /api/v8/forms` - Form builder
- `GET/POST /api/v8/kb/articles` - Knowledge base
- `POST /api/v8/tracking/events` - Activity tracking
- `GET /api/v8/health/score/{account_id}` - Health scoring

### Database Schema (Custom Tables)
```sql
-- Form Builder
CREATE TABLE form_builder_forms (
    id CHAR(36) PRIMARY KEY,
    name VARCHAR(255),
    fields JSON,
    settings JSON,
    embed_code TEXT,
    created_by CHAR(36),
    date_created DATETIME
);

-- Knowledge Base
CREATE TABLE knowledge_base_articles (
    id CHAR(36) PRIMARY KEY,
    title VARCHAR(255),
    content TEXT,
    category_id CHAR(36),
    tags JSON,
    is_public BOOLEAN,
    views INT DEFAULT 0,
    helpful_yes INT DEFAULT 0,
    helpful_no INT DEFAULT 0
);

-- Activity Tracking
CREATE TABLE website_sessions (
    id CHAR(36) PRIMARY KEY,
    visitor_id VARCHAR(255),
    lead_id CHAR(36),
    ip_address VARCHAR(45),
    user_agent TEXT,
    pages_viewed JSON,
    total_time INT,
    created_date DATETIME
);

-- AI Chat Sessions
CREATE TABLE ai_chat_sessions (
    id CHAR(36) PRIMARY KEY,
    visitor_id VARCHAR(255),
    lead_id CHAR(36),
    messages JSON,
    context JSON,
    created_date DATETIME
);
```

## 🤖 AI Integration Details

### Lead Scoring Algorithm
```javascript
// Factors for AI scoring (0-100)
{
  companySize: 20,        // Employee count
  industry: 15,           // Target industry match
  behavior: 25,           // Website activity
  engagement: 20,         // Form fills, downloads
  chatIntent: 20          // AI chat analysis
}
```

### Chatbot Capabilities
1. **Lead Qualification**
   - Ask qualifying questions
   - Capture contact info
   - Route to sales/support

2. **Knowledge Base Search**
   - Semantic search using embeddings
   - Suggest relevant articles
   - Fallback to GPT for complex queries

3. **Meeting Scheduling**
   - Show available slots
   - Book demo meetings
   - Send calendar invites

### Health Scoring Metrics
- Login frequency
- Feature usage
- Support ticket volume
- User growth/decline
- Contract value
- Engagement trends

## 📊 Website Activity Tracking

### Implementation Options (Ranked by Simplicity)

1. **Custom Lightweight Tracker** ✅ (Recommended)
   - Basic JavaScript snippet
   - Track: page views, time spent, clicks
   - Store in MySQL/Redis
   - Integrate heatmap.js for visual analytics

2. **Plausible Analytics** (Alternative)
   - Privacy-focused, lightweight
   - Easy self-hosting
   - Clean API for CRM integration

### Tracking Features
- Page views with duration
- Click tracking
- Scroll depth
- Form interactions
- Custom events
- Visitor identification (cookie-based)
- Session grouping

### Tracking Script
```javascript
// Embedded on customer's website
<script>
  (function(w,d,s,l,i){
    w[l]=w[l]||[];
    w[l].push({'track.start': new Date().getTime()});
    var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s);j.async=true;
    j.src='https://crm.yourcompany.com/tracking.js?id='+i;
    f.parentNode.insertBefore(j,f);
  })(window,document,'script','crmTrack','YOUR_SITE_ID');
</script>
```

## 🚀 Implementation Phases

### Phase 1: Foundation (Week 1-2)
- Docker setup with SuiteCRM
- React frontend boilerplate
- Authentication (JWT)
- Basic CRUD for Leads/Accounts
- Simple dashboard

### Phase 2: Core CRM (Week 3-4)
- Opportunities pipeline
- Activities management
- Cases/support tickets
- Email/document viewing
- Role-based access

### Phase 3: AI & Custom Features (Week 5-6)
- AI lead scoring
- Form builder
- Knowledge base
- Basic chatbot
- Activity tracking

### Phase 4: Polish & Demo (Week 7-8)
- Marketing website
- Demo data setup
- Health scoring
- Advanced chatbot features
- Documentation

## 🐳 Deployment Architecture

### Docker Compose Stack
```yaml
services:
  frontend:
    build: ./frontend
    ports: ["3000:3000"]
    
  backend:
    build: ./backend
    ports: ["8080:80"]
    
  mysql:
    image: mysql:8.0
    volumes: ["./data/mysql:/var/lib/mysql"]
    
  redis:
    image: redis:alpine
    
  tracking:
    build: ./tracking
    ports: ["3001:3001"]
```

### Demo Deployment
- **Frontend**: Vercel (React app)
- **Backend**: Railway (SuiteCRM + MySQL)
- **Domain**: Custom domain with SSL
- **CDN**: Cloudflare for assets

## 📈 Success Metrics

### Technical Goals
- Page load < 2 seconds
- API response < 200ms
- 99.9% uptime
- Mobile responsive

### Business Goals
- 70% reduction in manual lead qualification
- 50% decrease in support tickets via KB
- 2x improvement in sales velocity
- 90% user satisfaction score

## 🔒 Security Considerations

- JWT tokens with refresh rotation
- API rate limiting
- Input validation with Zod
- XSS protection
- CORS configuration
- Encrypted data at rest
- Regular security updates

## 📚 Documentation Requirements

1. **Installation Guide** - Docker setup steps
2. **API Documentation** - Swagger/OpenAPI spec
3. **User Guide** - Feature walkthroughs
4. **Developer Guide** - Extension points
5. **Demo Script** - Sales demonstration flow

## KEY DECESIONS MADE: