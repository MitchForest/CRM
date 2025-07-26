<?php
// Test JWT decoding
require_once('/var/www/html/custom/api/Auth/JWT.php');

$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjoiZDc5ZTkyNTItZTU1NS02N2FhLWU5MGItNjg4NGYxZjNjNDcwIiwidXNlcm5hbWUiOiJqb2huLmRvZSIsImVtYWlsIjoiam9obi5kb2VAZXhhbXBsZS5jb20iLCJleHAiOjE3NTM1NDY0MDksImlhdCI6MTc1MzU0NTUwOX0.IgcI-rIJfoIbS9SuqQ6Ayz1Z-drxSwgODpqdVXusFII';

echo "Testing JWT decode...\n";

try {
    $payload = \Api\Auth\JWT::decode($token);
    echo "Decoded successfully:\n";
    print_r($payload);
    
    echo "\nUser ID: " . ($payload['user_id'] ?? 'NOT FOUND') . "\n";
    
    // Check if expired
    if (isset($payload['exp'])) {
        $expired = $payload['exp'] < time();
        echo "Expired: " . ($expired ? 'YES' : 'NO') . "\n";
        echo "Expires at: " . date('Y-m-d H:i:s', $payload['exp']) . "\n";
        echo "Current time: " . date('Y-m-d H:i:s') . "\n";
    }
} catch (Exception $e) {
    echo "Error decoding: " . $e->getMessage() . "\n";
}