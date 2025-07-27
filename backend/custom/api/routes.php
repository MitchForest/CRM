<?php
function configureRoutes($router) {
    // Health check route (no auth required)
    $router->get('/health', 'Api\Controllers\HealthController::check', ['skipAuth' => true]);
    
    // Add middleware stack (simplified for MVP)
    $router->addMiddleware(new \Api\Middleware\BasicSecurityMiddleware());    // Basic security
    $router->addMiddleware(new \Api\Middleware\SimplerRateLimitMiddleware()); // Simple rate limiting
    $router->addMiddleware(new \Api\Middleware\AuthMiddleware());            // Authentication
    
    // Authentication routes
    $router->post('/auth/login', 'Api\Controllers\AuthController::login', ['skipAuth' => true]);
    $router->post('/auth/refresh', 'Api\Controllers\AuthController::refresh', ['skipAuth' => true]);
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
    $router->get('/forms/{id}', 'Api\Controllers\FormBuilderController::getForm', ['skipAuth' => true]); // Public endpoint for embed
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
    $router->post('/track/page-exit', 'Api\Controllers\ActivityTrackingController::trackPageExit', ['skipAuth' => true]); // Public endpoint
    $router->post('/track/event', 'Api\Controllers\ActivityTrackingController::trackEvent', ['skipAuth' => true]); // Public endpoint - Generic event tracking
    $router->post('/track/engagement', 'Api\Controllers\ActivityTrackingController::trackEngagement', ['skipAuth' => true]); // Public endpoint
    $router->post('/track/conversion', 'Api\Controllers\ActivityTrackingController::trackConversion', ['skipAuth' => true]); // Public endpoint
    $router->post('/track/session-end', 'Api\Controllers\ActivityTrackingController::endSession', ['skipAuth' => true]); // Public endpoint
    $router->get('/track/pixel/{tracking_id}.gif', 'Api\Controllers\ActivityTrackingController::trackingPixel', ['skipAuth' => true]); // Public endpoint
    $router->get('/analytics/visitors', 'Api\Controllers\ActivityTrackingController::getVisitorAnalytics');
    $router->get('/analytics/leads/{id}/activity', 'Api\Controllers\ActivityTrackingController::getLeadActivity');
    
    // Customer Health Scoring Routes (Phase 3)
    $router->post('/accounts/{id}/health-score', 'Api\Controllers\CustomerHealthController::calculateHealthScore');
    $router->post('/accounts/health-score-batch', 'Api\Controllers\CustomerHealthController::batchCalculateHealthScores');
    $router->get('/accounts/{id}/health-history', 'Api\Controllers\CustomerHealthController::getHealthHistory');
    $router->get('/accounts/at-risk', 'Api\Controllers\CustomerHealthController::getAtRiskAccounts');
    $router->get('/analytics/health-dashboard', 'Api\Controllers\CustomerHealthController::getHealthDashboard');
    $router->post('/admin/recalculate-health-scores', 'Api\Controllers\CustomerHealthController::recalculateAllScores');
    $router->post('/webhooks/health-check', 'Api\Controllers\CustomerHealthController::webhookHealthCheck', ['skipAuth' => true]);
    
    // Contacts Routes (Phase 5 - Unified)
    $router->get('/contacts', 'Api\Controllers\ContactsController::index');
    $router->get('/contacts/unified', 'Api\Controllers\ContactsController::getUnifiedList');
    $router->get('/contacts/{id}', 'Api\Controllers\ContactsController::show');
    $router->get('/contacts/{id}/unified', 'Api\Controllers\ContactsController::getUnifiedView');
    $router->post('/contacts', 'Api\Controllers\ContactsController::create');
    $router->put('/contacts/{id}', 'Api\Controllers\ContactsController::update');
    $router->delete('/contacts/{id}', 'Api\Controllers\ContactsController::delete');
    
    // Opportunities Routes
    $router->get('/opportunities', 'Api\Controllers\OpportunitiesController::index');
    $router->get('/opportunities/{id}', 'Api\Controllers\OpportunitiesController::show');
    $router->post('/opportunities', 'Api\Controllers\OpportunitiesController::create');
    $router->put('/opportunities/{id}', 'Api\Controllers\OpportunitiesController::update');
    $router->delete('/opportunities/{id}', 'Api\Controllers\OpportunitiesController::delete');
    
    // Cases (Support Tickets) Routes
    $router->get('/cases', 'Api\Controllers\CasesController::index');
    $router->get('/cases/{id}', 'Api\Controllers\CasesController::show');
    $router->post('/cases', 'Api\Controllers\CasesController::create');
    $router->put('/cases/{id}', 'Api\Controllers\CasesController::update');
    $router->delete('/cases/{id}', 'Api\Controllers\CasesController::delete');
    
    // Activities Routes
    $router->get('/activities', 'Api\Controllers\ActivitiesController::index');
    $router->get('/activities/{id}', 'Api\Controllers\ActivitiesController::show');
    $router->post('/activities', 'Api\Controllers\ActivitiesController::create');
    $router->put('/activities/{id}', 'Api\Controllers\ActivitiesController::update');
    $router->delete('/activities/{id}', 'Api\Controllers\ActivitiesController::delete');
    
    // User/Profile Routes
    $router->get('/auth/me', 'Api\Controllers\AuthController::getCurrentUser');
    $router->put('/auth/profile', 'Api\Controllers\AuthController::updateProfile');
    
    // AI Chat Routes (Phase 5 - Enhanced)
    $router->post('/ai/chat/start', 'Api\Controllers\AIController::startConversation');
    $router->post('/ai/chat/message', 'Api\Controllers\AIController::sendMessage');
    $router->get('/ai/chat/history/{contact_id}', 'Api\Controllers\AIController::getChatHistory');
    $router->post('/ai/chat/create-ticket', 'Api\Controllers\AIController::createTicketFromChat');
    $router->post('/ai/chat/schedule-demo', 'Api\Controllers\AIController::scheduleDemoFromChat');
    
    // Form Builder Routes (Phase 5 - Enhanced)
    $router->get('/forms/active', 'Api\Controllers\FormBuilderController::getActiveForms');
    $router->get('/forms/{id}/embed', 'Api\Controllers\FormBuilderController::getEmbedCode');
    $router->post('/forms/{id}/submissions/{submission_id}/convert', 'Api\Controllers\FormBuilderController::convertSubmissionToLead');
    
    // Knowledge Base Routes (Phase 5 - Enhanced) 
    $router->get('/kb/categories', 'Api\Controllers\KnowledgeBaseController::getCategories', ['skipAuth' => true]);
    $router->get('/kb/articles', 'Api\Controllers\KnowledgeBaseController::getArticles', ['skipAuth' => true]);
    $router->get('/kb/articles/{id}', 'Api\Controllers\KnowledgeBaseController::getArticle', ['skipAuth' => true]);
    $router->get('/kb/search', 'Api\Controllers\KnowledgeBaseController::searchArticles', ['skipAuth' => true]);
    $router->post('/kb/articles/{id}/helpful', 'Api\Controllers\KnowledgeBaseController::markHelpful', ['skipAuth' => true]);
    
    // Public Lead Form Submission
    $router->post('/public/lead-form', 'Api\Controllers\LeadsController::submitPublicForm', ['skipAuth' => true]);
    
    // Analytics Routes
    $router->get('/analytics/overview', 'Api\Controllers\AnalyticsController::getOverview');
    $router->get('/analytics/conversion-funnel', 'Api\Controllers\AnalyticsController::getConversionFunnel');
    $router->get('/analytics/lead-sources', 'Api\Controllers\AnalyticsController::getLeadSources');
}