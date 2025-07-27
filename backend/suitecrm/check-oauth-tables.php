<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

echo "Checking OAuth2 tables:\n\n";

$result = $db->query("SHOW TABLES LIKE 'oauth%'");
while ($row = $db->fetchByAssoc($result)) {
    print_r($row);
}

echo "\nAll tables with 'oauth' in name:\n";
$result = $db->query("SHOW TABLES");
while ($row = $db->fetchByAssoc($result)) {
    $table = array_values($row)[0];
    if (stripos($table, 'oauth') !== false) {
        echo "  - $table\n";
    }
}