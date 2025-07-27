<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

echo "Listing all OAuth2 clients:\n\n";

$result = $db->query("SELECT * FROM oauth2clients");
$count = 0;
while ($row = $db->fetchByAssoc($result)) {
    $count++;
    echo "Client #$count:\n";
    foreach ($row as $key => $value) {
        echo "  $key: $value\n";
    }
    echo "\n";
}

echo "Total clients: $count\n";