<?php
// Debug auth process
define('sugarEntry', true);
require_once('/var/www/html/include/entryPoint.php');

global $db;

$username = 'john.doe';
$password = 'admin123';

echo "Testing authentication for user: $username\n\n";

// Test query
$username_escaped = $db->quote($username);
echo "Escaped username: $username_escaped\n";

$query = "SELECT * FROM users WHERE user_name = $username_escaped AND deleted = 0 AND status = 'Active'";
echo "Query: $query\n\n";

$result = $db->query($query);
$user_row = $db->fetchByAssoc($result);

if ($user_row) {
    echo "User found!\n";
    echo "ID: " . $user_row['id'] . "\n";
    echo "Username: " . $user_row['user_name'] . "\n";
    echo "Status: " . $user_row['status'] . "\n";
    echo "Hash length: " . strlen($user_row['user_hash']) . "\n";
    echo "Hash: " . $user_row['user_hash'] . "\n\n";
    
    // Test password
    $verify = password_verify($password, $user_row['user_hash']);
    echo "Password verify result: " . ($verify ? 'TRUE' : 'FALSE') . "\n";
    
    // Test with different password
    $testHash = password_hash($password, PASSWORD_DEFAULT);
    echo "\nNew hash for 'admin123': $testHash\n";
    echo "Verify with new hash: " . (password_verify($password, $testHash) ? 'TRUE' : 'FALSE') . "\n";
} else {
    echo "User not found!\n";
    
    // Check all users
    echo "\nAll active users:\n";
    $allQuery = "SELECT user_name, status FROM users WHERE deleted = 0";
    $allResult = $db->query($allQuery);
    while ($row = $db->fetchByAssoc($allResult)) {
        echo "  - " . $row['user_name'] . " (Status: " . $row['status'] . ")\n";
    }
}