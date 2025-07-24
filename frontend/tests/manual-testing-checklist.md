# Phase 2 Manual Testing Checklist

## Prerequisites
- [ ] Docker containers running (MySQL and SuiteCRM)
- [ ] Backend API accessible at http://localhost:8080
- [ ] Frontend running at http://localhost:5173
- [ ] Test user credentials: admin/admin123

## 1. Authentication & Authorization

### Login
- [ ] Navigate to http://localhost:5173/login
- [ ] Verify login page displays correctly
- [ ] Enter invalid credentials - should show error message
- [ ] Enter valid credentials (admin/admin123) - should redirect to dashboard
- [ ] Verify user info appears in header/sidebar
- [ ] Check "Remember me" functionality works

### Logout
- [ ] Click logout button
- [ ] Verify redirect to login page
- [ ] Try accessing protected route - should redirect to login

### Session Management
- [ ] Keep browser open for extended period - verify token refresh works
- [ ] Open app in multiple tabs - verify all tabs stay authenticated

## 2. Dashboard

### Metrics Cards
- [ ] Verify 4 metric cards display:
  - Total Leads (with icon)
  - Total Accounts (with icon)
  - New Leads Today (with icon)
  - Pipeline Value (formatted as currency)
- [ ] Numbers should be non-negative
- [ ] Loading states should show briefly

### Pipeline Chart
- [ ] Bar chart displays with all 8 stages:
  - Qualification
  - Needs Analysis
  - Value Proposition
  - Decision Makers
  - Proposal
  - Negotiation
  - Closed Won
  - Closed Lost
- [ ] Hover over bars shows count and value
- [ ] Chart is responsive to window resize

### Activity Metrics
- [ ] Verify activity cards show:
  - Calls Today
  - Meetings Today
  - Overdue Tasks
- [ ] Numbers update when activities are created/modified

### Cases by Priority
- [ ] Pie chart shows P1, P2, P3 distribution
- [ ] Colors match priority levels (P1=red, P2=yellow, P3=green)
- [ ] Legend is clickable to toggle segments

### Recent Activities
- [ ] List shows latest 10 activities
- [ ] Each item has appropriate icon
- [ ] Clicking item navigates to detail page
- [ ] Shows mix of different record types

## 3. Opportunities Module

### List View
- [ ] Table displays with columns:
  - Name
  - Account
  - Stage (with color coding)
  - Amount (formatted as currency)
  - Probability (as percentage)
  - Close Date
  - Actions
- [ ] Pagination works correctly
- [ ] Search filters results in real-time
- [ ] Column sorting works for all sortable columns

### Pipeline/Kanban View
- [ ] Toggle between Table and Pipeline view
- [ ] 8 columns for each stage
- [ ] Opportunities display as cards with:
  - Name
  - Account
  - Amount
  - Probability
  - Close Date
- [ ] Drag opportunity between stages:
  - Card moves smoothly
  - Stage updates in backend
  - Probability auto-updates based on stage
  - Success toast appears

### Create Opportunity
- [ ] Click "New Opportunity" button
- [ ] Form displays all fields:
  - Name (required)
  - Account (dropdown)
  - Sales Stage (dropdown)
  - Amount
  - Probability (auto-fills based on stage)
  - Close Date (date picker)
  - Lead Source
  - Description
- [ ] Validation works for required fields
- [ ] Save creates opportunity and redirects to list
- [ ] Cancel returns to list without saving

### Edit Opportunity
- [ ] Click edit icon in table or card
- [ ] Form pre-fills with current data
- [ ] Changing stage updates probability
- [ ] Save updates record
- [ ] Changes reflect immediately in list/pipeline

### Delete Opportunity
- [ ] Click delete icon
- [ ] Confirmation dialog appears
- [ ] Cancel closes dialog without deleting
- [ ] Confirm deletes record and updates list

## 4. Activities Module

### Activities Dashboard
- [ ] Today's Activities section shows current day items
- [ ] Overdue Tasks highlighted in red
- [ ] Upcoming Activities sorted by date/time
- [ ] Activity type badges (Call, Meeting, Task, Note)

### Calls
- [ ] Create new call:
  - Name, Status, Direction fields
  - Date/time picker works
  - Duration inputs
  - Parent record linkage
- [ ] List shows all calls with status indicators
- [ ] Edit call updates properly
- [ ] Mark call as "Held" completes it

### Meetings
- [ ] Create new meeting:
  - Location field
  - Duration (hours and minutes)
  - Status dropdown
  - Reminder options
- [ ] Calendar date/time selection
- [ ] Invitee management (if available)
- [ ] Meeting status updates

### Tasks
- [ ] Create new task:
  - Priority selection (High/Medium/Low)
  - Due date picker
  - Status options
  - Parent record link
- [ ] Overdue tasks show warning
- [ ] Complete task updates status and date
- [ ] Task list filterable by status

### Notes
- [ ] Create note with rich text description
- [ ] Attach to parent records
- [ ] File attachment indicator (if files attached)
- [ ] Notes searchable by content

## 5. Cases Module

### List View
- [ ] Priority indicators (P1=red, P2=yellow, P3=green)
- [ ] Status badges with appropriate colors
- [ ] Case number displayed
- [ ] Search works across case name/number
- [ ] Filter by status and priority

### Create Case
- [ ] Form includes:
  - Name (required)
  - Type dropdown
  - Priority selection (P1/P2/P3)
  - Status
  - Account linkage
  - Description
- [ ] SLA deadline calculates based on priority
- [ ] Save creates case with unique case number

### Case Management
- [ ] Update case status through workflow:
  - New → Assigned → Pending Input → Resolved → Closed
- [ ] Resolution field required when resolving
- [ ] SLA timer/deadline visible
- [ ] Critical (P1) cases highlighted

### Case Metrics
- [ ] Dashboard shows critical cases count
- [ ] Average resolution time displayed
- [ ] Cases by priority breakdown accurate

## 6. Navigation & UI

### Sidebar
- [ ] Logo/brand displayed
- [ ] Menu items highlight when active
- [ ] Collapsible on mobile
- [ ] Icons align with menu items
- [ ] User info displayed

### Breadcrumbs
- [ ] Show current location
- [ ] Clickable to navigate back
- [ ] Update correctly on navigation

### Responsive Design
- [ ] Test on desktop (1920x1080)
- [ ] Test on tablet (768x1024)
- [ ] Test on mobile (375x667)
- [ ] Tables convert to cards on mobile
- [ ] Navigation adapts to screen size

## 7. Performance & Error Handling

### Loading States
- [ ] Skeleton loaders appear while data loads
- [ ] No layout shift after data loads
- [ ] Smooth transitions between states

### Error States
- [ ] Network error shows appropriate message
- [ ] Form validation errors display clearly
- [ ] API errors show user-friendly messages
- [ ] Retry options available where appropriate

### Performance
- [ ] Initial page load < 3 seconds
- [ ] Navigation between pages < 1 second
- [ ] Search/filter responds immediately
- [ ] Drag-drop is smooth without lag

## 8. Data Integrity

### Cross-Module Integration
- [ ] Account appears in opportunity dropdown
- [ ] Activities linked to records show in history
- [ ] Deleting parent warns about children
- [ ] Dashboard numbers match list counts

### Real-time Updates
- [ ] Create record in one tab, appears in another
- [ ] Pipeline changes reflect in dashboard
- [ ] Activity counts update immediately

## 9. Role-Based Access

### Sales Rep Role
- [ ] Can view/edit own records
- [ ] Cannot access admin features
- [ ] Cannot delete certain records

### Manager Role
- [ ] Can view all team records
- [ ] Can reassign records
- [ ] Has access to reports

### Admin Role
- [ ] Full access to all modules
- [ ] Can manage users
- [ ] System configuration access

## 10. Browser Compatibility

Test on:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

## Sign-off

- [ ] All critical features working
- [ ] No console errors in browser
- [ ] Performance acceptable
- [ ] UI/UX consistent throughout
- [ ] Data saves and retrieves correctly

**Tested by:** _______________
**Date:** _______________
**Version:** Phase 2
**Environment:** _______________

## Issues Found

| Issue | Severity | Module | Description | Status |
|-------|----------|---------|-------------|---------|
| | | | | |
| | | | | |

## Notes
_Add any additional observations or recommendations here_