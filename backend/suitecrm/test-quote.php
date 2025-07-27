<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

echo "Testing db->quote() method:\n\n";

$testString = 'suitecrm_client';
$quoted = $db->quote($testString);

echo "Original: $testString\n";
echo "Quoted: $quoted\n";
echo "Type: " . gettype($quoted) . "\n";

// Test in a query
$query = "SELECT * FROM oauth2clients WHERE id = " . $db->quote($testString);
echo "\nGenerated query:\n$query\n";