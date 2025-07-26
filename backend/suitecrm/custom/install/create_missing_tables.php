<?php
/**
 * Create missing tables for Phase 5 features
 */

$host = 'mysql';
$user = 'root';
$pass = 'root';
$db = 'suitecrm';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "\n=== Creating Missing Tables ===\n";

// 1. Form Builder Tables
echo "Creating form builder tables...\n";

$conn->query("CREATE TABLE IF NOT EXISTS form_builder_forms (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    fields JSON,
    status VARCHAR(20) DEFAULT 'draft',
    created_by CHAR(36),
    modified_user_id CHAR(36),
    date_entered DATETIME,
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_status (status),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS form_builder_submissions (
    id CHAR(36) NOT NULL PRIMARY KEY,
    form_id CHAR(36) NOT NULL,
    data JSON,
    submitted_at DATETIME,
    lead_id CHAR(36),
    converted_at DATETIME,
    created_by CHAR(36),
    modified_user_id CHAR(36),
    date_entered DATETIME,
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_form_id (form_id),
    INDEX idx_lead_id (lead_id),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 2. Activity Tracking Tables
echo "Creating activity tracking tables...\n";

$conn->query("CREATE TABLE IF NOT EXISTS activity_tracking_visitors (
    visitor_id CHAR(36) NOT NULL PRIMARY KEY,
    lead_id CHAR(36),
    contact_id CHAR(36),
    first_visit DATETIME,
    last_visit DATETIME,
    total_visits INT DEFAULT 0,
    total_page_views INT DEFAULT 0,
    engagement_score INT DEFAULT 0,
    created_at DATETIME,
    INDEX idx_lead_id (lead_id),
    INDEX idx_contact_id (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS activity_tracking_sessions (
    session_id CHAR(36) NOT NULL PRIMARY KEY,
    visitor_id CHAR(36) NOT NULL,
    start_time DATETIME,
    end_time DATETIME,
    page_count INT DEFAULT 0,
    referrer VARCHAR(500),
    created_at DATETIME,
    INDEX idx_visitor_id (visitor_id),
    INDEX idx_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS activity_tracking_page_views (
    id CHAR(36) NOT NULL PRIMARY KEY,
    visitor_id CHAR(36) NOT NULL,
    session_id CHAR(36),
    page_url VARCHAR(500),
    page_title VARCHAR(255),
    time_on_page INT,
    timestamp DATETIME,
    created_at DATETIME,
    INDEX idx_visitor_id (visitor_id),
    INDEX idx_session_id (session_id),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 3. AI Chat Tables
echo "Creating AI chat tables...\n";

$conn->query("CREATE TABLE IF NOT EXISTS ai_chat_conversations (
    id CHAR(36) NOT NULL PRIMARY KEY,
    contact_id CHAR(36),
    lead_id CHAR(36),
    status VARCHAR(20) DEFAULT 'active',
    started_at DATETIME,
    ended_at DATETIME,
    metadata JSON,
    created_by CHAR(36),
    modified_user_id CHAR(36),
    date_entered DATETIME,
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_contact_id (contact_id),
    INDEX idx_lead_id (lead_id),
    INDEX idx_status (status),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS ai_chat_messages (
    id CHAR(36) NOT NULL PRIMARY KEY,
    conversation_id CHAR(36) NOT NULL,
    role VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    metadata JSON,
    created_at DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_created_at (created_at),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 4. Customer Health Score Table
echo "Creating customer health score table...\n";

$conn->query("CREATE TABLE IF NOT EXISTS customer_health_scores (
    id CHAR(36) NOT NULL PRIMARY KEY,
    account_id CHAR(36) NOT NULL,
    score INT NOT NULL,
    risk_level VARCHAR(20),
    factors JSON,
    calculated_at DATETIME,
    created_by CHAR(36),
    date_entered DATETIME,
    date_modified DATETIME,
    deleted TINYINT(1) DEFAULT 0,
    INDEX idx_account_id (account_id),
    INDEX idx_score (score),
    INDEX idx_risk_level (risk_level),
    INDEX idx_deleted (deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 5. Knowledge Base Feedback Table
echo "Creating knowledge base feedback table...\n";

$conn->query("CREATE TABLE IF NOT EXISTS knowledge_base_feedback (
    id CHAR(36) NOT NULL PRIMARY KEY,
    article_id CHAR(36) NOT NULL,
    helpful TINYINT(1) DEFAULT 1,
    feedback TEXT,
    user_id CHAR(36),
    visitor_id CHAR(36),
    date_created DATETIME,
    INDEX idx_article_id (article_id),
    INDEX idx_helpful (helpful),
    INDEX idx_date_created (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 6. API Tables
echo "Creating API tables...\n";

$conn->query("CREATE TABLE IF NOT EXISTS api_rate_limits (
    id CHAR(36) NOT NULL PRIMARY KEY,
    ip_address VARCHAR(45),
    user_id CHAR(36),
    endpoint VARCHAR(255),
    requests INT DEFAULT 0,
    window_start DATETIME,
    created_at DATETIME,
    INDEX idx_ip_address (ip_address),
    INDEX idx_user_id (user_id),
    INDEX idx_endpoint (endpoint),
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS webhook_events (
    id CHAR(36) NOT NULL PRIMARY KEY,
    event_type VARCHAR(50),
    payload JSON,
    webhook_url VARCHAR(500),
    status VARCHAR(20) DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_attempt DATETIME,
    date_created DATETIME,
    INDEX idx_status (status),
    INDEX idx_event_type (event_type),
    INDEX idx_date_created (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 7. Add columns to existing tables if they don't exist
echo "Adding missing columns to existing tables...\n";

// Add columns to aok_knowledgebase if they don't exist
$conn->query("ALTER TABLE aok_knowledgebase 
    ADD COLUMN IF NOT EXISTS helpful_count INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS not_helpful_count INT DEFAULT 0");

// Add lead_id to form_builder_submissions if not exists
$conn->query("ALTER TABLE form_builder_submissions 
    ADD COLUMN IF NOT EXISTS lead_id CHAR(36),
    ADD COLUMN IF NOT EXISTS converted_at DATETIME");

echo "\n=== All Tables Created Successfully ===\n";

$conn->close();