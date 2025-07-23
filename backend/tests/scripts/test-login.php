<?php
// Test SuiteCRM login directly
define('sugarEntry', true);
require_once(__DIR__ . '/../../include/entryPoint.php');

// Test different authentication methods
echo "Testing SuiteCRM Authentication\n";
echo "==============================\n\n";

// Method 1: Direct user lookup
global $db;
$result = $db->query("SELECT id, user_name, user_hash FROM users WHERE user_name = 'admin' AND deleted = 0");
$user = $db->fetchByAssoc($result);

echo "Admin user found: " . ($user ? 'Yes' : 'No') . "\n";
if ($user) {
    echo "User ID: " . $user['id'] . "\n";
    echo "Username: " . $user['user_name'] . "\n";
    echo "Hash starts with: " . substr($user['user_hash'], 0, 20) . "...\n";
    
    // Test password
    $testPasswords = ['admin', 'admin123', 'password'];
    foreach ($testPasswords as $pass) {
        $valid = password_verify($pass, $user['user_hash']);
        echo "Password '$pass': " . ($valid ? 'VALID' : 'invalid') . "\n";
    }
}

// Method 2: Try SuiteCRM authentication
echo "\nTesting AuthenticationController:\n";
require_once('modules/Users/authentication/AuthenticationController.php');
$auth = new AuthenticationController();

// The login method expects: username, password (NOT hashed), fallback
$loginResult = $auth->login('admin', 'admin', false);
echo "Login with 'admin': " . ($loginResult ? 'SUCCESS' : 'FAILED') . "\n";

if ($loginResult) {
    global $current_user;
    echo "Current user ID: " . $current_user->id . "\n";
    echo "Current username: " . $current_user->user_name . "\n";
}