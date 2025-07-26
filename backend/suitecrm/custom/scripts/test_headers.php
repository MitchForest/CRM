<?php
// Test what headers are received
echo "All headers:\n";
print_r(getallheaders());

echo "\n\$_SERVER variables with AUTH:\n";
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'AUTH') !== false || strpos($key, 'HTTP_AUTH') !== false) {
        echo "$key: $value\n";
    }
}

echo "\nChecking specific headers:\n";
echo "HTTP_AUTHORIZATION: " . ($_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET') . "\n";
echo "REDIRECT_HTTP_AUTHORIZATION: " . ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'NOT SET') . "\n";

// Also check Apache's way
echo "\nApache getenv:\n";
echo "HTTP_AUTHORIZATION: " . getenv('HTTP_AUTHORIZATION') . "\n";
echo "REDIRECT_HTTP_AUTHORIZATION: " . getenv('REDIRECT_HTTP_AUTHORIZATION') . "\n";