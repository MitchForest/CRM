<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('include/entryPoint.php');

// Create a test user with known password
$user = new User();
$user->user_name = 'apiuser';
$user->user_hash = User::getPasswordHash('apiuser123');
$user->first_name = 'API';
$user->last_name = 'User';
$user->status = 'Active';
$user->is_admin = 1;
$user->employee_status = 'Active';
$user->email1 = 'apiuser@example.com';
$user->save();

echo "Created user: apiuser with password: apiuser123\n";
echo "User ID: " . $user->id . "\n";