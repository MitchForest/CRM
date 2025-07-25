# SuiteCRM Customizations for Phase 2

This directory contains all SuiteCRM-specific customizations that need to be deployed to the SuiteCRM instance.

## Directory Structure

```
suitecrm-custom/
├── Extension/           # Module extensions (fields, vardefs, language)
│   ├── modules/
│   │   ├── Opportunities/
│   │   ├── Cases/
│   │   ├── Calls/
│   │   ├── Meetings/
│   │   └── Tasks/
│   └── application/
├── modules/            # Module-specific logic hooks and classes
│   ├── Opportunities/
│   └── Cases/
└── install/            # Installation scripts
    ├── create_roles.php
    └── seed_phase2_data.php
```

## Deployment Instructions

1. Copy the contents of this directory to your SuiteCRM's `custom/` directory
2. Run Quick Repair and Rebuild in Admin panel
3. Execute role creation script: `php custom/install/create_roles.php`
4. (Optional) Seed demo data: `php custom/install/seed_phase2_data.php`

## Custom Fields Added

### Opportunities
- competitors (text)
- decision_criteria (text)
- champion_contact_id (id)
- subscription_type (enum)

### Cases
- severity (enum)
- product_version (varchar)
- environment (enum)
- sla_deadline (datetime)
- kb_article_id (id)

### Calls
- call_type (enum)
- call_outcome (enum)
- next_steps (text)

### Meetings
- meeting_type (enum)  
- demo_environment (varchar)
- attendee_count (int)

### Tasks
- task_type (enum)
- related_opportunity_id (id)