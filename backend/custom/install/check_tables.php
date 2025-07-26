<?php
$host = 'mysql';
$user = 'root';
$pass = 'root';
$db = 'suitecrm';

$conn = new mysqli($host, $user, $pass, $db);

// Check form_builder_forms columns
$result = $conn->query("SHOW COLUMNS FROM form_builder_forms");
echo "form_builder_forms columns:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n";

// Check form_builder_submissions columns
$result = $conn->query("SHOW COLUMNS FROM form_builder_submissions");
echo "form_builder_submissions columns:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n";

// Check if activity_tracking_visitors exists
$result = $conn->query("SHOW COLUMNS FROM activity_tracking_visitors");
if ($result) {
    echo "activity_tracking_visitors columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "activity_tracking_visitors table does not exist\n";
}

$conn->close();