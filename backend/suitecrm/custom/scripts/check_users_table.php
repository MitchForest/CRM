<?php
$host = 'mysql';
$user = 'root';
$pass = 'root';
$db = 'suitecrm';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check users table columns
$result = $conn->query("SHOW COLUMNS FROM users");
echo "users table columns:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

// Check if email field exists
$result = $conn->query("SELECT * FROM users WHERE deleted = 0 LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    echo "\nSample user record fields:\n";
    foreach ($row as $key => $value) {
        if (strpos(strtolower($key), 'email') !== false || strpos(strtolower($key), 'mail') !== false) {
            echo "  - $key: $value\n";
        }
    }
}

$conn->close();