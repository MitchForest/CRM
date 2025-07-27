<?php

use Slim\Routing\RouteCollectorProxy;
use App\Http\Controllers\FormBuilderController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\ActivityTrackingController;
use App\Http\Controllers\CustomerHealthController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\AIController;

// Admin Routes (all require authentication)
return function (RouteCollectorProxy $api) {
    
    $api->group('/admin', function (RouteCollectorProxy $admin) {
        
        // Form Builder Routes
        $admin->group('/forms', function (RouteCollectorProxy $forms) {
            $forms->get('', [FormBuilderController::class, 'getForms']);
            $forms->post('', [FormBuilderController::class, 'createForm']);
            $forms->get('/{id}', [FormBuilderController::class, 'getForm']);
            $forms->put('/{id}', [FormBuilderController::class, 'updateForm']);
            $forms->delete('/{id}', [FormBuilderController::class, 'deleteForm']);
            $forms->get('/{id}/submissions', [FormBuilderController::class, 'getSubmissions']);
            $forms->get('/{id}/analytics', [FormBuilderController::class, 'getFormAnalytics']);
            $forms->post('/{id}/duplicate', [FormBuilderController::class, 'duplicateForm']);
        });
        
        // Knowledge Base Management
        $admin->group('/knowledge-base', function (RouteCollectorProxy $kb) {
            $kb->get('/articles', [KnowledgeBaseController::class, 'getArticles']);
            $kb->post('/articles', [KnowledgeBaseController::class, 'createArticle']);
            $kb->get('/articles/{id}', [KnowledgeBaseController::class, 'getArticle']);
            $kb->put('/articles/{id}', [KnowledgeBaseController::class, 'updateArticle']);
            $kb->delete('/articles/{id}', [KnowledgeBaseController::class, 'deleteArticle']);
            $kb->post('/articles/{id}/publish', [KnowledgeBaseController::class, 'publishArticle']);
            $kb->post('/articles/{id}/unpublish', [KnowledgeBaseController::class, 'unpublishArticle']);
            $kb->get('/categories', [KnowledgeBaseController::class, 'getCategories']);
            $kb->post('/categories', [KnowledgeBaseController::class, 'createCategory']);
            $kb->put('/categories/{id}', [KnowledgeBaseController::class, 'updateCategory']);
            $kb->delete('/categories/{id}', [KnowledgeBaseController::class, 'deleteCategory']);
        });
        
        // Activity Tracking Configuration
        $admin->group('/tracking', function (RouteCollectorProxy $tracking) {
            $tracking->get('/config', [ActivityTrackingController::class, 'getConfig']);
            $tracking->put('/config', [ActivityTrackingController::class, 'updateConfig']);
            $tracking->get('/sessions', [ActivityTrackingController::class, 'getSessions']);
            $tracking->get('/sessions/{id}', [ActivityTrackingController::class, 'getSession']);
            $tracking->get('/page-views', [ActivityTrackingController::class, 'getPageViews']);
            $tracking->get('/visitors', [ActivityTrackingController::class, 'getVisitors']);
            $tracking->get('/analytics', [ActivityTrackingController::class, 'getTrackingAnalytics']);
        });
        
        // Customer Health Scoring
        $admin->group('/health-scoring', function (RouteCollectorProxy $health) {
            $health->get('/rules', [CustomerHealthController::class, 'getRules']);
            $health->post('/rules', [CustomerHealthController::class, 'createRule']);
            $health->put('/rules/{id}', [CustomerHealthController::class, 'updateRule']);
            $health->delete('/rules/{id}', [CustomerHealthController::class, 'deleteRule']);
            $health->post('/calculate', [CustomerHealthController::class, 'calculateScores']);
            $health->get('/scores', [CustomerHealthController::class, 'getScores']);
            $health->get('/trends', [CustomerHealthController::class, 'getHealthTrends']);
        });
        
        // Email Templates
        $admin->group('/emails', function (RouteCollectorProxy $emails) {
            $emails->get('/templates', [EmailController::class, 'getTemplates']);
            $emails->post('/templates', [EmailController::class, 'createTemplate']);
            $emails->put('/templates/{id}', [EmailController::class, 'updateTemplate']);
            $emails->delete('/templates/{id}', [EmailController::class, 'deleteTemplate']);
            $emails->post('/test', [EmailController::class, 'sendTestEmail']);
        });
        
        // Document Management
        $admin->group('/documents', function (RouteCollectorProxy $docs) {
            $docs->post('/upload', [DocumentController::class, 'upload']);
            $docs->get('/{id}', [DocumentController::class, 'getDocument']);
            $docs->delete('/{id}', [DocumentController::class, 'deleteDocument']);
            $docs->get('/{id}/download', [DocumentController::class, 'downloadDocument']);
        });
        
        // System Settings
        $admin->group('/settings', function (RouteCollectorProxy $settings) {
            $settings->get('/ai', [AIController::class, 'getSettings']);
            $settings->put('/ai', [AIController::class, 'updateSettings']);
            $settings->get('/tracking', [ActivityTrackingController::class, 'getSettings']);
            $settings->put('/tracking', [ActivityTrackingController::class, 'updateSettings']);
        });
    });
};