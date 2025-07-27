# Frontend Migration Tracker - Phase 6

## Current Status: ~75% Complete ⚠️

### Backend Schema API Status
✅ **FULLY FIXED!** The backend engineer has fixed the OpportunitieDB → OpportunityDB bug.
- `GET /api/schema/typescript` - ✅ Generates correct snake_case types with proper interface names
- All interfaces now correctly named: LeadDB, ContactDB, OpportunityDB, CaseDB, etc.

### ✅ Major Architectural Decision: Snake_case Everything 🐍
We've successfully implemented a clean, simple architecture:
1. **Snake_case everywhere** - Direct database field names, no transformation
2. **Single source of truth** - `/frontend/src/types/database.types.ts` generated from backend schema
3. **No transformation layer** - All mappers and transformers deleted
4. **Simplified architecture** - Database → API → Frontend (all snake_case)

### ✅ Cleanup Completed (2025-07-27)

#### What Was Deleted
- All confusing type files (api.generated.ts, frontend.types.ts, phase*.types.ts, etc.)
- All transformation/mapper files
- Unused validation schemas
- Unused type-safe API attempts

#### What We Keep
- ✅ **database.types.ts** - THE ONLY TYPE FILE (regenerated with fix)
- ✅ **validation.ts** - Manual validation schemas

## 🎯 Remaining Tasks - Clear Path to Completion

### Task 1: Update All Type Imports (45 min)
```bash
# Files that need import updates:
- src/components/dashboard/EntityActivities.tsx
- src/pages/LeadsList.tsx  
- src/pages/Contacts.tsx
- src/pages/contacts/ContactUnifiedView.tsx
- src/components/features/opportunities/*.tsx
- src/hooks/use-*.ts
- src/lib/api-client.ts
```

**Changes needed:**
```typescript
// OLD
import type { Lead, Contact, Opportunity } from '@/types/api.generated'
import type { LeadDB, ContactDB } from '@/types/database.types'

// NEW
import type { LeadDB, ContactDB, OpportunityDB } from '@/types/database.types'
```

### Task 2: Fix All TypeScript Type References (30 min)
```typescript
// OLD
const columns: ColumnDef<Lead>[] = [...]
const lead: Lead = {...}

// NEW  
const columns: ColumnDef<LeadDB>[] = [...]
const lead: LeadDB = {...}
```

### Task 3: Update Field Names to Snake_case (2 hours)
This is the biggest task. Every component needs field updates:

```typescript
// OLD (camelCase)
contact.firstName
lead.phoneWork
opportunity.dateEntered
case.assignedUserId

// NEW (snake_case)
contact.first_name
lead.phone_work
opportunity.date_entered
case.assigned_user_id
```

**Priority Components:**
1. Dashboard.tsx - Not migrated yet
2. EntityActivities.tsx - Partially done
3. LeadsList.tsx - Needs field updates
4. ContactUnifiedView.tsx - Complex, needs careful updates
5. All form components - Field names in forms

### Task 4: Fix Validation Schemas (30 min)
Update `validation.ts` to ensure all schemas use snake_case:
- Lead form validation
- Contact form validation  
- Opportunity form validation
- Any other forms

### Task 5: Final Testing (1 hour)
1. **TypeScript Check**
   ```bash
   npm run typecheck
   # Should have 0 errors
   ```

2. **ESLint Check**
   ```bash
   npm run lint
   # Fix any warnings
   ```

3. **Integration Testing**
   - Create a new lead
   - View lead details
   - Edit lead
   - Create opportunity
   - Drag opportunity in pipeline
   - View contact timeline
   - Create support case

## 📋 Quick Reference: Common Field Mappings

| Old (camelCase) | New (snake_case) |
|-----------------|------------------|
| firstName | first_name |
| lastName | last_name |
| phoneWork | phone_work |
| phoneMobile | phone_mobile |
| dateEntered | date_entered |
| dateModified | date_modified |
| assignedUserId | assigned_user_id |
| accountName | account_name |
| leadSource | lead_source |
| salesStage | sales_stage |
| parentId | parent_id |
| parentType | parent_type |

## 🏁 Definition of Done

- [ ] All imports use `@/types/database.types`
- [ ] All type references use `*DB` types (LeadDB, ContactDB, etc.)
- [ ] All field references use snake_case
- [ ] TypeScript compilation passes (0 errors)
- [ ] ESLint passes (0 errors)
- [ ] All CRUD operations work
- [ ] No console errors
- [ ] Integration tests pass

## Time Estimate

- **Total**: 4-5 hours
- **Breakdown**:
  - Type imports: 45 min
  - Type references: 30 min
  - Field name updates: 2 hours
  - Validation fixes: 30 min
  - Testing: 1 hour
  - Buffer for issues: 30 min

## Next Steps

1. Start with type imports - mechanical find/replace
2. Fix type references - also mechanical
3. Systematically update field names component by component
4. Test as you go
5. Final comprehensive testing

The architecture is now clean and simple. Just need to update the code to match!