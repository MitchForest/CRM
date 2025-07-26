# Phase 5 Implementation Order

## Overview
This document provides the recommended order for implementing Phase 5 changes. The order is designed to minimize breaking changes and allow for continuous testing.

## Week 1: Foundation & Cleanup

### Day 1-2: Environment & Security
1. **Set up proper environment variables**
   - Move all secrets to .env files
   - Update JWT secret
   - Configure OpenAI API key properly
   - Set up database credentials

2. **Remove technical debt files**
   - Delete all backup files
   - Remove DashboardOld.tsx
   - Delete unused controllers
   - Clean up example files

3. **Fix authentication**
   - Implement token refresh
   - Add token blacklist
   - Fix rate limiting
   - Test auth flow

### Day 3-4: Database Simplification
1. **Clear and prepare database**
   - Create database reset script
   - Clear all existing data
   - Add any missing fields (lead_id in opportunities, is_company in contacts)
   - Keep SuiteCRM table structure intact

2. **Update code to use simplified model**
   - Update APIs to use simplified statuses/stages
   - Update frontend to show only core modules
   - Hide unused modules in navigation

3. **Remove mock data**
   - Delete data.json
   - Update controllers to use database
   - Test all endpoints return real data

### Day 5: Seed Data
1. **Create comprehensive seed script**
   - Realistic lead data
   - Sample opportunities
   - Test users with different roles
   - Knowledge base articles
   - Activity history

2. **Test seeding**
   - Drop and recreate database
   - Run seed script
   - Verify all data looks correct

## Week 2: Core Features

### Day 6-7: Unified Contact View
1. **Backend API**
   - Create unified contact endpoint
   - Aggregate all related data
   - Implement activity timeline API
   - Add pagination

2. **Frontend implementation**
   - Build unified contact detail page
   - Implement activity timeline
   - Add quick actions
   - Test with different contact types

### Day 8-9: Lead Capture
1. **Form Builder fixes**
   - Ensure embed code generation works
   - Fix form submission endpoint
   - Link to visitor tracking
   - Test on external site

2. **Visitor tracking**
   - Fix tracking script
   - Implement visitor to lead linking
   - Test tracking accuracy
   - Verify data collection

### Day 10: AI Integration
1. **Knowledge Base AI**
   - Implement article embeddings
   - Set up semantic search
   - Test AI responses

2. **Chatbot fixes**
   - Fix chat widget embedding
   - Implement lead recognition
   - Add ticket creation
   - Test conversations

## Week 3: Feature Completion

### Day 11-12: Admin Dashboard
1. **Create admin section**
   - User management
   - Form builder interface
   - KB management
   - Chatbot configuration

2. **Embed code generation**
   - Tracking script generator
   - Chat widget customization
   - Form embed options
   - Clear documentation

### Day 13: Scoring Systems
1. **Lead scoring**
   - Implement AI scoring algorithm
   - Add behavioral factors
   - Update scores automatically
   - Display in UI

2. **Health scoring**
   - Create calculation service
   - Add monitoring factors
   - Flag at-risk customers
   - Test accuracy

### Day 14-15: Support System
1. **Rename cases to support tickets**
   - Update database
   - Update all APIs
   - Update frontend
   - Test ticket flows

2. **Support workflows**
   - Form creation
   - Chat creation
   - Assignment rules
   - Notification system

## Week 4: Polish & Testing

### Day 16-17: API Standardization
1. **Response format consistency**
   - Audit all endpoints
   - Standardize responses
   - Fix error handling
   - Update documentation

2. **Performance optimization**
   - Add missing indexes
   - Implement caching
   - Optimize queries
   - Test with load

### Day 18-19: Frontend Polish
1. **UI/UX consistency**
   - Fix loading states
   - Add error boundaries
   - Improve mobile experience
   - Polish animations

2. **Remove unused code**
   - Delete unused pages
   - Remove dead imports
   - Clean up styles
   - Reduce bundle size

### Day 20-21: Comprehensive Testing
1. **Execute test plan**
   - Run through all test scenarios
   - Document issues
   - Fix critical bugs
   - Retest fixes

2. **End-to-end workflows**
   - Complete customer journey
   - Multi-channel testing
   - Performance testing
   - Security testing

## Implementation Guidelines

### Daily Process
1. **Morning**
   - Review tasks for the day
   - Set up test environment
   - Begin implementation

2. **Implementation**
   - Write code
   - Test as you go
   - Commit frequently
   - Document changes

3. **End of Day**
   - Test completed features
   - Update todo list
   - Note any blockers
   - Plan next day

### Testing Protocol
- After each feature: Basic functionality test
- After each day: Integration test
- After each week: Comprehensive test
- Before completion: Full regression test

### Communication
- Update progress in todo.md daily
- Flag blockers immediately
- Request human testing when needed
- Document all decisions

## Rollback Plan

If issues arise:
1. Git branches for each major change
2. Database backups before migrations
3. Feature flags for risky changes
4. Ability to revert quickly

## Success Metrics

Week 1 complete when:
- All technical debt removed
- Database simplified
- Real data in all APIs

Week 2 complete when:
- Core features working
- Lead capture functional
- AI features integrated

Week 3 complete when:
- All features implemented
- Admin dashboard complete
- Support system working

Week 4 complete when:
- All tests passing
- Performance acceptable
- Ready for production

---

**Remember: It's better to do fewer things well than many things poorly.**