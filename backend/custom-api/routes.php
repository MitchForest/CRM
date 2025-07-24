<?php
function configureRoutes($router) {
    // Health check route (no auth required)
    $router->get('/health', 'Api\Controllers\HealthController::check', ['skipAuth' => true]);
    
    // Add middleware
    $router->addMiddleware(new \Api\Middleware\AuthMiddleware());
    
    // Authentication routes
    $router->post('/auth/login', 'Api\Controllers\AuthController::login');
    $router->post('/auth/refresh', 'Api\Controllers\AuthController::refresh');
    $router->post('/auth/logout', 'Api\Controllers\AuthController::logout');
    
    // Leads routes
    $router->get('/leads', 'Api\Controllers\LeadsController::index');
    $router->get('/leads/{id}', 'Api\Controllers\LeadsController::show');
    $router->post('/leads', 'Api\Controllers\LeadsController::create');
    $router->put('/leads/{id}', 'Api\Controllers\LeadsController::update');
    $router->patch('/leads/{id}', 'Api\Controllers\LeadsController::patch');
    $router->delete('/leads/{id}', 'Api\Controllers\LeadsController::delete');
    
    // Dashboard routes (Phase 2)
    $router->get('/dashboard/metrics', 'Api\Controllers\DashboardController::getMetrics');
    $router->get('/dashboard/pipeline', 'Api\Controllers\DashboardController::getPipelineData');
    $router->get('/dashboard/activities', 'Api\Controllers\DashboardController::getActivityMetrics');
    $router->get('/dashboard/cases', 'Api\Controllers\DashboardController::getCaseMetrics');
    
    // Email routes (Phase 2)
    $router->get('/emails/{id}/view', 'Api\Controllers\EmailController::viewEmail');
    
    // Document routes (Phase 2)
    $router->get('/documents/{id}/download', 'Api\Controllers\DocumentController::downloadDocument');
    
    // AI Routes (Phase 3)
    $router->post('/leads/{id}/ai-score', 'Api\Controllers\AIController::scoreLead');
    $router->post('/leads/ai-score-batch', 'Api\Controllers\AIController::scoreLeadsBatch');
    $router->get('/leads/{id}/score-history', 'Api\Controllers\AIController::getScoreHistory');
    $router->post('/ai/chat', 'Api\Controllers\AIController::chat');
    $router->get('/ai/chat/{conversation_id}', 'Api\Controllers\AIController::getConversation');
    
    // Form Builder Routes (Phase 3)
    $router->get('/forms', 'Api\Controllers\FormBuilderController::getForms');
    $router->get('/forms/{id}', 'Api\Controllers\FormBuilderController::getForm');
    $router->post('/forms', 'Api\Controllers\FormBuilderController::createForm');
    $router->put('/forms/{id}', 'Api\Controllers\FormBuilderController::updateForm');
    $router->delete('/forms/{id}', 'Api\Controllers\FormBuilderController::deleteForm');
    $router->post('/forms/{id}/submit', 'Api\Controllers\FormBuilderController::submitForm', ['skipAuth' => true]); // Public endpoint
    $router->get('/forms/{id}/submissions', 'Api\Controllers\FormBuilderController::getSubmissions');
    
    // Knowledge Base Routes (Phase 3)
    $router->get('/knowledge-base/articles', 'Api\Controllers\KnowledgeBaseController::getArticles');
    $router->get('/knowledge-base/articles/{id}', 'Api\Controllers\KnowledgeBaseController::getArticle');
    $router->get('/knowledge-base/search', 'Api\Controllers\KnowledgeBaseController::searchArticles');
    $router->post('/knowledge-base/articles', 'Api\Controllers\KnowledgeBaseController::createArticle');
    $router->put('/knowledge-base/articles/{id}', 'Api\Controllers\KnowledgeBaseController::updateArticle');
    $router->delete('/knowledge-base/articles/{id}', 'Api\Controllers\KnowledgeBaseController::deleteArticle');
    $router->post('/knowledge-base/articles/{id}/feedback', 'Api\Controllers\KnowledgeBaseController::submitFeedback', ['skipAuth' => true]); // Public endpoint
    $router->get('/knowledge-base/categories', 'Api\Controllers\KnowledgeBaseController::getCategories');
    
    // Activity Tracking Routes (Phase 3)
    $router->post('/track/pageview', 'Api\Controllers\ActivityTrackingController::trackPageView', ['skipAuth' => true]); // Public endpoint
    $router->post('/track/engagement', 'Api\Controllers\ActivityTrackingController::trackEngagement', ['skipAuth' => true]); // Public endpoint
    $router->post('/track/conversion', 'Api\Controllers\ActivityTrackingController::trackConversion', ['skipAuth' => true]); // Public endpoint
    $router->post('/track/session-end', 'Api\Controllers\ActivityTrackingController::endSession', ['skipAuth' => true]); // Public endpoint
    $router->get('/track/pixel/{tracking_id}.gif', 'Api\Controllers\ActivityTrackingController::trackingPixel', ['skipAuth' => true]); // Public endpoint
    $router->get('/analytics/visitors', 'Api\Controllers\ActivityTrackingController::getVisitorAnalytics');
    $router->get('/analytics/leads/{id}/activity', 'Api\Controllers\ActivityTrackingController::getLeadActivity');
}