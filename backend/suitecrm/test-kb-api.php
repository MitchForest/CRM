<?php
// Test Knowledge Base API endpoints

echo "=== Testing Knowledge Base API ===\n\n";

// 1. Test getting categories
echo "1. Testing GET /api/v8/kb/categories:\n";
$ch = curl_init('http://localhost/api/v8/kb/categories');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "   Categories found: " . count($data['data'] ?? []) . "\n";
    foreach (($data['data'] ?? []) as $cat) {
        echo "   - " . $cat['name'] . "\n";
    }
} else {
    echo "   Response: " . substr($response, 0, 200) . "\n";
}

// 2. Test getting articles
echo "\n2. Testing GET /api/v8/kb/articles:\n";
$ch = curl_init('http://localhost/api/v8/kb/articles');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "   Articles found: " . count($data['data'] ?? []) . "\n";
    foreach (($data['data'] ?? []) as $index => $article) {
        if ($index < 3) {
            echo "   - " . ($article['title'] ?? 'Untitled') . "\n";
        }
    }
} else {
    echo "   Response: " . substr($response, 0, 200) . "\n";
}

// 3. Test creating an article (requires auth)
echo "\n3. Testing article creation (requires auth):\n";
echo "   Skipping - would need authentication token\n";

echo "\n=== END TEST ===\n";