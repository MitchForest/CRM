# CRM Project - Setup and Testing Guide

A modern CRM system built with SuiteCRM backend and React TypeScript frontend, featuring AI-powered capabilities and advanced B2B sales management.

## Project Structure

```
crm/
├── backend/          # SuiteCRM 8.6.1 with custom API
├── frontend/         # React 18 + TypeScript + Vite
├── .docs/           # Phase documentation
└── README.md        # This file
```

## Quick Start

### Prerequisites
- Docker & Docker Compose
- Node.js 18+
- npm or yarn
- Git

### 1. Start Backend Services

```bash
# Navigate to backend directory
cd backend

# Start Docker containers (MySQL + SuiteCRM)
docker-compose up -d

# Verify containers are running
docker ps

# Expected output:
# - suitecrm-backend (port 8080)
# - suitecrm-mysql (port 3306)

# Check backend health
curl http://localhost:8080/custom-api/health

# View logs if needed
docker logs -f suitecrm-backend
```

### 2. Start Frontend Application

```bash
# Navigate to frontend directory
cd frontend

# Install dependencies
npm install

# Start development server
npm run dev

# Frontend available at: http://localhost:5173
```

### 3. Login Credentials

- **URL**: http://localhost:5173/login
- **Username**: `admin`
- **Password**: `admin123`

## Phase Status

### ✅ Phase 1 - Foundation (Complete)
- Basic CRM modules (Leads, Accounts, Contacts)
- Authentication system
- Modern React UI with TypeScript
- API integration

### ✅ Phase 2 - Core CRM Features (Complete)
- Real-time Dashboard with metrics
- Opportunities with Kanban board
- Activities management (Calls, Meetings, Tasks, Notes)
- Cases with priority system (P1/P2/P3)
- Drag-drop functionality
- Cross-module integration

### 🚧 Phase 3 - AI & Advanced Features (Pending)
- AI Lead Scoring with OpenAI
- Form Builder (drag-drop)
- Knowledge Base
- AI Chatbot
- Website Activity Tracking

## Testing Phase 2 Features

### Dashboard
After login, verify:
- **Metric Cards**: Total Leads, Accounts, New Leads Today, Pipeline Value
- **Pipeline Chart**: 8 B2B sales stages with hover details
- **Activity Metrics**: Today's calls/meetings, overdue tasks
- **Case Distribution**: P1/P2/P3 pie chart
- **Recent Activities**: Latest 10 items from all modules

### Opportunities Module
Key features to test:
1. **Kanban View**:
   - Toggle between Table and Pipeline view
   - Drag opportunities between stages
   - Verify automatic probability updates:
     - Qualification: 10%
     - Needs Analysis: 20%
     - Value Proposition: 40%
     - Decision Makers: 60%
     - Proposal: 75%
     - Negotiation: 90%
     - Closed Won: 100%
     - Closed Lost: 0%

2. **CRUD Operations**:
   - Create new opportunity
   - Edit existing opportunity
   - Delete opportunity
   - Search and filter

### Activities Module
Test each activity type:
- **Calls**: Schedule, link to records, mark as held
- **Meetings**: Add location, set duration, manage status
- **Tasks**: Set priorities (High/Medium/Low), track overdue
- **Notes**: Rich text, attach to parent records

### Cases Module
Priority-based support:
- **P1 (Critical)**: Red badge, 4-hour SLA
- **P2 (Medium)**: Yellow badge, 24-hour SLA
- **P3 (Low)**: Green badge, 72-hour SLA

Test workflow: New → Assigned → Pending Input → Resolved → Closed

## Running Tests

### Unit Tests
```bash
cd frontend
npm run test
```

### Integration Tests
```bash
cd frontend
npm run test:integration

# Run specific test suite
npm run test:integration dashboard.integration.test.ts
npm run test:integration opportunities.integration.test.ts
npm run test:integration activities.integration.test.ts
npm run test:integration cases.integration.test.ts
npm run test:integration e2e-workflows.test.ts
```

### Manual Testing
Use the comprehensive checklist:
```bash
cat frontend/tests/manual-testing-checklist.md
```

## API Endpoints

### SuiteCRM V8 API
- Base URL: `http://localhost:8080/Api/V8`
- Authentication: OAuth2
- Used for: CRUD operations on all modules

### Custom API (Phase 2)
- Base URL: `http://localhost:8080/custom-api`
- Authentication: JWT
- Endpoints:
  - `POST /auth/login` - Authentication
  - `GET /dashboard/metrics` - Dashboard stats
  - `GET /dashboard/pipeline` - Opportunity pipeline
  - `GET /dashboard/activities` - Activity metrics
  - `GET /dashboard/cases` - Case statistics

## Development

### Frontend Structure
```
frontend/src/
├── components/      # Reusable UI components
├── pages/          # Route pages
├── hooks/          # Custom React hooks
├── lib/            # Utilities and API client
├── stores/         # Zustand stores
└── types/          # TypeScript definitions
```

### Add New Features
1. Update types in `src/types/`
2. Create/update hooks in `src/hooks/`
3. Build components in `src/components/`
4. Add pages in `src/pages/`
5. Update routes in `src/App.tsx`

### Code Quality
```bash
# Type checking
npm run type-check

# Linting
npm run lint

# Format code
npm run format
```

## Troubleshooting

### Backend Issues
```bash
# Check container status
docker ps -a

# View logs
docker logs suitecrm-backend
docker logs suitecrm-mysql

# Restart containers
docker-compose restart

# Reset everything
docker-compose down -v
docker-compose up -d
```

### Frontend Issues
```bash
# Clear cache
rm -rf node_modules .vite
npm install

# Check for TypeScript errors
npm run type-check

# View browser console
# Press F12 in browser
```

### Common Problems

**Dashboard shows no data**
- Create test records first
- Check API connection in Network tab (F12)

**Drag-drop not working**
- Ensure you're in Pipeline view
- Check browser console for errors

**Login fails**
- Verify backend is running
- Check credentials: admin/admin123
- Clear browser storage

## Performance Benchmarks

| Operation | Target | Expected |
|-----------|--------|----------|
| Initial Load | < 3s | ~2s |
| Dashboard | < 1s | ~800ms |
| Page Navigation | < 1s | ~500ms |
| Search/Filter | < 300ms | ~200ms |
| Drag-Drop | < 500ms | ~400ms |

## Contributing

### Setup Development Environment
1. Fork the repository
2. Clone your fork
3. Create feature branch: `git checkout -b feature/your-feature`
4. Make changes
5. Run tests: `npm run test`
6. Commit: `git commit -m "Add your feature"`
7. Push: `git push origin feature/your-feature`
8. Create Pull Request

### Coding Standards
- TypeScript strict mode
- ESLint rules enforced
- Prettier formatting
- Component-based architecture
- Comprehensive error handling

## Phase 3 Preview

Coming next:
- **AI Lead Scoring**: Automatic lead qualification
- **Form Builder**: Drag-drop custom forms
- **Knowledge Base**: Self-service portal
- **AI Chatbot**: Customer support automation
- **Activity Tracking**: Website visitor insights

## Resources

- [Phase 1 Documentation](.docs/phase-1/)
- [Phase 2 Documentation](.docs/phase-2/)
- [Phase 3 Documentation](.docs/phase-3/)
- [API Documentation](backend/API_DOCUMENTATION.md)
- [Integration Tests](frontend/tests/integration/README.md)
- [Manual Testing Checklist](frontend/tests/manual-testing-checklist.md)

## License

This project is proprietary software. All rights reserved.

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review test files for examples
3. Check Docker logs for backend issues
4. Review browser console for frontend issues

---

**Current Status**: Phase 2 Complete ✅ | Ready for Production Testing