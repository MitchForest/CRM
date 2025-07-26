# AI-Powered CRM - Phase 5 Complete

## Overview
This CRM has been simplified and prepared for production deployment. All core features work with real data, technical debt has been removed, and the system follows consistent patterns throughout.

## Key Features

### 🎯 Lead Management
- Simplified lead lifecycle: New → Contacted → Qualified
- AI-powered lead scoring
- Automatic lead capture from forms, chat, and tracking
- Full activity timeline for each lead

### 💼 Sales Pipeline
- Opportunities: Qualified → Proposal → Negotiation → Won/Lost
- Visual pipeline management
- Automatic customer creation on won deals
- Lead to opportunity conversion

### 👥 Unified Contacts
- Single view for all contact interactions
- Combined timeline of activities, opportunities, and support
- Supports both people and companies
- Rich activity history

### 🤖 AI Integration
- Intelligent chatbot with knowledge base
- Automatic lead qualification
- Smart conversation routing
- Context-aware responses

### 📊 Analytics & Tracking
- Visitor behavior tracking
- Form submission analytics
- Engagement scoring
- Real-time dashboards

### 🛠️ Admin Tools
- Drag-and-drop form builder
- Knowledge base editor
- Embed code generation
- User management

## Quick Start

### Prerequisites
- Docker & Docker Compose
- Node.js 18+
- MySQL 8.0+

### Installation

1. **Clone repository**
   ```bash
   git clone https://github.com/your-org/crm.git
   cd crm
   ```

2. **Configure environment**
   ```bash
   # Backend
   cp backend/.env.example backend/.env
   # Edit backend/.env with your settings
   
   # Frontend  
   cp frontend/.env.example frontend/.env
   # Edit frontend/.env if needed
   ```

3. **Start services**
   ```bash
   docker-compose up -d
   ```

4. **Initialize database**
   ```bash
   # Reset database (warning: deletes all data)
   docker-compose exec backend php custom/install/reset_database.php
   
   # Seed with test data
   docker-compose exec backend php custom/install/seed_phase5_data.php
   ```

5. **Access CRM**
   - Frontend: http://localhost:5173
   - API: http://localhost:8080/custom/api
   - Login: john.doe@example.com / admin123

## Architecture

### Technology Stack
- **Backend**: PHP 8.1, SuiteCRM core (hidden modules)
- **Frontend**: React 18, TypeScript, Tailwind CSS
- **Database**: MySQL 8.0
- **AI**: OpenAI GPT-4
- **Caching**: Redis (optional)
- **Search**: Built-in full-text search

### Simplified Data Model
```
Leads (New → Contacted → Qualified)
  ↓
Opportunities (Qualified → Proposal → Negotiation → Won/Lost)
  ↓
Customers (from Won opportunities)

Contacts (Unified people & companies)
Support Tickets (Open → In Progress → Resolved → Closed)
```

### API Structure
All APIs follow RESTful patterns:
```
GET    /api/v8/{resource}      # List
GET    /api/v8/{resource}/{id} # Get one
POST   /api/v8/{resource}      # Create
PUT    /api/v8/{resource}/{id} # Update
DELETE /api/v8/{resource}/{id} # Delete
```

## Embedding Features

### 1. Activity Tracking
```html
<script>
window.SUITECRM_TRACKER = {
  api_url: 'https://your-crm.com'
};
</script>
<script src="https://your-crm.com/custom/public/js/tracking.js"></script>
```

### 2. Lead Capture Forms
```html
<div data-form-id="your-form-id"></div>
<script>
window.SUITECRM_URL = 'https://your-crm.com';
</script>
<script src="https://your-crm.com/custom/public/js/forms-embed.js"></script>
```

### 3. AI Chat Widget
```html
<script>
window.SUITECRM_CHAT = {
  api_url: 'https://your-crm.com',
  position: 'bottom-right'
};
</script>
<script src="https://your-crm.com/custom/public/js/chat-widget.js"></script>
```

## Configuration

### Required Environment Variables

**Backend (.env)**
```env
JWT_SECRET=your-secure-secret
OPENAI_API_KEY=sk-your-key
DATABASE_URL=mysql://user:pass@host:3306/db
```

**Frontend (.env)**
```env
VITE_API_URL=http://localhost:8080/custom/api
```

## Testing

### Automated Tests
```bash
# Backend tests
cd backend && ./vendor/bin/phpunit

# Frontend tests
cd frontend && npm test
```

### Manual Testing
See `.docs/phase-5/testing-guide.md` for comprehensive testing procedures.

## Deployment

See `.docs/phase-5/deployment-guide.md` for production deployment instructions.

### Quick Deploy
```bash
# Build for production
cd frontend && npm run build

# Deploy with Docker
docker-compose -f docker-compose.prod.yml up -d
```

## Documentation

- **Phase 5 Docs**: `.docs/phase-5/`
  - `todo.md` - Implementation checklist
  - `testing-guide.md` - Testing procedures
  - `deployment-guide.md` - Production deployment
  - `quick-reference.md` - Developer reference
  - `implementation-status.md` - Current status

## API Reference

### Authentication
```bash
POST /api/v8/auth/login
POST /api/v8/auth/refresh
POST /api/v8/auth/logout
```

### Core Resources
```bash
# Leads
GET/POST    /api/v8/leads
GET/PUT/DEL /api/v8/leads/{id}

# Contacts  
GET         /api/v8/contacts
GET         /api/v8/contacts/{id}/unified

# Opportunities
GET/POST    /api/v8/opportunities
GET/PUT/DEL /api/v8/opportunities/{id}

# Support Tickets
GET/POST    /api/v8/cases
GET/PUT/DEL /api/v8/cases/{id}
```

### AI & Analytics
```bash
# Chat
POST /api/v8/ai/chat
GET  /api/v8/ai/chat/{conversation_id}

# Lead Scoring
POST /api/v8/leads/{id}/ai-score
POST /api/v8/leads/ai-score-batch

# Tracking
POST /api/v8/track/pageview
POST /api/v8/track/event
```

## Support

### Known Limitations
- Email sending uses placeholders (configure SMTP for production)
- File uploads limited to documents
- Single language (English)
- Basic reporting only

### Troubleshooting
1. **JWT errors**: Check JWT_SECRET in .env
2. **AI not working**: Verify OPENAI_API_KEY
3. **Database errors**: Run reset_database.php
4. **CORS issues**: Check allowed domains configuration

## Contributing

1. Fork the repository
2. Create feature branch
3. Follow existing patterns
4. Test thoroughly
5. Submit pull request

## License

[Your License Here]

---

**Phase 5 Status**: ✅ COMPLETE - Ready for production deployment

For questions or support, contact: support@yourcompany.com