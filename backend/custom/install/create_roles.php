<?php
/**
 * Phase 2 - Create B2B Roles
 * Run this script to create Sales Representative, Customer Success Manager, and Sales Manager roles
 */

// Bootstrap SuiteCRM
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('modules/ACLRoles/ACLRole.php');
require_once('modules/ACLActions/ACLAction.php');

function createB2BRoles() {
    global $db;
    
    $roles = [
        [
            'name' => 'Sales Representative',
            'description' => 'Can manage leads, accounts, opportunities, and activities',
            'permissions' => [
                'Leads' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75, 'import' => 89, 'export' => 89],
                'Accounts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => -98, 'import' => 89, 'export' => 89],
                'Opportunities' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75, 'import' => 89, 'export' => 89],
                'Contacts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75, 'import' => 89, 'export' => 89],
                'Calls' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
                'Meetings' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
                'Tasks' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
                'Notes' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
                'Emails' => ['access' => 89, 'view' => 89, 'list' => 89],
                'Cases' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => -98, 'delete' => -98],
                'Documents' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 75, 'delete' => -98],
            ],
        ],
        [
            'name' => 'Customer Success Manager',
            'description' => 'Can manage accounts, cases, and activities',
            'permissions' => [
                'Leads' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => -98, 'delete' => -98],
                'Accounts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => -98],
                'Opportunities' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 75, 'delete' => -98],
                'Contacts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => -98],
                'Calls' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
                'Meetings' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
                'Tasks' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
                'Notes' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
                'Emails' => ['access' => 89, 'view' => 89, 'list' => 89],
                'Cases' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
                'Documents' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 75],
            ],
        ],
        [
            'name' => 'Sales Manager',
            'description' => 'Full access to sales modules and team management',
            'permissions' => [
                'Leads' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89, 'import' => 89, 'export' => 89],
                'Accounts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89, 'import' => 89, 'export' => 89],
                'Opportunities' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89, 'import' => 89, 'export' => 89],
                'Contacts' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89, 'import' => 89, 'export' => 89],
                'Calls' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89],
                'Meetings' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89],
                'Tasks' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89],
                'Notes' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89],
                'Emails' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89],
                'Cases' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89],
                'Documents' => ['access' => 89, 'view' => 89, 'list' => 89, 'edit' => 89, 'delete' => 89],
            ],
        ],
    ];
    
    foreach ($roles as $roleData) {
        // Check if role already exists
        $query = "SELECT id FROM acl_roles WHERE name = ? AND deleted = 0";
        $stmt = $db->getConnection()->prepare($query);
        $stmt->execute([$roleData['name']]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            // Create role
            $role = new ACLRole();
            $role->name = $roleData['name'];
            $role->description = $roleData['description'];
            $role->save();
            
            echo "Created role: {$roleData['name']} (ID: {$role->id})\n";
            
            // Set module permissions
            foreach ($roleData['permissions'] as $module => $actions) {
                foreach ($actions as $action => $level) {
                    ACLRole::setAction($role->id, $module, $action, $level);
                }
            }
            
            echo "Configured permissions for: {$roleData['name']}\n";
        } else {
            echo "Role already exists: {$roleData['name']}\n";
        }
    }
    
    echo "\nRole creation completed!\n";
    echo "ACL Permission Values Reference:\n";
    echo "-99 = Not Set\n";
    echo "-98 = None (no access)\n";
    echo " 75 = Owner (can only edit/delete own records)\n";
    echo " 89 = All (full access)\n";
}

// Run the setup
try {
    createB2BRoles();
} catch (Exception $e) {
    echo "Error creating roles: " . $e->getMessage() . "\n";
    exit(1);
}