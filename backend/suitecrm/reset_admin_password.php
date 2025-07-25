<?php
// Reset admin password script
require_once 'suitecrm/public/legacy/include/utils.php';
require_once 'suitecrm/public/legacy/include/entryPoint.php';

global $db;

$new_password = 'admin123';
$password_hash = password_hash($new_password, PASSWORD_BCRYPT);

$query = "UPDATE users SET user_hash = '$password_hash' WHERE user_name = 'admin' AND deleted = 0";
$db->query($query);

echo "Admin password has been reset to: $new_password\n";