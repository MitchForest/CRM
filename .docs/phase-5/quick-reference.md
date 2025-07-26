# Phase 5 Quick Reference Guide

## 🎯 Core Goal
Transform the CRM from a complex prototype into a simple, functional production system with ONLY the essential features working perfectly.

## 📝 Simplified Approach
- **No data migration** - Clear database and recreate with seed data
- **No module deletion** - Just hide/disable unused SuiteCRM modules  
- **Basic rate limiting** - Simple login attempt limiting only (if needed)
- **Keep it simple** - Don't over-engineer solutions

## 🔑 Key Principles
1. **If it doesn't work, fix it or remove it**
2. **No mock data anywhere**
3. **Test everything before marking complete**
4. **Simple is better than complex**
5. **Consistent patterns everywhere**

## 📊 Simplified Data Model

### Leads
- Statuses: `New` → `Contacted` → `Qualified`
- When qualified, can create Opportunity

### Opportunities  
- Stages: `Qualified` → `Proposal` → `Negotiation` → `Won`/`Lost`
- Linked to original Lead
- When Won, creates Customer

### Contacts (Unified)
- Can be person OR company (is_company flag)
- Single table for all contacts
- Rich activity timeline

### Support Tickets (renamed from Cases)
- Types: `Technical`, `Billing`, `Feature Request`, `Other`
- Statuses: `Open` → `In Progress` → `Resolved` → `Closed`

## 🚀 Core Features That Must Work

### 1. Lead Capture & Tracking
- ✅ Embeddable forms that create leads
- ✅ Website tracking script
- ✅ Visitor → Lead association
- ✅ Full activity timeline

### 2. AI Chatbot
- ✅ Embeddable widget
- ✅ Uses knowledge base
- ✅ Recognizes returning visitors
- ✅ Can create leads and tickets

### 3. Knowledge Base
- ✅ Public articles
- ✅ Admin can create/edit
- ✅ AI can reference
- ✅ Search works

### 4. Unified Contact View
- ✅ All activity in one timeline
- ✅ Assigned reps visible
- ✅ Scores displayed
- ✅ Quick actions available

### 5. Admin Dashboard
- ✅ Form builder
- ✅ KB management
- ✅ Chatbot config
- ✅ Get embed codes

## 🗑️ What to Remove/Hide
- ❌ Hide from UI: Projects, Campaigns, Documents, Contracts, Quotes, Products
- ❌ Delete all backup files (*_old, *_backup, etc.)
- ❌ Delete mock data files (data.json)
- ❌ Remove unused frontend pages and components
- ❌ Don't implement complex workflows not in core features

## 🔧 Technical Requirements

### Authentication
```javascript
// Must support:
- JWT with refresh tokens
- Role-based access (Admin, Sales Rep, Customer Success)
- Automatic token refresh
- Proper logout
```

### API Pattern
```javascript
// All APIs must follow:
GET    /api/{resource}      // List
GET    /api/{resource}/{id} // Get one
POST   /api/{resource}      // Create
PUT    /api/{resource}/{id} // Update
DELETE /api/{resource}/{id} // Delete

// Response format:
{
  "success": true,
  "data": {...},
  "meta": {...}
}
```

### Required ENV Variables
```bash
# Backend
JWT_SECRET=secure-random-string
OPENAI_API_KEY=sk-...
DATABASE_URL=mysql://...

# Frontend
VITE_API_URL=http://localhost:8080/custom/api
```

## 📋 Daily Checklist
- [ ] Is this feature in the core requirements?
- [ ] Does it work with real data?
- [ ] Have I tested it manually?
- [ ] Is the code clean and consistent?
- [ ] Have I removed related technical debt?

## 🧪 Quick Test Commands
```bash
# Backend - Reset and seed database
cd backend
php custom/install/reset_database.php  # Clear all data
php custom/install/seed_phase5_data.php # Add test data

# Frontend
cd frontend
npm run test
npm run build # Check for errors

# Test user credentials (from seed data)
Email: john.doe@example.com
Password: admin123
```

## ⚠️ Common Pitfalls
1. **Don't forget to test with different user roles**
2. **Check mobile view for every feature**
3. **Ensure visitor tracking works before testing lead capture**
4. **Test embed codes on actual external site**
5. **Verify AI features have OpenAI key configured**

## 📞 When to Ask for Human Testing
- After completing each major section
- When AI responses seem incorrect
- Before marking any workflow complete
- If unsure about user experience
- When performance seems slow

## 🎉 Definition of Done
A feature is DONE when:
1. Works end-to-end with real data
2. No console errors
3. Handles errors gracefully
4. Works on mobile
5. Different roles see correct data
6. Human has tested and approved

---

**Remember: We're building a SIMPLE, WORKING system. When in doubt, choose the simpler option.**