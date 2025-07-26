<?php
$host = 'mysql';
$user = 'root';
$pass = 'root';
$db = 'suitecrm';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate UUID
function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Check if test user exists
$result = $conn->query("SELECT id FROM users WHERE user_name = 'john.doe' AND deleted = 0");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $userId = $row['id'];
    
    // Update password
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET user_hash = '$passwordHash', status = 'Active' WHERE id = '$userId'");
    echo "Updated existing user john.doe\n";
} else {
    // Create new user
    $userId = generateUUID();
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (
        id, user_name, user_hash, first_name, last_name, 
        status, is_admin, date_entered, date_modified, 
        created_by, modified_user_id, deleted
    ) VALUES (
        '$userId', 'john.doe', '$passwordHash', 'John', 'Doe',
        'Active', 0, NOW(), NOW(),
        '1', '1', 0
    )";
    
    if ($conn->query($sql)) {
        echo "Created new user john.doe\n";
    } else {
        die("Error creating user: " . $conn->error);
    }
}

// Now handle email address
// First check if email exists
$emailResult = $conn->query("SELECT id FROM email_addresses WHERE email_address = 'john.doe@example.com' AND deleted = 0");

if ($emailResult->num_rows > 0) {
    $emailRow = $emailResult->fetch_assoc();
    $emailId = $emailRow['id'];
    echo "Email address already exists\n";
} else {
    // Create email address
    $emailId = generateUUID();
    $sql = "INSERT INTO email_addresses (
        id, email_address, email_address_caps, 
        invalid_email, opt_out, deleted, 
        date_created, date_modified
    ) VALUES (
        '$emailId', 'john.doe@example.com', 'JOHN.DOE@EXAMPLE.COM',
        0, 0, 0,
        NOW(), NOW()
    )";
    
    if ($conn->query($sql)) {
        echo "Created email address\n";
    } else {
        die("Error creating email: " . $conn->error);
    }
}

// Check if relationship exists
$relResult = $conn->query("
    SELECT id FROM email_addr_bean_rel 
    WHERE bean_id = '$userId' 
    AND bean_module = 'Users' 
    AND email_address_id = '$emailId'
    AND deleted = 0
");

if ($relResult->num_rows == 0) {
    // Create relationship
    $relId = generateUUID();
    $sql = "INSERT INTO email_addr_bean_rel (
        id, email_address_id, bean_id, bean_module,
        primary_address, reply_to_address, deleted,
        date_created, date_modified
    ) VALUES (
        '$relId', '$emailId', '$userId', 'Users',
        1, 0, 0,
        NOW(), NOW()
    )";
    
    if ($conn->query($sql)) {
        echo "Created email relationship\n";
    } else {
        echo "Error creating relationship: " . $conn->error . "\n";
    }
}

echo "\nTest user setup complete:\n";
echo "Username: john.doe\n";
echo "Email: john.doe@example.com\n";
echo "Password: admin123\n";

$conn->close();