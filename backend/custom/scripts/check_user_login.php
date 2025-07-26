<?php
$host = 'mysql';
$user = 'root';
$pass = 'root';
$db = 'suitecrm';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check john.doe user
$result = $conn->query("SELECT id, user_name, user_hash, status FROM users WHERE user_name = 'john.doe' AND deleted = 0");

if ($row = $result->fetch_assoc()) {
    echo "User found:\n";
    echo "ID: " . $row['id'] . "\n";
    echo "Username: " . $row['user_name'] . "\n";
    echo "Status: " . $row['status'] . "\n";
    echo "Hash: " . $row['user_hash'] . "\n\n";
    
    // Test password
    $testPassword = 'admin123';
    if (password_verify($testPassword, $row['user_hash'])) {
        echo "Password verification: SUCCESS\n";
    } else {
        echo "Password verification: FAILED\n";
        // Create new hash
        $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
        echo "New hash would be: $newHash\n";
    }
    
    // Check email
    $userId = $row['id'];
    $emailQuery = "SELECT ea.email_address 
                  FROM email_addresses ea
                  JOIN email_addr_bean_rel eabr ON ea.id = eabr.email_address_id
                  WHERE eabr.bean_id = '$userId' AND eabr.bean_module = 'Users' 
                  AND eabr.deleted = 0 AND ea.deleted = 0";
    $emailResult = $conn->query($emailQuery);
    
    echo "\nUser emails:\n";
    while ($emailRow = $emailResult->fetch_assoc()) {
        echo "  - " . $emailRow['email_address'] . "\n";
    }
} else {
    echo "User john.doe not found\n";
}

// Also check if we can login by email
echo "\n\nTesting email-based login query:\n";
$email = 'john.doe@example.com';
$query = "SELECT u.* 
         FROM users u
         JOIN email_addr_bean_rel eabr ON u.id = eabr.bean_id AND eabr.bean_module = 'Users' AND eabr.deleted = 0
         JOIN email_addresses ea ON eabr.email_address_id = ea.id AND ea.deleted = 0
         WHERE ea.email_address = '$email' AND u.deleted = 0 AND u.status = 'Active'";
$result = $conn->query($query);

if ($row = $result->fetch_assoc()) {
    echo "Found user by email: " . $row['user_name'] . " (ID: " . $row['id'] . ")\n";
} else {
    echo "Could not find user by email\n";
}

$conn->close();