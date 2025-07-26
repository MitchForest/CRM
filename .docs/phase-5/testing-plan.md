# Phase 5 Testing Plan

## Overview
This document outlines the comprehensive testing strategy for Phase 5. Each feature must pass all tests before being marked as complete.

## Testing Principles
1. **Manual Testing First** - A human must test each feature
2. **Real Data Only** - No mock data in tests
3. **End-to-End** - Test complete workflows, not just individual components
4. **Multiple Roles** - Test with different user permissions
5. **Error Scenarios** - Test failure cases, not just success

## 1. Authentication & Authorization Testing

### Login Flow
- [ ] Can login with valid credentials
- [ ] Cannot login with invalid credentials
- [ ] Proper error messages shown
- [ ] Token stored correctly in frontend
- [ ] Redirect to dashboard after login
- [ ] Session persists on page refresh

### Token Management
- [ ] Access token expires after 15 minutes
- [ ] Refresh token automatically refreshes access token
- [ ] User logged out when refresh token expires
- [ ] Multiple API calls don't cause multiple refresh attempts
- [ ] Logout clears all tokens

### Role-Based Access
- [ ] Admin can access all features
- [ ] Sales Rep can only access leads/opportunities
- [ ] Customer Success can only access customers/support
- [ ] Unauthorized access shows proper error
- [ ] Navigation reflects user permissions

## 2. Lead Management Testing

### Lead Capture Forms
1. Create a test form in Form Builder
   - [ ] Add various field types (text, email, dropdown, etc.)
   - [ ] Set some fields as required
   - [ ] Generate embed code
   
2. Embed form on test page
   - [ ] Form renders correctly
   - [ ] Styling doesn't conflict with page
   - [ ] Required fields validated
   - [ ] Form submission works
   
3. Verify lead creation
   - [ ] Lead appears in CRM immediately
   - [ ] All form data captured correctly
   - [ ] Visitor tracking data associated
   - [ ] Activity recorded in timeline

### Manual Lead Creation
- [ ] Create lead with minimum required fields
- [ ] Create lead with all fields
- [ ] Validation prevents invalid data
- [ ] Lead appears in list immediately
- [ ] Can edit lead after creation
- [ ] Can delete lead (soft delete)

### Lead Qualification
- [ ] Can change lead status
- [ ] Status changes recorded in activity
- [ ] Can convert qualified lead to opportunity
- [ ] Lead data transfers to opportunity
- [ ] Original lead marked as converted

## 3. Visitor Tracking Testing

### Tracking Script
1. Embed tracking script on test site
   - [ ] Script loads without errors
   - [ ] Doesn't slow down page load
   - [ ] Works with ad blockers disabled
   
2. Verify tracking data
   - [ ] Page views recorded
   - [ ] Time on page accurate
   - [ ] Referrer captured
   - [ ] Device/browser info correct
   
3. Visitor to Lead Association
   - [ ] Anonymous visitor tracked
   - [ ] When form submitted, visitor linked to lead
   - [ ] Previous activity shows in lead timeline
   - [ ] Return visits tracked correctly

## 4. AI Chatbot Testing

### Basic Functionality
- [ ] Chatbot widget loads on page
- [ ] Can minimize/maximize chat
- [ ] Chat persists across page navigation
- [ ] Messages send and receive properly
- [ ] Typing indicators work

### AI Responses
- [ ] Bot answers questions using KB articles
- [ ] Provides relevant responses
- [ ] Admits when doesn't know answer
- [ ] Suggests related articles
- [ ] Response time acceptable (<3 seconds)

### Lead Capture
1. New visitor scenario
   - [ ] Bot asks for contact info
   - [ ] Creates lead when info provided
   - [ ] Doesn't ask again on return visit
   
2. Returning lead scenario
   - [ ] Bot recognizes returning lead
   - [ ] Shows personalized greeting
   - [ ] Conversation history available
   - [ ] Updates lead activity

### Support Ticket Creation
- [ ] Bot can create support tickets
- [ ] Captures issue description
- [ ] Assigns to correct queue
- [ ] Notifies support team
- [ ] Ticket appears in contact timeline

## 5. Knowledge Base Testing

### Public Access
- [ ] Articles accessible without login
- [ ] SEO-friendly URLs work
- [ ] Search returns relevant results
- [ ] Categories filter correctly
- [ ] Article views tracked

### Admin Functions
- [ ] Can create new article
- [ ] Rich text editor works
- [ ] Can add images/media
- [ ] Preview before publishing
- [ ] Can edit existing articles
- [ ] Can unpublish articles
- [ ] Changes reflect immediately

### AI Integration
- [ ] Chatbot references KB articles
- [ ] Provides links to full articles
- [ ] Uses latest article content
- [ ] Handles deleted articles gracefully

## 6. Contact/Account Testing

### Unified View
- [ ] All activity in chronological order
- [ ] Website visits shown
- [ ] Form submissions shown
- [ ] Chat conversations shown
- [ ] Support tickets shown
- [ ] Meetings/calls shown
- [ ] Score displayed prominently

### Activity Recording
- [ ] Can log call with notes
- [ ] Can schedule meeting
- [ ] Can send email (tracked)
- [ ] Can add general note
- [ ] Activities appear immediately
- [ ] Can edit/delete activities

### Assignment
- [ ] Can assign sales rep
- [ ] Can assign customer success rep
- [ ] Assignments tracked in history
- [ ] Notifications sent on assignment

## 7. Opportunity Pipeline Testing

### Opportunity Management
- [ ] Can create from qualified lead
- [ ] Can create manually
- [ ] Required fields validated
- [ ] Amount calculations correct
- [ ] Probability updates by stage

### Pipeline View
- [ ] Drag-drop between stages works
- [ ] Stage changes tracked
- [ ] Filters work correctly
- [ ] Totals calculate accurately
- [ ] Performance with many opportunities

### Win/Loss
- [ ] Can mark as won
- [ ] Can mark as lost with reason
- [ ] Customer created on win
- [ ] Reports update immediately

## 8. Support Ticket Testing

### Ticket Creation
- [ ] Via embedded form
- [ ] Via AI chat
- [ ] Manual creation in CRM
- [ ] Email notifications sent
- [ ] Appears in queue immediately

### Ticket Management
- [ ] Can assign to team member
- [ ] Can change priority
- [ ] Can add internal notes
- [ ] Can respond to customer
- [ ] Status changes tracked
- [ ] SLA tracking works

### Customer View
- [ ] Customer can see their tickets
- [ ] Status updates visible
- [ ] Can add comments
- [ ] Receives notifications

## 9. Scoring System Testing

### Lead Scoring
- [ ] Score calculates on creation
- [ ] Updates based on activity
- [ ] Website behavior affects score
- [ ] Company data affects score
- [ ] Scores visible in list/detail views

### Health Scoring
- [ ] Calculates for all customers
- [ ] Updates based on:
  - Support ticket volume
  - Last activity date
  - User engagement
- [ ] At-risk customers flagged
- [ ] Trends visible over time

## 10. Admin Dashboard Testing

### Form Builder
- [ ] Can create forms
- [ ] Can edit forms
- [ ] Can delete forms
- [ ] Embed codes work
- [ ] Form submissions tracked

### Knowledge Base Admin
- [ ] Article management works
- [ ] Categories manageable
- [ ] Analytics visible
- [ ] Bulk operations work

### Chatbot Config
- [ ] Can update prompts
- [ ] Can set business hours
- [ ] Can configure escalation
- [ ] Changes apply immediately

### Tracking Config
- [ ] Can exclude pages
- [ ] Can set tracking parameters
- [ ] Generate new tracking code
- [ ] Instructions clear

## 11. Performance Testing

### Load Times
- [ ] Dashboard loads < 3 seconds
- [ ] Lists load < 2 seconds
- [ ] Forms submit < 1 second
- [ ] Search returns < 1 second

### Concurrent Users
- [ ] Test with 10 concurrent users
- [ ] No deadlocks or conflicts
- [ ] Performance remains acceptable
- [ ] Real-time updates work

### Data Volume
- [ ] Test with 10k+ leads
- [ ] Test with 1k+ opportunities
- [ ] Test with 5k+ activities
- [ ] Pagination works correctly
- [ ] Searches remain fast

## 12. Integration Testing

### Complete Customer Journey
1. Anonymous visitor browses site (tracking active)
2. Reads 3 KB articles (tracked)
3. Initiates chat, asks questions (AI responds using KB)
4. Submits demo request form (lead created)
5. Sales rep sees lead with full history
6. Sales rep qualifies and creates opportunity
7. Multiple meetings/calls logged
8. Opportunity won (customer created)
9. Customer submits support ticket
10. Health score calculated
11. All history visible in unified view

### Multi-Channel Test
- [ ] Same lead through multiple channels
- [ ] All data consolidated correctly
- [ ] No duplicate records
- [ ] Timeline accurate

## 13. Error Handling Testing

### API Errors
- [ ] Network timeout handled gracefully
- [ ] 500 errors show user-friendly message
- [ ] 404 errors handled properly
- [ ] Validation errors show clearly

### Form Errors
- [ ] Required field validation works
- [ ] Email format validation
- [ ] Clear error messages
- [ ] Errors don't break form

### Permission Errors
- [ ] Unauthorized access blocked
- [ ] Clear error messages
- [ ] Redirect to appropriate page
- [ ] No data leaks

## 14. Mobile Testing

### Responsive Design
- [ ] All pages work on mobile
- [ ] Navigation accessible
- [ ] Forms usable on touch
- [ ] Tables scroll properly
- [ ] Modals work correctly

### Touch Interactions
- [ ] Drag-drop works (pipeline)
- [ ] Swipe gestures work
- [ ] Tap targets large enough
- [ ] No hover-dependent features

## 15. Security Testing

### Authentication
- [ ] Can't access without login
- [ ] Token can't be forged
- [ ] Sessions expire properly
- [ ] No sensitive data in localStorage

### Authorization
- [ ] Can't access others' data
- [ ] Role restrictions enforced
- [ ] API permissions checked
- [ ] No privilege escalation

### Input Validation
- [ ] SQL injection prevented
- [ ] XSS prevented
- [ ] CSRF protected
- [ ] File upload restrictions

## Test Execution Checklist

For each major feature:
1. [ ] Test happy path
2. [ ] Test error cases
3. [ ] Test edge cases
4. [ ] Test with different roles
5. [ ] Test on mobile
6. [ ] Test performance
7. [ ] Document any issues
8. [ ] Verify fixes
9. [ ] Retest after fixes
10. [ ] Sign off as complete

## Acceptance Criteria

A feature is considered complete when:
- All test cases pass
- No critical bugs remain
- Performance is acceptable
- Works on all supported browsers
- Works on mobile devices
- Proper error handling exists
- Documentation is updated

---

**Remember: If it's not tested, it's not done!**