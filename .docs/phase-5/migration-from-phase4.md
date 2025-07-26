# Migration Guide: Phase 4 to Phase 5

## Overview
This guide helps migrate existing Phase 4 installations to the simplified Phase 5 architecture.

## ⚠️ Important Notes
- **No automatic data migration** - Phase 5 uses a simplified data model
- **Manual data export/import required** for production systems
- **Test thoroughly** in staging before production migration

## Pre-Migration Checklist

- [ ] Full database backup completed
- [ ] All custom code documented
- [ ] User list exported
- [ ] Active integrations identified
- [ ] Downtime window scheduled

## Migration Steps

### 1. Backup Phase 4 Data

```bash
# Export critical data
mysqldump -u root -p suitecrm_phase4 \
  leads contacts accounts opportunities cases \
  > phase4_backup.sql

# Export configurations
cp -r backend/custom /backup/phase4_custom
cp backend/.env /backup/phase4.env
```

### 2. Deploy Phase 5 Code

```bash
# Pull Phase 5 branch
git fetch origin
git checkout phase5-simplified

# Install dependencies
cd backend && composer install
cd ../frontend && npm install
```

### 3. Environment Updates

**Update backend/.env:**
```env
# Add new Phase 5 variables
JWT_ACCESS_TOKEN_TTL=900
JWT_REFRESH_TOKEN_TTL=2592000

# Remove deprecated variables
# Remove any MODULE_* settings
# Remove complex workflow configs
```

### 4. Database Schema Updates

```sql
-- Simplify lead statuses
UPDATE leads 
SET status = CASE 
  WHEN status IN ('Dead', 'Recycled') THEN 'New'
  WHEN status IN ('Working', 'Assigned') THEN 'Contacted'
  WHEN status = 'Converted' THEN 'Qualified'
  ELSE status
END;

-- Simplify opportunity stages
UPDATE opportunities
SET sales_stage = CASE
  WHEN sales_stage IN ('Prospecting', 'Qualification') THEN 'Qualified'
  WHEN sales_stage IN ('Needs Analysis', 'Value Proposition') THEN 'Proposal'
  WHEN sales_stage IN ('Id. Decision Makers', 'Perception Analysis') THEN 'Negotiation'
  WHEN sales_stage = 'Closed Won' THEN 'Won'
  WHEN sales_stage = 'Closed Lost' THEN 'Lost'
  ELSE sales_stage
END;

-- Update case types (now support_tickets)
UPDATE cases
SET type = CASE
  WHEN type NOT IN ('Technical', 'Billing', 'Feature', 'Other') THEN 'Other'
  ELSE type
END;

-- Simplify case status
UPDATE cases
SET status = CASE
  WHEN status IN ('New', 'Assigned') THEN 'Open'
  WHEN status IN ('Pending Input') THEN 'In Progress'
  WHEN status IN ('Rejected', 'Duplicate') THEN 'Closed'
  ELSE status
END;
```

### 5. Data Cleanup

```sql
-- Remove unused modules data
DELETE FROM calls WHERE deleted = 0;
DELETE FROM meetings WHERE deleted = 0;
DELETE FROM tasks WHERE deleted = 0;
DELETE FROM notes WHERE deleted = 0;
DELETE FROM documents WHERE deleted = 0;
DELETE FROM contracts WHERE deleted = 0;
DELETE FROM quotes WHERE deleted = 0;
DELETE FROM products WHERE deleted = 0;
DELETE FROM campaigns WHERE deleted = 0;

-- Clean orphaned records
DELETE FROM email_addresses 
WHERE id NOT IN (
  SELECT DISTINCT email_address_id 
  FROM email_addr_bean_rel 
  WHERE deleted = 0
);
```

### 6. User Migration

```bash
# Export users from Phase 4
mysql -u root -p suitecrm_phase4 -e \
  "SELECT user_name, first_name, last_name, email, status 
   FROM users WHERE deleted = 0" > users_export.csv

# Import to Phase 5 (manually or via script)
# Default password: admin123 (force reset on first login)
```

### 7. Update Frontend Configuration

```bash
# Update API endpoints in frontend/.env
VITE_API_URL=http://localhost:8080/custom/api

# Remove deprecated feature flags
# Remove any VITE_FEATURE_* variables
```

### 8. Clear Caches

```bash
# Clear all caches
rm -rf backend/cache/*
rm -rf frontend/node_modules/.cache
redis-cli FLUSHALL  # If using Redis
```

### 9. Test Core Functions

Run through the testing guide to ensure:
- [ ] Users can login with existing credentials
- [ ] Leads display with simplified statuses
- [ ] Opportunities show correct stages
- [ ] Support tickets (cases) work properly
- [ ] Unified contact view displays all data
- [ ] Forms and tracking still function

### 10. Update Integrations

**Webhook URLs:**
- Update from `/api/v7/*` to `/api/v8/*`
- Update authentication headers to use JWT

**Embed Scripts:**
- Replace old tracking scripts with new versions
- Update form embed codes
- Deploy new chat widget

## Rollback Plan

If issues occur:

```bash
# Stop Phase 5
docker-compose down

# Restore Phase 4 code
git checkout phase4-stable

# Restore database
mysql -u root -p suitecrm < phase4_backup.sql

# Restore custom files
cp -r /backup/phase4_custom/* backend/custom/

# Restart services
docker-compose up -d
```

## Post-Migration Tasks

### Week 1
- [ ] Monitor error logs daily
- [ ] Check user adoption metrics
- [ ] Address any bug reports
- [ ] Verify all integrations working

### Week 2
- [ ] Review performance metrics
- [ ] Optimize slow queries
- [ ] User training sessions
- [ ] Document new workflows

### Month 1
- [ ] Full system audit
- [ ] Security review
- [ ] Performance tuning
- [ ] Plan next improvements

## Common Migration Issues

### 1. Login Failures
**Symptom**: Users can't login after migration
**Fix**: 
```bash
# Reset user passwords
UPDATE users SET user_hash = MD5('admin123') WHERE deleted = 0;
```

### 2. Missing Data
**Symptom**: Some records not visible
**Fix**:
```sql
-- Check for incorrect status mappings
SELECT DISTINCT status FROM leads;
SELECT DISTINCT sales_stage FROM opportunities;

-- Update any missed mappings
UPDATE leads SET status = 'New' WHERE status NOT IN ('New', 'Contacted', 'Qualified');
```

### 3. Broken Embeds
**Symptom**: Forms/tracking not working on external sites
**Fix**:
- Update embed URLs to new endpoints
- Check CORS configuration
- Regenerate embed codes

### 4. Performance Issues
**Symptom**: Slow page loads after migration
**Fix**:
```sql
-- Rebuild indexes
OPTIMIZE TABLE leads;
OPTIMIZE TABLE opportunities;
OPTIMIZE TABLE contacts;
OPTIMIZE TABLE cases;

-- Update statistics
ANALYZE TABLE leads;
ANALYZE TABLE opportunities;
```

## Migration Success Criteria

- [ ] All users can login successfully
- [ ] Historical data is accessible
- [ ] Core workflows function properly
- [ ] No critical errors in logs
- [ ] Performance meets or exceeds Phase 4
- [ ] Users trained on simplified interface

## Support During Migration

- **Technical Issues**: Create issue in GitHub
- **Data Questions**: Document specific examples
- **Emergency**: Use rollback procedure

---

**Remember**: Phase 5 is intentionally simplified. Some Phase 4 features were removed by design to improve maintainability and user experience.