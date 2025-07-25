<?php
/**
 * Create a JWT token for testing
 */

if (!defined('sugarEntry')) define('sugarEntry', true);
require_once('/var/www/html/include/entryPoint.php');
require_once('/var/www/html/custom/api/auth/JWT.php');

use Api\Auth\JWT;

// Create token payload
$payload = [
    'user_id' => '1', // Admin user
    'username' => 'admin',
    'iat' => time(),
    'exp' => time() + 86400 // 24 hours
];

// Generate JWT token
$token = JWT::encode($payload);

echo "JWT Token created:\n";
echo $token . "\n\n";
echo "Use in Authorization header: Bearer $token\n";
echo "Expires: " . date('Y-m-d H:i:s', $payload['exp']) . "\n";