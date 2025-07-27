# Phase 7: Bug Fix Plan

## Overview
This document outlines the plan to fix the identified bugs in the CRM application based on the deep dive investigation.

## Identified Issues

### 1. AI Scoring Not Displayed in UI
**Problem**: AI scoring is implemented in the backend but not integrated into the frontend views (leads, opportunities, contacts).

**Root Cause**: 
- The `AIScoreDisplay` component exists but is not used in the leads list or opportunities pages
- Only leads have AI scoring implemented (not opportunities or contacts)

**Fix Plan**:
1. Add AI score display to `LeadsList.tsx` component
2. Add AI score display to `LeadDetail.tsx` component  
3. Add a "Calculate AI Score" button to trigger scoring for individual leads
4. Consider implementing AI scoring for opportunities (future enhancement)

### 2. Opportunities Page Shows No Data
**Problem**: The opportunities pipeline page loads but shows no opportunities.

**Root Cause**: The `OpportunitySeeder` exists but may not have been run to populate data.

**Fix Plan**:
1. Verify if the seeder has been run by checking the database
2. Run the OpportunitySeeder if needed: `docker-compose exec backend php bin/seed.php --class=OpportunitySeeder`
3. Add error handling to show a proper empty state message

### 3. Forms API 405 Method Not Allowed Error
**Problem**: Frontend is calling `/api/forms` but the backend route is `/api/admin/forms`.

**Root Cause**: Incorrect API endpoint in `formBuilder.service.ts`.

**Fix Plan**:
1. Update `frontend/src/services/formBuilder.service.ts` to use `/admin/forms` instead of `/forms`
2. Update all form-related API calls to use the correct `/admin/` prefix

### 4. Activity Tracking Visitor Lead Relationship Error
**Problem**: `Call to undefined relationship [lead] on model [App\Models\ActivityTrackingVisitor]`

**Root Cause**: The `ActivityTrackingVisitor` model is missing the `lead()` relationship method.

**Fix Plan**:
1. Add the `lead()` relationship method to `ActivityTrackingVisitor` model
2. Add the `contact()` relationship method as well (also being accessed)

### 5. Knowledge Base Article Creation Redirect Issue
**Problem**: After creating an article via AI, the user is redirected to the homepage instead of back to the KB admin area.

**Root Cause**: `ArticleEditor` navigates to `/kb` instead of `/app/kb` after saving.

**Fix Plan**:
1. Update navigation in `ArticleEditor.tsx` from `navigate('/kb')` to `navigate('/app/kb')`
2. Update the back button navigation as well

## Implementation Order

### Priority 1 - Quick Fixes (5 minutes each)
1. **Fix Forms API endpoint** 
   - File: `frontend/src/services/formBuilder.service.ts`
   - Change: `/forms` → `/admin/forms`

2. **Fix KB redirect issue**
   - File: `frontend/src/pages/kb/ArticleEditor.tsx`
   - Change: `navigate('/kb')` → `navigate('/app/kb')` (2 locations)

3. **Fix Activity Tracking relationship**
   - File: `backend/app/Models/ActivityTrackingVisitor.php`
   - Add: `lead()` and `contact()` relationship methods

### Priority 2 - Data Issues (10 minutes)
4. **Run Opportunity Seeder**
   - Command: `docker-compose exec backend php bin/seed.php --class=OpportunitySeeder`
   - Verify data exists after seeding

### Priority 3 - Feature Integration (30 minutes)
5. **Integrate AI Scoring Display**
   - Add `AIScoreDisplay` component to leads list
   - Add scoring button to trigger AI scoring
   - Add score display to lead detail page

## Code Changes

### 1. Fix Forms API Endpoint
```typescript
// frontend/src/services/formBuilder.service.ts
// Line 23: Change from
const response = await apiClient.customGet('/forms', { params });
// To:
const response = await apiClient.customGet('/admin/forms', { params });
```

### 2. Fix KB Redirect
```typescript
// frontend/src/pages/kb/ArticleEditor.tsx
// Line 126: Change from
navigate('/kb');
// To:
navigate('/app/kb');

// Line 177: Change from
<Button variant="ghost" size="icon" onClick={() => navigate('/kb')}>
// To:
<Button variant="ghost" size="icon" onClick={() => navigate('/app/kb')}>
```

### 3. Fix Activity Tracking Relationships
```php
// backend/app/Models/ActivityTrackingVisitor.php
// Add after line 68:
public function lead()
{
    return $this->belongsTo(Lead::class, 'lead_id');
}

public function contact()
{
    return $this->belongsTo(Contact::class, 'contact_id');
}
```

### 4. Add AI Score Display to Leads List
```tsx
// frontend/src/pages/LeadsList.tsx
// Add import:
import { AIScoreDisplay } from '@/components/features/ai-scoring/AIScoreDisplay';

// Add column to table:
{
  accessorKey: 'ai_score',
  header: 'AI Score',
  cell: ({ row }) => {
    const lead = row.original;
    return lead.latest_score ? (
      <Badge variant={getScoreBadgeVariant(lead.latest_score)}>
        {lead.latest_score}
      </Badge>
    ) : (
      <Button 
        size="sm" 
        variant="ghost"
        onClick={() => handleCalculateScore(lead.id)}
      >
        Calculate
      </Button>
    );
  }
}
```

## Testing Plan

1. **Forms Page**: Navigate to `/app/forms` and verify it loads without 405 error
2. **Opportunities Page**: Navigate to `/app/opportunities` and verify opportunities are displayed
3. **Activity Tracking**: Navigate to `/app/tracking` and verify no console errors
4. **KB Article Creation**: 
   - Go to `/app/kb`
   - Click "New Article" 
   - Use AI to generate content
   - Save the article
   - Verify redirect stays in `/app/kb` showing the new article in the list
5. **AI Scoring**: Check if AI scores appear in leads list (if implemented)

## Success Criteria

- [ ] Forms page loads without errors
- [ ] Opportunities page shows seeded data
- [ ] Activity tracking page loads without relationship errors
- [ ] KB article creation redirects to admin KB page, not homepage
- [ ] AI scores are visible in the leads interface (if implemented)

## Notes

- The AI scoring integration for leads is the most complex fix and could be deferred if needed
- Consider adding AI scoring for opportunities and contacts as a future enhancement
- The FormBuilderController exists but may need additional implementation for full functionality 