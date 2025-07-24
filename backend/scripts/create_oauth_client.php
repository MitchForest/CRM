<?php
/**
 * Create OAuth2 Client for API Access
 */

// Database connection
$host = 'suitecrm-mysql'; // Docker container name
$db = 'suitecrm';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if oauth2clients table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'oauth2clients'");
    if ($tableCheck->rowCount() == 0) {
        // Create oauth2clients table
        $createTable = "
        CREATE TABLE IF NOT EXISTS oauth2clients (
            id CHAR(36) NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            date_entered DATETIME DEFAULT NULL,
            date_modified DATETIME DEFAULT NULL,
            modified_user_id CHAR(36) DEFAULT NULL,
            created_by CHAR(36) DEFAULT NULL,
            description TEXT,
            deleted TINYINT(1) DEFAULT '0',
            assigned_user_id CHAR(36) DEFAULT NULL,
            client_id VARCHAR(255) DEFAULT NULL,
            client_secret VARCHAR(255) DEFAULT NULL,
            redirect_uri VARCHAR(255) DEFAULT NULL,
            is_confidential TINYINT(1) DEFAULT '1',
            allowed_grant_type VARCHAR(255) DEFAULT 'password',
            PRIMARY KEY (id),
            KEY idx_oauth2clients_client_id (client_id),
            KEY idx_oauth2clients_date_entered (date_entered),
            KEY idx_oauth2clients_date_modified (date_modified),
            KEY idx_oauth2clients_assigned_user (assigned_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";
        $pdo->exec($createTable);
        echo "Created oauth2clients table\n";
    }
    
    // Generate client credentials
    $clientId = 'sugar';
    $clientSecret = hash('sha256', 'sugar_secret_' . time());
    $id = generateUUID();
    
    // Check if client already exists
    $stmt = $pdo->prepare("SELECT id FROM oauth2clients WHERE client_id = ? AND deleted = 0");
    $stmt->execute([$clientId]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing client
        $updateStmt = $pdo->prepare("
            UPDATE oauth2clients 
            SET client_secret = ?, 
                date_modified = NOW(),
                is_confidential = 1,
                allowed_grant_type = 'password'
            WHERE client_id = ? AND deleted = 0
        ");
        $updateStmt->execute([$clientSecret, $clientId]);
        echo "Updated existing OAuth2 client\n";
    } else {
        // Insert new client
        $insertStmt = $pdo->prepare("
            INSERT INTO oauth2clients (
                id, name, date_entered, date_modified, 
                deleted, client_id, client_secret, 
                is_confidential, allowed_grant_type
            ) VALUES (
                ?, 'API Client', NOW(), NOW(), 
                0, ?, ?, 
                1, 'password'
            )
        ");
        $insertStmt->execute([$id, $clientId, $clientSecret]);
        echo "Created new OAuth2 client\n";
    }
    
    echo "\nOAuth2 Client Credentials:\n";
    echo "Client ID: $clientId\n";
    echo "Client Secret: $clientSecret\n";
    echo "\nPlease save these credentials for API authentication.\n";
    
    // Also create a simple client for testing
    $testId = generateUUID();
    $testClientId = 'test_client';
    $testClientSecret = 'test_secret';
    
    $stmt = $pdo->prepare("SELECT id FROM oauth2clients WHERE client_id = ? AND deleted = 0");
    $stmt->execute([$testClientId]);
    
    if ($stmt->rowCount() == 0) {
        $insertStmt = $pdo->prepare("
            INSERT INTO oauth2clients (
                id, name, date_entered, date_modified, 
                deleted, client_id, client_secret, 
                is_confidential, allowed_grant_type
            ) VALUES (
                ?, 'Test Client', NOW(), NOW(), 
                0, ?, ?, 
                1, 'password'
            )
        ");
        $insertStmt->execute([$testId, $testClientId, $testClientSecret]);
        echo "\nAlso created test client:\n";
        echo "Client ID: $testClientId\n";
        echo "Client Secret: $testClientSecret\n";
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}