# Complete Database Schema - Sassy CRM

Generated from actual database schema. This is the single source of truth for all field names.

## Core Tables Overview

### leads
Primary table for potential customers before conversion.

| Field | Type | Nullable | Key | Default | Description |
|-------|------|----------|-----|---------|-------------|
| id | char(36) | NO | PRI | NULL | UUID primary key |
| date_entered | datetime | YES | | NULL | Record creation timestamp |
| date_modified | datetime | YES | | NULL | Last modification timestamp |
| created_by | char(36) | YES | | NULL | User ID who created |
| modified_user_id | char(36) | YES | | NULL | User ID who last modified |
| assigned_user_id | char(36) | YES | MUL | NULL | Assigned sales rep |
| deleted | tinyint(1) | YES | MUL | 0 | Soft delete flag |
| salutation | varchar(255) | YES | | NULL | Mr./Ms./Dr. etc |
| first_name | varchar(100) | YES | | NULL | First name |
| last_name | varchar(100) | YES | MUL | NULL | Last name |
| title | varchar(100) | YES | | NULL | Job title |
| department | varchar(100) | YES | | NULL | Department |
| phone_work | varchar(100) | YES | | NULL | Work phone |
| phone_mobile | varchar(100) | YES | | NULL | Mobile phone |
| email1 | varchar(100) | YES | MUL | NULL | Primary email |
| primary_address_street | varchar(150) | YES | | NULL | Street address |
| primary_address_city | varchar(100) | YES | | NULL | City |
| primary_address_state | varchar(100) | YES | | NULL | State/Province |
| primary_address_postalcode | varchar(20) | YES | | NULL | Postal/Zip code |
| primary_address_country | varchar(255) | YES | | NULL | Country |
| status | varchar(100) | YES | MUL | NULL | Lead status |
| status_description | text | YES | | NULL | Status details |
| lead_source | varchar(100) | YES | | NULL | Source of lead |
| lead_source_description | text | YES | | NULL | Source details |
| description | text | YES | | NULL | General notes |
| account_name | varchar(255) | YES | | NULL | Company name |
| website | varchar(255) | YES | | NULL | Company website |
| ai_score | int | YES | | 0 | AI-calculated score |
| ai_score_date | datetime | YES | | NULL | When scored |
| ai_insights | json | YES | | NULL | AI scoring factors |
| ai_next_best_action | varchar(255) | YES | | NULL | AI recommendation |
| converted | tinyint(1) | YES | | 0 | Conversion flag |
| converted_contact_id | char(36) | YES | | NULL | Created contact ID |
| converted_account_id | char(36) | YES | | NULL | Created account ID |
| converted_opportunity_id | char(36) | YES | | NULL | Created opportunity ID |

### contacts
Converted leads and customer contacts.

| Field | Type | Nullable | Key | Default | Description |
|-------|------|----------|-----|---------|-------------|
| id | char(36) | NO | PRI | NULL | UUID primary key |
| date_entered | datetime | YES | | NULL | Record creation timestamp |
| date_modified | datetime | YES | | NULL | Last modification timestamp |
| created_by | char(36) | YES | | NULL | User ID who created |
| modified_user_id | char(36) | YES | | NULL | User ID who last modified |
| assigned_user_id | char(36) | YES | MUL | NULL | Assigned rep |
| deleted | tinyint(1) | YES | MUL | 0 | Soft delete flag |
| salutation | varchar(255) | YES | | NULL | Mr./Ms./Dr. etc |
| first_name | varchar(100) | YES | | NULL | First name |
| last_name | varchar(100) | YES | MUL | NULL | Last name |
| title | varchar(100) | YES | | NULL | Job title |
| department | varchar(255) | YES | | NULL | Department |
| phone_work | varchar(100) | YES | | NULL | Work phone |
| phone_mobile | varchar(100) | YES | | NULL | Mobile phone |
| email1 | varchar(100) | YES | MUL | NULL | Primary email |
| primary_address_street | varchar(150) | YES | | NULL | Street address |
| primary_address_city | varchar(100) | YES | | NULL | City |
| primary_address_state | varchar(100) | YES | | NULL | State/Province |
| primary_address_postalcode | varchar(20) | YES | | NULL | Postal/Zip code |
| primary_address_country | varchar(255) | YES | | NULL | Country |
| description | text | YES | | NULL | General notes |
| lead_source | varchar(100) | YES | | NULL | Original source |
| account_id | char(36) | YES | MUL | NULL | Related account |
| lifetime_value | decimal(26,6) | YES | | NULL | Customer LTV |
| engagement_score | int | YES | | NULL | Engagement metric |
| last_activity_date | datetime | YES | | NULL | Last interaction |

### accounts
Company/organization records.

| Field | Type | Nullable | Key | Default | Description |
|-------|------|----------|-----|---------|-------------|
| id | char(36) | NO | PRI | NULL | UUID primary key |
| name | varchar(150) | YES | MUL | NULL | Company name |
| date_entered | datetime | YES | | NULL | Record creation timestamp |
| date_modified | datetime | YES | | NULL | Last modification timestamp |
| created_by | char(36) | YES | | NULL | User ID who created |
| modified_user_id | char(36) | YES | | NULL | User ID who last modified |
| assigned_user_id | char(36) | YES | MUL | NULL | Account manager |
| deleted | tinyint(1) | YES | MUL | 0 | Soft delete flag |
| account_type | varchar(50) | YES | | NULL | Customer/Prospect/Partner |
| industry | varchar(50) | YES | | NULL | Industry vertical |
| annual_revenue | varchar(100) | YES | | NULL | Revenue range |
| phone_office | varchar(100) | YES | | NULL | Main phone |
| phone_alternate | varchar(100) | YES | | NULL | Alternative phone |
| website | varchar(255) | YES | | NULL | Company website |
| email1 | varchar(100) | YES | | NULL | Primary email |
| employees | varchar(10) | YES | | NULL | Employee count |
| billing_address_street | varchar(150) | YES | | NULL | Billing street |
| billing_address_city | varchar(100) | YES | | NULL | Billing city |
| billing_address_state | varchar(100) | YES | | NULL | Billing state |
| billing_address_postalcode | varchar(20) | YES | | NULL | Billing zip |
| billing_address_country | varchar(255) | YES | | NULL | Billing country |
| description | text | YES | | NULL | General notes |
| rating | varchar(100) | YES | | NULL | Account rating |
| ownership | varchar(100) | YES | | NULL | Public/Private |
| health_score | int | YES | | NULL | Customer health |
| renewal_date | date | YES | | NULL | Contract renewal |
| contract_value | decimal(26,6) | YES | | NULL | Annual contract value |

### opportunities
Sales opportunities/deals in pipeline.

| Field | Type | Nullable | Key | Default | Description |
|-------|------|----------|-----|---------|-------------|
| id | char(36) | NO | PRI | NULL | UUID primary key |
| name | varchar(50) | YES | MUL | NULL | Opportunity name |
| date_entered | datetime | YES | | NULL | Record creation timestamp |
| date_modified | datetime | YES | | NULL | Last modification timestamp |
| created_by | char(36) | YES | | NULL | User ID who created |
| modified_user_id | char(36) | YES | | NULL | User ID who last modified |
| assigned_user_id | char(36) | YES | MUL | NULL | Sales rep |
| deleted | tinyint(1) | YES | MUL | 0 | Soft delete flag |
| opportunity_type | varchar(255) | YES | | NULL | New/Existing/Renewal |
| account_id | char(36) | YES | MUL | NULL | Related account |
| amount | decimal(26,6) | YES | | NULL | Deal value |
| amount_usdollar | decimal(26,6) | YES | | NULL | USD equivalent |
| date_closed | date | YES | MUL | NULL | Expected close date |
| next_step | varchar(100) | YES | | NULL | Next action |
| sales_stage | varchar(255) | YES | MUL | NULL | Pipeline stage |
| probability | int | YES | | NULL | Win probability % |
| description | text | YES | | NULL | Deal details |
| lead_source | varchar(50) | YES | | NULL | Original source |
| campaign_id | char(36) | YES | | NULL | Marketing campaign |

### cases
Support tickets and customer issues.

| Field | Type | Nullable | Key | Default | Description |
|-------|------|----------|-----|---------|-------------|
| id | char(36) | NO | PRI | NULL | UUID primary key |
| case_number | int | NO | UNI | NULL | Auto-increment case # |
| name | varchar(255) | YES | MUL | NULL | Subject/Title |
| date_entered | datetime | YES | | NULL | Record creation timestamp |
| date_modified | datetime | YES | | NULL | Last modification timestamp |
| created_by | char(36) | YES | | NULL | User ID who created |
| modified_user_id | char(36) | YES | | NULL | User ID who last modified |
| assigned_user_id | char(36) | YES | MUL | NULL | Support agent |
| deleted | tinyint(1) | YES | MUL | 0 | Soft delete flag |
| type | varchar(255) | YES | | NULL | Issue type |
| status | varchar(100) | YES | MUL | NULL | Open/Closed/Pending |
| priority | varchar(100) | YES | | NULL | P1/P2/P3 |
| resolution | text | YES | | NULL | How resolved |
| description | text | YES | | NULL | Issue details |
| account_id | char(36) | YES | MUL | NULL | Customer account |
| contact_id | char(36) | YES | | NULL | Primary contact |

### users
System users (employees).

| Field | Type | Nullable | Key | Default | Description |
|-------|------|----------|-----|---------|-------------|
| id | char(36) | NO | PRI | NULL | UUID primary key |
| user_name | varchar(60) | YES | UNI | NULL | Login username |
| first_name | varchar(255) | YES | | NULL | First name |
| last_name | varchar(255) | YES | | NULL | Last name |
| email | varchar(100) | YES | | NULL | Email address |
| status | varchar(100) | YES | | NULL | Active/Inactive |
| is_admin | tinyint(1) | YES | | 0 | Admin flag |
| deleted | tinyint(1) | YES | | 0 | Soft delete flag |

## Custom Tables

### activity_tracking_sessions
Website visitor sessions.

| Field | Type | Nullable | Key | Default | Description |
|-------|------|----------|-----|---------|-------------|
| id | char(36) | NO | PRI | NULL | UUID primary key |
| visitor_id | char(36) | YES | | NULL | Visitor identifier |
| session_id | varchar(255) | YES | | NULL | Session identifier |
| lead_id | char(36) | YES | | NULL | Associated lead |
| contact_id | char(36) | YES | | NULL | Associated contact |
| ip_address | varchar(45) | YES | | NULL | Visitor IP |
| user_agent | text | YES | | NULL | Browser info |
| referrer | text | YES | | NULL | Referral source |
| landing_page | text | YES | | NULL | Entry page |
| exit_page | text | YES | | NULL | Exit page |
| page_count | int | YES | | 0 | Pages viewed |
| duration | int | YES | | 0 | Session length (seconds) |
| created_at | timestamp | YES | | CURRENT_TIMESTAMP | Session start |
| updated_at | timestamp | YES | | CURRENT_TIMESTAMP | Last activity |

### ai_conversations
AI chatbot conversations.

| Field | Type | Nullable | Key | Default | Description |
|-------|------|----------|-----|---------|-------------|
| id | char(36) | NO | PRI | NULL | UUID primary key |
| lead_id | char(36) | YES | | NULL | Associated lead |
| contact_id | char(36) | YES | | NULL | Associated contact |
| visitor_id | char(36) | YES | | NULL | Anonymous visitor |
| status | varchar(50) | YES | | active | active/resolved/abandoned |
| created_at | timestamp | YES | | CURRENT_TIMESTAMP | Start time |
| updated_at | timestamp | YES | | CURRENT_TIMESTAMP | Last message |

### form_submissions
Captured form data.

| Field | Type | Nullable | Key | Default | Description |
|-------|------|----------|-----|---------|-------------|
| id | char(36) | NO | PRI | NULL | UUID primary key |
| form_id | char(36) | YES | | NULL | Form definition |
| lead_id | char(36) | YES | | NULL | Created/linked lead |
| contact_id | char(36) | YES | | NULL | Existing contact |
| form_data | json | YES | | NULL | Submitted data |
| source_url | text | YES | | NULL | Page submitted from |
| ip_address | varchar(45) | YES | | NULL | Submitter IP |
| created_at | timestamp | YES | | CURRENT_TIMESTAMP | Submission time |

## Field Naming Conventions

1. **Emails**: Always numbered (email1, email2, etc.)
2. **Phones**: Always specific (phone_work, phone_mobile, phone_home)
3. **Addresses**: Prefixed with type (primary_address_*, billing_address_*)
4. **Timestamps**: date_entered, date_modified (not created_at, updated_at)
5. **Foreign Keys**: *_id suffix (account_id, contact_id)
6. **Flags**: Boolean fields are tinyint(1) (deleted, converted)

## Important Notes

1. All ID fields are CHAR(36) for UUID storage
2. Soft deletes use `deleted` field (0=active, 1=deleted)
3. Text fields have 65535 character limit
4. Varchar fields have specific limits - respect them in validation
5. Some tables use auto-increment (case_number) in addition to UUID

This schema is the definitive guide for all development.