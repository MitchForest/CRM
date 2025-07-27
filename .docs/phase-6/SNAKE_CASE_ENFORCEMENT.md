# SNAKE_CASE ENFORCEMENT - NO FUCKING EXCEPTIONS

## DATABASE IS THE SINGLE SOURCE OF TRUTH

### RULES - NO EXCEPTIONS:
1. **ALWAYS return EXACT database field names**
2. **NEVER transform field names**
3. **NO camelCase anywhere in the backend**
4. **NO Laravel conventions - we're using Slim**
5. **If the database has `email1`, return `email1` NOT `email`**
6. **If the database has `phone_work`, return `phone_work` NOT `phone`**

### EXACT Database Fields (from models):

#### Leads Table
```
first_name          NOT firstName
last_name           NOT lastName
email1              NOT email
phone_work          NOT phone or phoneWork
phone_mobile        NOT mobile
assigned_user_id    NOT assignedUserId
date_entered        NOT dateEntered
date_modified       NOT dateModified
lead_source         NOT leadSource
account_name        NOT accountName or company
```

#### Common Fields (all tables)
```
id                  NOT ID
created_by          NOT createdBy
modified_user_id    NOT modifiedUserId
assigned_user_id    NOT assignedUserId
parent_type         NOT parentType
parent_id           NOT parentId
date_entered        NOT dateEntered
date_modified       NOT dateModified
deleted             NOT isDeleted
```

#### Users Table
```
first_name          NOT firstName
last_name           NOT lastName
email1              NOT email
phone_work          NOT phoneWork
user_name           NOT username or userName
```

### HOW TO ENSURE COMPLIANCE:

1. **NEVER use accessors that change field names**
   ```php
   // ❌ WRONG - This creates a field that doesn't exist in DB
   public function getFullNameAttribute() {
       return $this->first_name . ' ' . $this->last_name;
   }
   
   // ✅ CORRECT - Return exact DB fields
   return [
       'first_name' => $lead->first_name,
       'last_name' => $lead->last_name
   ];
   ```

2. **Return EXACT database fields**
   ```php
   // ❌ WRONG
   return [
       'email' => $lead->email1,
       'phone' => $lead->phone_work,
       'assignedUserId' => $lead->assigned_user_id
   ];
   
   // ✅ CORRECT - EXACT database names
   return [
       'email1' => $lead->email1,
       'phone_work' => $lead->phone_work,
       'assigned_user_id' => $lead->assigned_user_id
   ];
   ```

3. **Check the model's $fillable array**
   - This shows EXACTLY what fields exist in the database
   - Return these EXACT names, no transformation

4. **Use database column listing**
   ```php
   // See actual database columns
   $columns = DB::select("SHOW COLUMNS FROM leads");
   ```

### CONTROLLERS THAT NEED FIXING:

1. **AuthController**
   - Returns `firstName` → MUST return `first_name`
   - Returns `lastName` → MUST return `last_name`
   - Returns `phoneWork` → MUST return `phone_work`

2. **OpportunitiesController**
   - Returns `leadSource` → MUST return `lead_source`
   - Returns `accountName` → MUST return `account_name`
   - Returns `assignedUserId` → MUST return `assigned_user_id`
   - Returns `dateEntered` → MUST return `date_entered`
   - Returns `dateModified` → MUST return `date_modified`

3. **CasesController**
   - Returns `assignedUserId` → MUST return `assigned_user_id`
   - Returns `dateEntered` → MUST return `date_entered`
   - Returns `dateModified` → MUST return `date_modified`

4. **ActivitiesController**
   - Returns `assignedUserId` → MUST return `assigned_user_id`
   - Returns `assignedUserName` → MUST return `assigned_user_name` or remove
   - Returns `parentType` → MUST return `parent_type`
   - Returns `parentId` → MUST return `parent_id`
   - Returns `dateEntered` → MUST return `date_entered`
   - Returns `dateModified` → MUST return `date_modified`

5. **DashboardController**
   - Returns `leadSource` → MUST return `lead_source`
   - Returns `dateEntered` → MUST return `date_entered`

6. **ContactsController**
   - Returns `dateEntered` → MUST return `date_entered`

### VALIDATION:
Run this SQL to see EXACT field names:
```sql
SHOW COLUMNS FROM leads;
SHOW COLUMNS FROM contacts;
SHOW COLUMNS FROM opportunities;
SHOW COLUMNS FROM cases;
SHOW COLUMNS FROM users;
```

### NO MORE MISTAKES:
1. Open the Model file
2. Look at $fillable array
3. Return THOSE EXACT FIELD NAMES
4. No transformation, no accessors, no camelCase
5. Database is the ONLY source of truth