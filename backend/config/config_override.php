<?php
// Headless mode configuration for B2C CRM

// API-specific settings
$sugar_config['site_url'] = 'http://localhost:8080';
$sugar_config['api_enabled'] = true;
$sugar_config['rest_enabled'] = true;

// Security settings for API
$sugar_config['api_cors_enabled'] = true;
$sugar_config['api_cors_allowed_origins'] = ['http://localhost:5173', 'http://localhost:3000'];
$sugar_config['api_cors_allowed_methods'] = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
$sugar_config['api_cors_allowed_headers'] = ['Content-Type', 'Authorization', 'X-Requested-With'];

// Performance optimizations
$sugar_config['disable_count_query'] = true;
$sugar_config['save_query'] = 'populate_only';
$sugar_config['list_max_entries_per_page'] = 50;

// Cache settings
$sugar_config['external_cache_disabled'] = false;

// Session handling for API
$sugar_config['session_gc_maxlifetime'] = 86400; // 24 hours

// v8 API Configuration
$sugar_config['api']['v8']['enabled'] = true;
$sugar_config['api']['v8']['jwt']['secret'] = 'your-secret-key-change-in-production';
$sugar_config['api']['v8']['jwt']['expire_after'] = 3600; // 1 hour
$sugar_config['api']['v8']['jwt']['refresh_expire_after'] = 604800; // 7 days

// CORS Configuration for v8 API
$sugar_config['api']['v8']['cors']['enabled'] = true;
$sugar_config['api']['v8']['cors']['origin'] = ['http://localhost:3000', 'http://localhost:5173'];
$sugar_config['api']['v8']['cors']['methods'] = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
$sugar_config['api']['v8']['cors']['headers'] = ['Content-Type', 'Authorization', 'X-Requested-With'];
