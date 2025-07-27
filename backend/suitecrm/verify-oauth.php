<?php
if (!defined('sugarEntry')) define('sugarEntry', true);
require_once 'include/entryPoint.php';

$result = $db->query("SELECT * FROM oauth2clients");
echo "OAuth2 Clients:\n";
while ($row = $db->fetchByAssoc($result)) {
    echo "ID: " . $row['id'] . "\n";
    echo "Name: " . $row['name'] . "\n";
    echo "Secret: " . $row['secret'] . "\n";
    echo "Grant types: " . $row['allowed_grant_type'] . "\n";
    echo "---\n";
}