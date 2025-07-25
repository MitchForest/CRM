<?php
/**
 * Phase 3 - AI Configuration
 * This file contains all AI-related configuration settings
 */

// Load environment variables (you'll need to add a .env loader)
$env = function($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
};

// AI Configuration
$ai_config = [
    'openai' => [
        'api_key' => $env('OPENAI_API_KEY', ''),
        'organization' => $env('OPENAI_ORG_ID', null),
        'model' => [
            'chat' => $env('OPENAI_MODEL_CHAT', 'gpt-4-turbo-preview'),
            'completion' => $env('OPENAI_MODEL_COMPLETION', 'gpt-4-turbo-preview'),
            'embedding' => $env('OPENAI_MODEL_EMBEDDING', 'text-embedding-ada-002'),
        ],
        'max_tokens' => [
            'chat' => (int)$env('OPENAI_MAX_TOKENS_CHAT', 1000),
            'completion' => (int)$env('OPENAI_MAX_TOKENS_COMPLETION', 2000),
        ],
        'temperature' => (float)$env('OPENAI_TEMPERATURE', 0.7),
        'timeout' => 30, // seconds
        'retry' => [
            'max_attempts' => 3,
            'delay' => 1000, // milliseconds
        ],
    ],
    'lead_scoring' => [
        'enabled' => filter_var($env('FEATURE_AI_LEAD_SCORING', true), FILTER_VALIDATE_BOOLEAN),
        'weights' => [
            'company_size' => (float)$env('AI_SCORE_WEIGHT_COMPANY_SIZE', 0.20),
            'industry_match' => (float)$env('AI_SCORE_WEIGHT_INDUSTRY_MATCH', 0.15),
            'behavior_score' => (float)$env('AI_SCORE_WEIGHT_BEHAVIOR', 0.25),
            'engagement' => (float)$env('AI_SCORE_WEIGHT_ENGAGEMENT', 0.20),
            'budget_signals' => (float)$env('AI_SCORE_WEIGHT_BUDGET', 0.20),
        ],
        'thresholds' => [
            'hot' => (int)$env('AI_SCORE_THRESHOLD_HOT', 80),
            'warm' => (int)$env('AI_SCORE_THRESHOLD_WARM', 60),
            'cool' => (int)$env('AI_SCORE_THRESHOLD_COOL', 40),
        ],
        'factors' => [
            'company_size_scoring' => [
                'enterprise' => ['min' => 1000, 'score' => 20],
                'mid_market' => ['min' => 100, 'max' => 999, 'score' => 15],
                'small_business' => ['min' => 10, 'max' => 99, 'score' => 10],
                'startup' => ['max' => 9, 'score' => 5],
            ],
            'industry_priorities' => [
                'technology' => 20,
                'finance' => 18,
                'healthcare' => 16,
                'manufacturing' => 14,
                'retail' => 12,
                'other' => 10,
            ],
            'engagement_actions' => [
                'demo_request' => 25,
                'pricing_view' => 20,
                'whitepaper_download' => 15,
                'webinar_attendance' => 15,
                'email_open' => 5,
                'website_visit' => 3,
            ],
        ],
    ],
    'activity_tracking' => [
        'enabled' => filter_var($env('FEATURE_ACTIVITY_TRACKING', true), FILTER_VALIDATE_BOOLEAN),
        'session_timeout' => (int)$env('TRACKING_SESSION_TIMEOUT', 1800), // 30 minutes
        'high_value_pages' => explode(',', $env('TRACKING_HIGH_VALUE_PAGES', '/pricing,/demo,/contact,/trial')),
        'engagement_thresholds' => [
            'high' => [
                'pages' => (int)$env('TRACKING_ENGAGEMENT_HIGH_PAGES', 5),
                'time' => (int)$env('TRACKING_ENGAGEMENT_HIGH_TIME', 300)
            ],
            'medium' => [
                'pages' => (int)$env('TRACKING_ENGAGEMENT_MEDIUM_PAGES', 3),
                'time' => (int)$env('TRACKING_ENGAGEMENT_MEDIUM_TIME', 120)
            ],
        ],
        'tracking_cookie_name' => 'crm_visitor_id',
        'tracking_cookie_lifetime' => 365 * 24 * 60 * 60, // 1 year
    ],
    'knowledge_base' => [
        'enabled' => filter_var($env('FEATURE_KNOWLEDGE_BASE', true), FILTER_VALIDATE_BOOLEAN),
        'search_limit' => (int)$env('KB_SEARCH_LIMIT', 10),
        'embedding_cache_ttl' => (int)$env('KB_EMBEDDING_CACHE_TTL', 86400), // 24 hours
        'public_access' => filter_var($env('KB_PUBLIC_ACCESS', true), FILTER_VALIDATE_BOOLEAN),
        'similarity_threshold' => 0.75,
        'categories' => [
            'getting-started',
            'features',
            'integrations',
            'troubleshooting',
            'api-documentation',
            'best-practices',
        ],
    ],
    'chatbot' => [
        'enabled' => filter_var($env('FEATURE_AI_CHATBOT', true), FILTER_VALIDATE_BOOLEAN),
        'widget' => [
            'position' => $env('CHAT_WIDGET_POSITION', 'bottom-right'),
            'primary_color' => $env('CHAT_WIDGET_PRIMARY_COLOR', '#3b82f6'),
            'greeting' => $env('CHAT_WIDGET_GREETING', 'Hi! How can we help you today?'),
            'offline_message' => $env('CHAT_WIDGET_OFFLINE_MESSAGE', 'We\'re currently offline. Please leave a message!'),
        ],
        'conversation' => [
            'context_messages' => 10, // Number of previous messages to include for context
            'session_timeout' => 3600, // 1 hour
            'handoff_keywords' => ['human', 'agent', 'support', 'help', 'speak to someone'],
        ],
        'lead_capture' => [
            'enabled' => true,
            'required_fields' => ['email'],
            'optional_fields' => ['name', 'company', 'phone'],
        ],
    ],
    'form_builder' => [
        'enabled' => filter_var($env('FEATURE_FORM_BUILDER', true), FILTER_VALIDATE_BOOLEAN),
        'max_fields' => (int)$env('FORM_BUILDER_MAX_FIELDS', 50),
        'allowed_domains' => explode(',', $env('FORM_BUILDER_ALLOWED_DOMAINS', 'localhost,yourdomain.com')),
        'spam_protection' => filter_var($env('FORM_BUILDER_SPAM_PROTECTION', true), FILTER_VALIDATE_BOOLEAN),
        'field_types' => [
            'text' => ['label' => 'Text Input', 'icon' => 'text'],
            'email' => ['label' => 'Email', 'icon' => 'email'],
            'tel' => ['label' => 'Phone', 'icon' => 'phone'],
            'select' => ['label' => 'Dropdown', 'icon' => 'dropdown'],
            'checkbox' => ['label' => 'Checkbox', 'icon' => 'checkbox'],
            'radio' => ['label' => 'Radio Button', 'icon' => 'radio'],
            'textarea' => ['label' => 'Text Area', 'icon' => 'textarea'],
            'hidden' => ['label' => 'Hidden Field', 'icon' => 'hidden'],
        ],
    ],
    'health_scoring' => [
        'enabled' => filter_var($env('FEATURE_HEALTH_SCORING', true), FILTER_VALIDATE_BOOLEAN),
        'factors' => [
            'support_tickets' => -0.3, // Negative weight
            'last_activity_days' => -0.2, // Negative weight (more days = lower score)
            'mrr_growth' => 0.25,
            'user_adoption' => 0.15,
            'feature_usage' => 0.10,
        ],
        'thresholds' => [
            'healthy' => 80,
            'at_risk' => 60,
            'critical' => 40,
        ],
        'calculation_frequency' => 'daily', // daily, weekly, monthly
    ],
    'security' => [
        'rate_limiting' => [
            'enabled' => true,
            'per_minute' => (int)$env('API_RATE_LIMIT_PER_MINUTE', 60),
            'per_hour' => (int)$env('API_RATE_LIMIT_PER_HOUR', 1000),
        ],
        'allowed_origins' => explode(',', $env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
        'webhook_signature_secret' => $env('WEBHOOK_SIGNATURE_SECRET', 'your-webhook-secret'),
    ],
    'webhooks' => [
        'lead_created' => $env('WEBHOOK_LEAD_CREATED', ''),
        'lead_scored' => $env('WEBHOOK_LEAD_SCORED', ''),
        'form_submitted' => $env('WEBHOOK_FORM_SUBMITTED', ''),
        'chat_started' => $env('WEBHOOK_CHAT_STARTED', ''),
    ],
    'cache' => [
        'driver' => 'redis',
        'prefix' => 'crm_ai_',
        'ttl' => [
            'embeddings' => 86400, // 24 hours
            'ai_responses' => 3600, // 1 hour
            'scores' => 7200, // 2 hours
        ],
    ],
];

// Load into global config
global $sugar_config;
if (!isset($sugar_config['ai'])) {
    $sugar_config['ai'] = [];
}
$sugar_config['ai'] = array_merge($sugar_config['ai'], $ai_config);

// Export for use in other files
return $ai_config;