-- Phase 3 Custom Tables for AI-Powered CRM Features
-- Run this script to create all required tables

-- Form Builder Tables
CREATE TABLE IF NOT EXISTS form_builder_forms (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_builder_submissions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Knowledge Base Tables
CREATE TABLE IF NOT EXISTS knowledge_base_articles (
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
    embedding TEXT, -- Store OpenAI embedding as JSON
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knowledge_base_feedback (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Tracking Tables
CREATE TABLE IF NOT EXISTS activity_tracking_visitors (
    id CHAR(36) NOT NULL PRIMARY KEY,
    visitor_id VARCHAR(255) NOT NULL UNIQUE,
    lead_id CHAR(36),
    contact_id CHAR(36),
    first_visit DATETIME,
    last_visit DATETIME,
    total_visits INT DEFAULT 1,
    total_page_views INT DEFAULT 0,
    total_time_spent INT DEFAULT 0, -- in seconds
    browser VARCHAR(255),
    device_type VARCHAR(50),
    referrer_source VARCHAR(255),
    utm_source VARCHAR(255),
    utm_medium VARCHAR(255),
    utm_campaign VARCHAR(255),
    engagement_score INT DEFAULT 0,
    date_modified DATETIME,
    INDEX idx_visitor_id (visitor_id),
    INDEX idx_lead_id (lead_id),
    INDEX idx_contact_id (contact_id),
    INDEX idx_last_visit (last_visit),
    INDEX idx_engagement_score (engagement_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_tracking_sessions (
    id CHAR(36) NOT NULL PRIMARY KEY,
    visitor_id VARCHAR(255) NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    start_time DATETIME,
    end_time DATETIME,
    duration INT DEFAULT 0, -- in seconds
    page_count INT DEFAULT 0,
    bounce TINYINT(1) DEFAULT 0,
    conversion_event VARCHAR(255),
    date_created DATETIME,
    INDEX idx_visitor_id (visitor_id),
    INDEX idx_session_id (session_id),
    INDEX idx_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_tracking_page_views (
    id CHAR(36) NOT NULL PRIMARY KEY,
    visitor_id VARCHAR(255) NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    page_url TEXT,
    page_title VARCHAR(255),
    referrer_url TEXT,
    time_on_page INT DEFAULT 0, -- in seconds
    scroll_depth INT DEFAULT 0, -- percentage
    clicks INT DEFAULT 0,
    is_high_value TINYINT(1) DEFAULT 0,
    date_created DATETIME,
    INDEX idx_visitor_session (visitor_id, session_id),
    INDEX idx_date_created (date_created),
    INDEX idx_high_value (is_high_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Chat Tables
CREATE TABLE IF NOT EXISTS ai_chat_conversations (
    id CHAR(36) NOT NULL PRIMARY KEY,
    visitor_id VARCHAR(255),
    lead_id CHAR(36),
    contact_id CHAR(36),
    status VARCHAR(50) DEFAULT 'active', -- active, ended, handoff
    handoff_to_user_id CHAR(36),
    start_time DATETIME,
    end_time DATETIME,
    message_count INT DEFAULT 0,
    satisfaction_rating INT,
    date_created DATETIME,
    date_modified DATETIME,
    INDEX idx_visitor_id (visitor_id),
    INDEX idx_lead_id (lead_id),
    INDEX idx_status (status),
    INDEX idx_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_chat_messages (
    id CHAR(36) NOT NULL PRIMARY KEY,
    conversation_id CHAR(36) NOT NULL,
    role VARCHAR(50) NOT NULL, -- user, assistant, system
    content TEXT NOT NULL,
    metadata JSON, -- Store additional data like intent, confidence, etc.
    date_created DATETIME,
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_date_created (date_created),
    FOREIGN KEY (conversation_id) REFERENCES ai_chat_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI Lead Scoring History
CREATE TABLE IF NOT EXISTS ai_lead_scoring_history (
    id CHAR(36) NOT NULL PRIMARY KEY,
    lead_id CHAR(36) NOT NULL,
    score INT NOT NULL,
    previous_score INT,
    score_change INT,
    factors JSON NOT NULL, -- Detailed breakdown of scoring factors
    insights JSON, -- AI-generated insights
    recommendations JSON, -- AI-generated recommendations
    model_version VARCHAR(50),
    date_scored DATETIME,
    INDEX idx_lead_id (lead_id),
    INDEX idx_date_scored (date_scored),
    INDEX idx_score (score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Health Score History
CREATE TABLE IF NOT EXISTS customer_health_scores (
    id CHAR(36) NOT NULL PRIMARY KEY,
    account_id CHAR(36) NOT NULL,
    score INT NOT NULL,
    previous_score INT,
    score_change INT,
    factors JSON NOT NULL,
    risk_level VARCHAR(50), -- healthy, at_risk, critical
    churn_probability DECIMAL(5,2),
    recommendations JSON,
    date_calculated DATETIME,
    INDEX idx_account_id (account_id),
    INDEX idx_date_calculated (date_calculated),
    INDEX idx_risk_level (risk_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Rate Limiting
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id CHAR(36) NOT NULL PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL, -- IP address or API key
    endpoint VARCHAR(255) NOT NULL,
    requests INT DEFAULT 1,
    window_start DATETIME,
    window_end DATETIME,
    INDEX idx_identifier_endpoint (identifier, endpoint),
    INDEX idx_window (window_start, window_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook Events
CREATE TABLE IF NOT EXISTS webhook_events (
    id CHAR(36) NOT NULL PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    webhook_url TEXT,
    response_code INT,
    response_body TEXT,
    attempts INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'pending', -- pending, success, failed
    next_retry DATETIME,
    date_created DATETIME,
    date_processed DATETIME,
    INDEX idx_status (status),
    INDEX idx_next_retry (next_retry),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create views for reporting
CREATE OR REPLACE VIEW v_lead_engagement AS
SELECT 
    l.id as lead_id,
    l.first_name,
    l.last_name,
    l.email,
    l.ai_score,
    v.visitor_id,
    v.total_visits,
    v.total_page_views,
    v.total_time_spent,
    v.engagement_score,
    v.last_visit
FROM leads l
LEFT JOIN activity_tracking_visitors v ON l.id = v.lead_id
WHERE l.deleted = 0;

CREATE OR REPLACE VIEW v_form_performance AS
SELECT 
    f.id as form_id,
    f.name as form_name,
    f.is_active,
    COUNT(DISTINCT s.id) as total_submissions,
    COUNT(DISTINCT s.lead_id) as leads_generated,
    DATE(MIN(s.date_submitted)) as first_submission,
    DATE(MAX(s.date_submitted)) as last_submission
FROM form_builder_forms f
LEFT JOIN form_builder_submissions s ON f.id = s.form_id AND s.deleted = 0
WHERE f.deleted = 0
GROUP BY f.id, f.name, f.is_active;

-- Add triggers for updated timestamps
DELIMITER $$

CREATE TRIGGER form_builder_forms_before_insert 
BEFORE INSERT ON form_builder_forms
FOR EACH ROW
BEGIN
    SET NEW.date_entered = NOW();
    SET NEW.date_modified = NOW();
END$$

CREATE TRIGGER form_builder_forms_before_update
BEFORE UPDATE ON form_builder_forms
FOR EACH ROW
BEGIN
    SET NEW.date_modified = NOW();
END$$

CREATE TRIGGER knowledge_base_articles_before_insert
BEFORE INSERT ON knowledge_base_articles
FOR EACH ROW
BEGIN
    SET NEW.date_entered = NOW();
    SET NEW.date_modified = NOW();
END$$

CREATE TRIGGER knowledge_base_articles_before_update
BEFORE UPDATE ON knowledge_base_articles
FOR EACH ROW
BEGIN
    SET NEW.date_modified = NOW();
END$$

DELIMITER ;