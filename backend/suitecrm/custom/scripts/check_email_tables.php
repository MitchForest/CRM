<?php
$host = 'mysql';
$user = 'root';
$pass = 'root';
$db = 'suitecrm';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check for email-related tables
$result = $conn->query("SHOW TABLES LIKE '%email%'");
echo "Email-related tables:\n";
while ($row = $result->fetch_array()) {
    echo "  - " . $row[0] . "\n";
}

// Check email_addresses table structure
$result = $conn->query("SHOW COLUMNS FROM email_addresses");
if ($result) {
    echo "\nemail_addresses table columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

// Check email_addr_bean_rel table
$result = $conn->query("SHOW COLUMNS FROM email_addr_bean_rel");
if ($result) {
    echo "\nemail_addr_bean_rel table columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

// Check if admin user has email
$result = $conn->query("
    SELECT u.id, u.user_name, ea.email_address 
    FROM users u
    LEFT JOIN email_addr_bean_rel eabr ON u.id = eabr.bean_id AND eabr.bean_module = 'Users' AND eabr.deleted = 0
    LEFT JOIN email_addresses ea ON eabr.email_address_id = ea.id AND ea.deleted = 0
    WHERE u.user_name = 'admin' AND u.deleted = 0
");

echo "\nAdmin user email info:\n";
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

$conn->close();