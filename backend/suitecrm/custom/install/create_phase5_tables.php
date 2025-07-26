<?php
/**
 * Create Phase 5 Tables - Ensures all custom tables exist
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line\n");
}

// Load SuiteCRM bootstrap
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

chdir(dirname(__FILE__) . '/../../..');
require_once('include/entryPoint.php');

global $db;

echo "\n=== Creating Phase 5 Custom Tables ===\n";

try {
    // Add is_company field to contacts if it doesn't exist
    echo "Checking contacts table modifications...\n";
    $result = $db->query("SHOW COLUMNS FROM contacts LIKE 'is_company'");
    if (!$db->fetchByAssoc($result)) {
        $db->query("ALTER TABLE contacts ADD COLUMN is_company TINYINT(1) DEFAULT 0");
        echo "  Added is_company field to contacts\n";
    }
    
    // Add lead_id to opportunities if it doesn't exist
    echo "Checking opportunities table modifications...\n";
    $result = $db->query("SHOW COLUMNS FROM opportunities LIKE 'lead_id'");
    if (!$db->fetchByAssoc($result)) {
        $db->query("ALTER TABLE opportunities ADD COLUMN lead_id CHAR(36) DEFAULT NULL");
        $db->query("ALTER TABLE opportunities ADD INDEX idx_lead_id (lead_id)");
        echo "  Added lead_id field to opportunities\n";
    }
    
    // API Refresh Tokens table
    echo "Creating API refresh tokens table...\n";
    $db->query("CREATE TABLE IF NOT EXISTS api_refresh_tokens (
        id CHAR(36) NOT NULL PRIMARY KEY,
        user_id CHAR(36) NOT NULL,
        token TEXT NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Form Builder Tables
    echo "Creating form builder tables...\n";
    $db->query("CREATE TABLE IF NOT EXISTS form_builder_forms (
        id CHAR(36) NOT NULL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        fields JSON NOT NULL,
        settings JSON NOT NULL,
        embed_code TEXT,
        is_active TINYINT(1) DEFAULT 1,
        submissions_count INT DEFAULT 0,
        created_by CHAR(36),
        date_entered DATETIME,
        date_modified DATETIME,
        modified_user_id CHAR(36),
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_active_deleted (is_active, deleted),
        INDEX idx_created_by (created_by),
        INDEX idx_date_entered (date_entered)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $db->query("CREATE TABLE IF NOT EXISTS form_builder_submissions (
        id CHAR(36) NOT NULL PRIMARY KEY,
        form_id CHAR(36) NOT NULL,
        data JSON NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        referrer_url TEXT,
        lead_id CHAR(36),
        contact_id CHAR(36),
        date_submitted DATETIME,
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_form_id (form_id),
        INDEX idx_lead_id (lead_id),
        INDEX idx_contact_id (contact_id),
        INDEX idx_date_submitted (date_submitted),
        FOREIGN KEY (form_id) REFERENCES form_builder_forms(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Knowledge Base Tables
    echo "Creating knowledge base tables...\n";
    $db->query("CREATE TABLE IF NOT EXISTS knowledge_base_articles (
        id CHAR(36) NOT NULL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        content TEXT NOT NULL,
        summary TEXT,
        category VARCHAR(100),
        tags JSON,
        is_published TINYINT(1) DEFAULT 0,
        is_featured TINYINT(1) DEFAULT 0,
        view_count INT DEFAULT 0,
        helpful_count INT DEFAULT 0,
        not_helpful_count INT DEFAULT 0,
        embedding TEXT,
        author_id CHAR(36),
        date_published DATETIME,
        date_entered DATETIME,
        date_modified DATETIME,
        modified_user_id CHAR(36),
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_slug (slug),
        INDEX idx_published (is_published, deleted),
        INDEX idx_category (category),
        INDEX idx_featured (is_featured, is_published, deleted),
        FULLTEXT idx_search (title, content, summary)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $db->query("CREATE TABLE IF NOT EXISTS knowledge_base_feedback (
        id CHAR(36) NOT NULL PRIMARY KEY,
        article_id CHAR(36) NOT NULL,
        user_id CHAR(36),
        session_id VARCHAR(255),
        is_helpful TINYINT(1),
        feedback_text TEXT,
        date_created DATETIME,
        INDEX idx_article_id (article_id),
        INDEX idx_user_id (user_id),
        FOREIGN KEY (article_id) REFERENCES knowledge_base_articles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Activity Tracking Tables
    echo "Creating activity tracking tables...\n";
    $db->query("CREATE TABLE IF NOT EXISTS activity_tracking_visitors (
        id CHAR(36) NOT NULL PRIMARY KEY,
        visitor_id VARCHAR(255) NOT NULL UNIQUE,
        lead_id CHAR(36),
        contact_id CHAR(36),
        first_visit DATETIME,
        last_visit DATETIME,
        total_visits INT DEFAULT 1,
        total_page_views INT DEFAULT 0,
        total_time_spent INT DEFAULT 0,
        browser VARCHAR(255),
        device_type VARCHAR(50),
        referrer_source VARCHAR(255),
        utm_source VARCHAR(255),
        utm_medium VARCHAR(255),
        utm_campaign VARCHAR(255),
        engagement_score INT DEFAULT 0,
        date_entered DATETIME,
        date_modified DATETIME,
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_lead_id (lead_id),
        INDEX idx_contact_id (contact_id),
        INDEX idx_last_visit (last_visit),
        INDEX idx_engagement (engagement_score, deleted)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $db->query("CREATE TABLE IF NOT EXISTS activity_tracking_sessions (
        id CHAR(36) NOT NULL PRIMARY KEY,
        visitor_id VARCHAR(255) NOT NULL,
        session_id VARCHAR(255) NOT NULL,
        start_time DATETIME,
        end_time DATETIME,
        duration INT DEFAULT 0,
        page_count INT DEFAULT 0,
        bounce TINYINT(1) DEFAULT 0,
        ip_address VARCHAR(45),
        user_agent TEXT,
        date_entered DATETIME,
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_visitor_id (visitor_id),
        INDEX idx_session_id (session_id),
        INDEX idx_start_time (start_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $db->query("CREATE TABLE IF NOT EXISTS activity_tracking_pageviews (
        id CHAR(36) NOT NULL PRIMARY KEY,
        visitor_id VARCHAR(255) NOT NULL,
        session_id VARCHAR(255) NOT NULL,
        page_url TEXT,
        page_title VARCHAR(255),
        referrer_url TEXT,
        time_on_page INT DEFAULT 0,
        exit_page TINYINT(1) DEFAULT 0,
        date_entered DATETIME,
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_visitor_session (visitor_id, session_id),
        INDEX idx_date_entered (date_entered)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $db->query("CREATE TABLE IF NOT EXISTS activity_tracking_events (
        id CHAR(36) NOT NULL PRIMARY KEY,
        visitor_id VARCHAR(255) NOT NULL,
        session_id VARCHAR(255) NOT NULL,
        event_type VARCHAR(100),
        event_data JSON,
        page_url TEXT,
        date_entered DATETIME,
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_visitor_session (visitor_id, session_id),
        INDEX idx_event_type (event_type),
        INDEX idx_date_entered (date_entered)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // AI Chat Tables
    echo "Creating AI chat tables...\n";
    $db->query("CREATE TABLE IF NOT EXISTS ai_chat_conversations (
        id CHAR(36) NOT NULL PRIMARY KEY,
        visitor_id VARCHAR(255),
        lead_id CHAR(36),
        contact_id CHAR(36),
        status VARCHAR(50),
        started_at DATETIME,
        ended_at DATETIME,
        transcript JSON,
        date_entered DATETIME,
        date_modified DATETIME,
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_visitor_id (visitor_id),
        INDEX idx_lead_id (lead_id),
        INDEX idx_contact_id (contact_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $db->query("CREATE TABLE IF NOT EXISTS ai_chat_messages (
        id CHAR(36) NOT NULL PRIMARY KEY,
        conversation_id CHAR(36) NOT NULL,
        sender_type VARCHAR(50),
        message TEXT,
        metadata JSON,
        sent_at DATETIME,
        date_entered DATETIME,
        date_modified DATETIME,
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_sent_at (sent_at),
        FOREIGN KEY (conversation_id) REFERENCES ai_chat_conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Scoring Tables
    echo "Creating scoring tables...\n";
    $db->query("CREATE TABLE IF NOT EXISTS ai_lead_scores (
        id CHAR(36) NOT NULL PRIMARY KEY,
        lead_id CHAR(36) NOT NULL,
        score INT DEFAULT 0,
        factors JSON,
        calculated_at DATETIME,
        date_entered DATETIME,
        date_modified DATETIME,
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_lead_id (lead_id),
        INDEX idx_score (score),
        INDEX idx_calculated_at (calculated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    $db->query("CREATE TABLE IF NOT EXISTS customer_health_scores (
        id CHAR(36) NOT NULL PRIMARY KEY,
        account_id CHAR(36) NOT NULL,
        contact_id CHAR(36),
        score INT DEFAULT 0,
        risk_level VARCHAR(50),
        factors JSON,
        recommendations JSON,
        calculated_at DATETIME,
        date_entered DATETIME,
        date_modified DATETIME,
        deleted TINYINT(1) DEFAULT 0,
        INDEX idx_account_id (account_id),
        INDEX idx_contact_id (contact_id),
        INDEX idx_score (score),
        INDEX idx_risk_level (risk_level),
        INDEX idx_calculated_at (calculated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "\n=== All Phase 5 tables created successfully! ===\n";
    
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}