# Database Seeding Tracker - Phase 7

## Overview
This document tracks the database seeding implementation for the CRM project. The goal is to populate the database with realistic mock data for all features visible in the app (dashboard, leads, opportunities, customers, support tickets, knowledge base, AI scoring, sessions, forms).

## Current Status - ALL SEEDERS COMPLETE! ✅

### ✅ Completed Seeders
1. **UserSeeder** - Successfully creates 10 users
2. **KnowledgeBaseSeeder** - Successfully creates 12 articles
3. **LeadSeeder** - Successfully creates 500+ leads
4. **ContactSeeder** - Successfully creates 166 accounts and 478 contacts
5. **OpportunitySeeder** - Successfully creates 200 opportunities
6. **FormSeeder** - Successfully creates 10 forms and 475 submissions
7. **ActivitySeeder** - Successfully creates 852 calls, 711 meetings, 1092 notes, 535 tasks
8. **ActivityTrackingSeeder** - Successfully creates 956 visitors, 4537 sessions, 21193 page views
9. **CaseSeeder** - Successfully creates 150 support cases
10. **AISeeder** - Successfully creates 4079 lead scores, 205 chat conversations, 1998 messages

### 🎉 Final Database Status
- **Total Records Created**: 38,199
- **All expected data successfully seeded**
- **Database is ready for testing**

## Schema Mismatches Found & Fixed

### 1. knowledge_base_articles table
**Expected fields (from seeder):**
- `excerpt` → Actually `summary` in DB
- `created_by` → Actually `author_id` in DB

**Missing fields added to seeder:**
- `date_published`
- `is_featured`
- `tags` (JSON)

### 2. form_builder_forms table
**Expected fields (from seeder):**
- `slug` → Doesn't exist, moved to settings JSON
- `type` → Doesn't exist, moved to settings JSON
- `submit_button_text` → Doesn't exist, moved to settings JSON
- `success_message` → Doesn't exist, moved to settings JSON

**Solution:** All these fields are now stored in the `settings` JSON column

### 3. leads table
**Non-existent fields removed:**
- `converted_date` → Doesn't exist in schema
- `converted` → Doesn't exist in schema  
- `converted_contact_id` → Doesn't exist in schema
- `converted_account_id` → Doesn't exist in schema
- `converted_opportunity_id` → Doesn't exist in schema

**Solution:** System tracks conversion only through status='Converted'

### 4. opportunities table
**Field constraints:**
- `name` → Limited to 50 characters

**Solution:** Truncate opportunity names to fit limit

## Seeder Dependencies

```
UserSeeder (no dependencies)
    ↓
KnowledgeBaseSeeder (needs user IDs)
    ↓
LeadSeeder (needs user IDs for assignment)
    ↓
ContactSeeder (needs lead IDs for conversion)
    ↓
OpportunitySeeder (needs account IDs)
    ↓
FormSeeder (needs lead & contact IDs for submissions)
    ↓
ActivitySeeder (needs leads, contacts, opportunities)
    ↓
ActivityTrackingSeeder (needs leads, contacts)
    ↓
CaseSeeder (needs contacts, accounts)
    ↓
AISeeder (needs leads, contacts)
```

## How to Run Seeders Individually

### 1. Clean existing data first
```bash
docker-compose exec backend php bin/clean-seed-data.php
```

### 2. Run seeders one by one in order
```bash
# Users first (required by all others)
docker-compose exec backend php bin/seed.php --class=UserSeeder

# Knowledge base (independent)
docker-compose exec backend php bin/seed.php --class=KnowledgeBaseSeeder

# Leads (requires users)
docker-compose exec backend php bin/seed.php --class=LeadSeeder

# Contacts/Accounts (requires leads for conversion)
docker-compose exec backend php bin/seed.php --class=ContactSeeder

# Opportunities (requires accounts)
docker-compose exec backend php bin/seed.php --class=OpportunitySeeder

# Forms (requires leads & contacts for submissions)
docker-compose exec backend php bin/seed.php --class=FormSeeder

# Activities (requires leads, contacts, opportunities)
docker-compose exec backend php bin/seed.php --class=ActivitySeeder

# Activity Tracking (requires leads, contacts)
docker-compose exec backend php bin/seed.php --class=ActivityTrackingSeeder

# Support Cases (requires contacts, accounts)
docker-compose exec backend php bin/seed.php --class=CaseSeeder

# AI Data (requires leads, contacts)
docker-compose exec backend php bin/seed.php --class=AISeeder
```

## Files Created

### Seeder Files
- `/backend/database/seeders/BaseSeeder.php` - Base class with utilities
- `/backend/database/seeders/UserSeeder.php` - Creates users with roles
- `/backend/database/seeders/KnowledgeBaseSeeder.php` - Creates KB articles
- `/backend/database/seeders/FormSeeder.php` - Creates forms and submissions
- `/backend/database/seeders/LeadSeeder.php` - Creates 500 leads
- `/backend/database/seeders/ContactSeeder.php` - Creates accounts/contacts
- `/backend/database/seeders/OpportunitySeeder.php` - Creates opportunities
- `/backend/database/seeders/ActivitySeeder.php` - Creates activities
- `/backend/database/seeders/ActivityTrackingSeeder.php` - Creates sessions
- `/backend/database/seeders/CaseSeeder.php` - Creates support tickets
- `/backend/database/seeders/AISeeder.php` - Creates AI scores & chats
- `/backend/database/seeders/DatabaseSeeder.php` - Master seeder

### Utility Scripts
- `/backend/bin/seed.php` - Command to run seeders
- `/backend/bin/clean-seed-data.php` - Cleans all seed data

## Known Issues

### 1. Missing Faker Library
**Fixed:** Installed with `composer require fakerphp/faker --dev`

### 2. Bootstrap Path
**Fixed:** Changed from `/bootstrap.php` to `/bootstrap/app.php`

### 3. Seeder Dependencies
**Issue:** Seeders create JSON files to share IDs between runs
**Location:** `/backend/database/seeders/*.json` (temporary files)
**Note:** These files are cleaned up after successful run

### 4. BaseSeeder Date Range Issue
**Fixed:** Added logic to handle when start date > end date in randomDate()

### 5. Lead Model Missing Fillable Fields
**Fixed:** Model was missing conversion fields that don't exist in DB

### 6. sprintf Template Formatting
**Fixed:** LeadSeeder had templates with multiple placeholders causing crashes

### 7. FormSeeder Schema Issues
**Fixed:** Changed `form_data` to `data`, removed `date_modified`

### 8. ActivitySeeder Schema Issues
**Fixed:** Changed duration fields, removed `type` from meetings, fixed call/meeting duration format

### 9. ActivityTrackingSeeder Schema Issues
**Fixed:** Changed `total_visits` to `visit_count`, fixed timestamp fields, removed referrer from sessions

### 10. CaseSeeder Schema Issues
**Fixed:** Removed `resolved_date`, removed `case_number` (auto-increment)

### 11. AISeeder Schema Issues
**Fixed:** Removed `created_at`/`updated_at`, changed to proper timestamp fields, removed `rating`

## Test Credentials

After successful seeding, these users are available:

| Role | Email | Password | Name |
|------|-------|----------|------|
| Admin | john.smith@techflow.com | password123 | John Smith |
| SDR Lead | sarah.chen@techflow.com | password123 | Sarah Chen |
| Junior SDR | mike.johnson@techflow.com | password123 | Mike Johnson |
| Senior SDR | emily.rodriguez@techflow.com | password123 | Emily Rodriguez |
| Enterprise AE | david.park@techflow.com | password123 | David Park |
| Mid-Market AE | jessica.williams@techflow.com | password123 | Jessica Williams |
| Senior CSM | alex.thompson@techflow.com | password123 | Alex Thompson |
| CSM | maria.garcia@techflow.com | password123 | Maria Garcia |
| Support Lead | kevin.liu@techflow.com | password123 | Kevin Liu |
| Support Engineer | rachel.brown@techflow.com | password123 | Rachel Brown |

## Data Created by Each Seeder

### UserSeeder
- 10 users across different roles
- Admin, SDRs, AEs, CSMs, Support

### KnowledgeBaseSeeder
- 12 help articles
- Categories: Getting Started, Features, Integrations, etc.
- Each with view counts and helpful/not helpful counts

### LeadSeeder (Not tested yet)
- 500 leads
- Various statuses: New, Contacted, Qualified, Unqualified, Converted
- Different lead sources
- Realistic company names and job titles

### ContactSeeder (Not tested yet)
- 125 accounts (converted from leads)
- ~375 contacts (multiple per account)
- Account types: Trial, Active, Churned
- MRR calculations based on company size

### OpportunitySeeder (Not tested yet)
- 200 opportunities
- Full pipeline stages
- Realistic deal sizes
- Linked to accounts

### FormSeeder (Schema fixed, not tested)
- 5 forms (demo request, contact, support, newsletter, trial)
- 475+ form submissions
- Linked to leads and contacts

### ActivitySeeder (Not tested yet)
- Calls, meetings, notes, tasks
- For leads, opportunities, and contacts
- Realistic activity patterns

### ActivityTrackingSeeder (Not tested yet)
- Website visitor sessions
- Page views with realistic navigation
- Sessions for leads before conversion
- App usage for customers

### CaseSeeder (Not tested yet)
- 150 support tickets
- Various priorities and types
- Resolution notes
- Linked to contacts

### AISeeder (Not tested yet)
- Lead scoring history
- Chat conversations
- AI insights and next best actions

## Next Steps for Handoff

1. **Test remaining seeders one by one**
   - Start with LeadSeeder
   - Check for any schema mismatches
   - Document any issues found

2. **Verify data relationships**
   - Ensure leads convert to contacts properly
   - Check that activities link correctly
   - Verify AI scores attach to leads

3. **Performance considerations**
   - LeadSeeder creates 500 records
   - ActivitySeeder creates thousands of records
   - May need to add progress indicators

4. **Frontend verification**
   - After seeding, check that data appears correctly in:
     - Dashboard stats
     - Leads list
     - Opportunities pipeline
     - Customer list
     - Support tickets
     - Knowledge base

## Troubleshooting

### If a seeder fails:
1. Check the error message for schema mismatches
2. Compare seeder fields with actual database table
3. Update seeder to match database schema
4. Clean data and retry

### To check table structure:
```bash
docker exec sassycrm-mysql mysql -u root -proot -e "USE suitecrm; DESCRIBE table_name;"
```

### To see what was created:
```bash
docker exec sassycrm-mysql mysql -u root -proot -e "USE suitecrm; SELECT COUNT(*) FROM table_name;"
```

## Important Notes

1. **Schema First**: Always verify database schema before running seeders
2. **Order Matters**: Run seeders in dependency order
3. **Clean Between Runs**: Use clean script to avoid duplicates
4. **Check Logs**: Backend logs may have additional error details
5. **Faker Data**: All data is randomly generated and not real

## Contact

This seeding system was implemented as part of Phase 7 of the CRM backend migration.
For questions about the implementation, refer to:
- `/backend/database/seeders/` - All seeder implementations
- `/.docs/phase-6/backend-todo-new.md` - Backend migration status
- `/.docs/phase-6/TESTING_STRATEGY.md` - Original testing plan