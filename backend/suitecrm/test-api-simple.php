<?php
// Simple test to see if the V8 API is working at all

echo "Testing V8 API endpoints...\n\n";

// Test 1: Try to access the base API URL
echo "1. Testing base API URL:\n";
$ch = curl_init('http://localhost/Api/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   /Api/ - HTTP $httpCode\n";

// Test 2: Try to access V8
echo "\n2. Testing V8 URL:\n";
$ch = curl_init('http://localhost/Api/V8/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   /Api/V8/ - HTTP $httpCode\n";

// Test 3: Check if Api/index.php exists
echo "\n3. Checking API files:\n";
echo "   Api/index.php exists: " . (file_exists('/var/www/html/Api/index.php') ? 'YES' : 'NO') . "\n";
echo "   Api/Core/app.php exists: " . (file_exists('/var/www/html/Api/Core/app.php') ? 'YES' : 'NO') . "\n";

// Test 4: Check Apache rewrite
echo "\n4. Checking .htaccess:\n";
if (file_exists('/var/www/html/.htaccess')) {
    $htaccess = file_get_contents('/var/www/html/.htaccess');
    if (strpos($htaccess, 'Api/index.php') !== false) {
        echo "   API rewrite rules found in .htaccess\n";
    } else {
        echo "   NO API rewrite rules in .htaccess!\n";
    }
}