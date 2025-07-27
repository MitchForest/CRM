<?php
// Direct OAuth2 test
if (!defined('sugarEntry')) define('sugarEntry', true);

// Minimal bootstrap to avoid entryPoint issues
chdir('/var/www/html');
require_once 'config.php';
require_once 'include/database/DBManagerFactory.php';
require_once 'include/utils.php';

global $db, $sugar_config;
$db = DBManagerFactory::getInstance();

echo "Testing OAuth2 setup:\n\n";

// Check OAuth2 client
$query = "SELECT * FROM oauth2clients WHERE id = 'suitecrm_client'";
$result = $db->query($query);
$client = $db->fetchByAssoc($result);

echo "1. OAuth2 Client:\n";
if ($client) {
    echo "   - ID: " . $client['id'] . "\n";
    echo "   - Name: " . $client['name'] . "\n";
    echo "   - Grant Type: " . $client['allowed_grant_type'] . "\n";
    echo "   - Secret exists: " . (!empty($client['secret']) ? 'YES' : 'NO') . "\n";
} else {
    echo "   - NOT FOUND\n";
}

// Check admin user
$query = "SELECT id, user_name FROM users WHERE user_name = 'admin' AND deleted = 0";
$result = $db->query($query);
$user = $db->fetchByAssoc($result);

echo "\n2. Admin User:\n";
if ($user) {
    echo "   - ID: " . $user['id'] . "\n";
    echo "   - Username: " . $user['user_name'] . "\n";
} else {
    echo "   - NOT FOUND\n";
}

// Check OAuth2 keys
echo "\n3. OAuth2 Keys:\n";
echo "   - Encryption key: " . (!empty($sugar_config['oauth2_encryption_key']) ? 'SET' : 'MISSING') . "\n";

// Test direct API access
echo "\n4. Testing direct API access...\n";
try {
    require_once 'Api/Core/Loader/CustomLoader.php';
    require_once 'vendor/autoload.php';
    echo "   - API loader: OK\n";
} catch (Exception $e) {
    echo "   - API loader: FAILED - " . $e->getMessage() . "\n";
}