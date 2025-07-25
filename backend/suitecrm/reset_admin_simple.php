<?php
// Simple admin password reset
$new_password = 'admin123';
$password_hash = password_hash($new_password, PASSWORD_BCRYPT);

// Direct MySQL connection
$mysqli = new mysqli('mysql', 'suitecrm', 'suitecrm', 'suitecrm');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$query = "UPDATE users SET user_hash = '$password_hash' WHERE user_name = 'admin' AND deleted = 0";
$result = $mysqli->query($query);

if ($result) {
    echo "Admin password has been reset to: $new_password\n";
    echo "Password hash: $password_hash\n";
} else {
    echo "Error updating password: " . $mysqli->error . "\n";
}

$mysqli->close();