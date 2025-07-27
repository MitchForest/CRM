<?php

/**
 * EXACT Model Fillable Arrays from Database Schema
 * 
 * These are the EXACT field names as they exist in the database.
 * Copy these into your models to ensure perfect alignment.
 * 
 * CRITICAL: Do not rename or "improve" these field names.
 * The database schema is the single source of truth.
 */

// =====================================
// Model: Lead
// Table: leads
// =====================================

class Lead extends BaseModel
{
    protected $table = 'leads';
    
    protected $fillable = [
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'salutation',
        'first_name',
        'last_name',
        'title',
        'department',
        'phone_work',               // NOT 'phone'
        'phone_mobile',             // NOT 'phone_mobile'
        'email1',                   // NOT 'email'
        'primary_address_street',
        'primary_address_city',
        'primary_address_state',
        'primary_address_postalcode',
        'primary_address_country',
        'status',
        'status_description',
        'lead_source',
        'lead_source_description',
        'description',
        'account_name',             // NOT 'company'
        'website',
        'ai_score',
        'ai_score_date',
        'ai_insights',
        'ai_next_best_action',
        'converted',
        'converted_contact_id',
        'converted_account_id',
        'converted_opportunity_id',
    ];
}

// =====================================
// Model: Contact
// Table: contacts
// =====================================

class Contact extends BaseModel
{
    protected $table = 'contacts';
    
    protected $fillable = [
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'salutation',
        'first_name',
        'last_name',
        'title',
        'department',
        'phone_work',
        'phone_mobile',
        'email1',                   // SAME AS LEAD - CONSISTENCY
        'primary_address_street',
        'primary_address_city',
        'primary_address_state',
        'primary_address_postalcode',
        'primary_address_country',
        'description',
        'lead_source',
        'account_id',
        'lifetime_value',
        'engagement_score',
        'last_activity_date',
    ];
}

// =====================================
// Model: Account
// Table: accounts
// =====================================

class Account extends BaseModel
{
    protected $table = 'accounts';
    
    protected $fillable = [
        'name',                     // THIS IS THE COMPANY NAME
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'account_type',
        'industry',
        'annual_revenue',
        'phone_office',             // NOT 'phone'
        'phone_alternate',
        'website',
        'email1',
        'employees',
        'billing_address_street',   // NOTE: 'billing_' prefix
        'billing_address_city',
        'billing_address_state',
        'billing_address_postalcode',
        'billing_address_country',
        'description',
        'rating',
        'ownership',
        'health_score',
        'renewal_date',
        'contract_value',
    ];
}

// =====================================
// Model: Opportunity
// Table: opportunities
// =====================================

class Opportunity extends BaseModel
{
    protected $table = 'opportunities';
    
    protected $fillable = [
        'name',
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'opportunity_type',
        'account_id',
        'amount',
        'amount_usdollar',
        'date_closed',
        'next_step',
        'sales_stage',
        'probability',
        'description',
        'lead_source',
        'campaign_id',
    ];
}

// =====================================
// Model: Case
// Table: cases
// =====================================

class Case extends BaseModel
{
    protected $table = 'cases';
    
    protected $fillable = [
        'case_number',              // AUTO-INCREMENT
        'name',                     // SUBJECT/TITLE
        'created_by',
        'modified_user_id',
        'assigned_user_id',
        'type',
        'status',
        'priority',
        'resolution',
        'description',
        'account_id',
        'contact_id',
    ];
}

// =====================================
// Model: User
// Table: users
// =====================================

class User extends BaseModel
{
    protected $table = 'users';
    
    protected $fillable = [
        'user_name',                // LOGIN USERNAME
        'user_hash',                // PASSWORD HASH
        'first_name',
        'last_name',
        'email',                    // NOTE: Just 'email', NOT 'email1'
        'status',
        'is_admin',
        'default_team',
        'phone_work',
        'phone_mobile',
        'address_street',           // NOTE: No prefix for users
        'address_city',
        'address_state',
        'address_postalcode',
        'address_country',
    ];
}

// =====================================
// KEY DIFFERENCES TO NOTE
// =====================================

/**
 * 1. LEADS vs CONTACTS vs USERS Email:
 *    - Leads: email1
 *    - Contacts: email1
 *    - Users: email (no number)
 * 
 * 2. COMPANY NAME FIELDS:
 *    - Leads: account_name
 *    - Accounts: name
 *    - No 'company' field anywhere
 * 
 * 3. PHONE FIELDS:
 *    - Always specific: phone_work, phone_mobile
 *    - Never generic 'phone'
 *    - Accounts use phone_office (not phone_work)
 * 
 * 4. ADDRESS PREFIXES:
 *    - Leads/Contacts: primary_address_*
 *    - Accounts: billing_address_*
 *    - Users: address_* (no prefix)
 * 
 * 5. PARENT RELATIONSHIPS:
 *    - Use parent_type + parent_id
 *    - parent_type = module name ('Leads', 'Contacts', etc.)
 * 
 * 6. NEVER FILLABLE:
 *    - id (UUID, auto-generated)
 *    - date_entered (auto-set on create)
 *    - date_modified (auto-set on update)
 *    - deleted (handled by soft delete trait)
 */

// =====================================
// VALIDATION RULES BASED ON DB SCHEMA
// =====================================

/**
 * Lead Validation Rules:
 */
$leadRules = [
    'last_name' => 'required|string|max:100',
    'first_name' => 'sometimes|string|max:100',
    'email1' => 'sometimes|email|max:100',
    'phone_work' => 'sometimes|string|max:100',
    'phone_mobile' => 'sometimes|string|max:100',
    'account_name' => 'sometimes|string|max:255',
    'status' => 'sometimes|string|max:100',
    'lead_source' => 'sometimes|string|max:100',
    'assigned_user_id' => 'sometimes|string|size:36|exists:users,id',
    'description' => 'sometimes|string|max:65535',
    'website' => 'sometimes|url|max:255',
];

/**
 * Contact Validation Rules:
 */
$contactRules = [
    'last_name' => 'required|string|max:100',
    'first_name' => 'sometimes|string|max:100',
    'email1' => 'sometimes|email|max:100',
    'phone_work' => 'sometimes|string|max:100',
    'phone_mobile' => 'sometimes|string|max:100',
    'account_id' => 'sometimes|string|size:36|exists:accounts,id',
    'assigned_user_id' => 'sometimes|string|size:36|exists:users,id',
    'lifetime_value' => 'sometimes|numeric|min:0',
    'engagement_score' => 'sometimes|integer|min:0|max:100',
];

/**
 * Account Validation Rules:
 */
$accountRules = [
    'name' => 'required|string|max:150',
    'account_type' => 'sometimes|string|max:50',
    'industry' => 'sometimes|string|max:50',
    'phone_office' => 'sometimes|string|max:100',
    'email1' => 'sometimes|email|max:100',
    'website' => 'sometimes|url|max:255',
    'assigned_user_id' => 'sometimes|string|size:36|exists:users,id',
    'employees' => 'sometimes|string|max:10',
    'contract_value' => 'sometimes|numeric|min:0',
];