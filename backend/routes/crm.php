<?php

use Slim\Routing\RouteCollectorProxy;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\OpportunitiesController;
use App\Http\Controllers\CasesController;
use App\Http\Controllers\ActivitiesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AIController;

// CRM Routes (all require authentication)
return function (RouteCollectorProxy $api) {
    
    $api->group('/crm', function (RouteCollectorProxy $crm) {
        
        // Dashboard Routes
        $crm->get('/dashboard/metrics', [DashboardController::class, 'getMetrics']);
        $crm->get('/dashboard/pipeline', [DashboardController::class, 'getPipelineData']);
        $crm->get('/dashboard/activities', [DashboardController::class, 'getActivityMetrics']);
        $crm->get('/dashboard/cases', [DashboardController::class, 'getCaseMetrics']);
        
        // Leads Routes
        $crm->group('/leads', function (RouteCollectorProxy $leads) {
            $leads->get('', [LeadsController::class, 'index']);
            $leads->post('', [LeadsController::class, 'create']);
            $leads->get('/{id}', [LeadsController::class, 'show']);
            $leads->put('/{id}', [LeadsController::class, 'update']);
            $leads->patch('/{id}', [LeadsController::class, 'patch']);
            $leads->delete('/{id}', [LeadsController::class, 'delete']);
            
            // Lead AI features
            $leads->post('/{id}/ai-score', [AIController::class, 'scoreLead']);
            $leads->get('/{id}/score-history', [AIController::class, 'getScoreHistory']);
            $leads->get('/{id}/timeline', [LeadsController::class, 'getTimeline']);
            $leads->post('/{id}/convert', [LeadsController::class, 'convert']);
        });
        
        // Contacts Routes
        $crm->group('/contacts', function (RouteCollectorProxy $contacts) {
            $contacts->get('', [ContactsController::class, 'index']);
            $contacts->post('', [ContactsController::class, 'create']);
            $contacts->get('/{id}', [ContactsController::class, 'show']);
            $contacts->put('/{id}', [ContactsController::class, 'update']);
            $contacts->delete('/{id}', [ContactsController::class, 'delete']);
            $contacts->get('/{id}/unified-view', [ContactsController::class, 'unifiedView']);
            $contacts->get('/{id}/health-score', [ContactsController::class, 'getHealthScore']);
        });
        
        // Opportunities Routes
        $crm->group('/opportunities', function (RouteCollectorProxy $opportunities) {
            $opportunities->get('', [OpportunitiesController::class, 'index']);
            $opportunities->get('/pipeline', [OpportunitiesController::class, 'pipeline']); // Moved before {id}
            $opportunities->post('', [OpportunitiesController::class, 'create']);
            $opportunities->get('/{id}', [OpportunitiesController::class, 'show']);
            $opportunities->put('/{id}', [OpportunitiesController::class, 'update']);
            $opportunities->delete('/{id}', [OpportunitiesController::class, 'delete']);
            $opportunities->post('/{id}/stage', [OpportunitiesController::class, 'updateStage']);
        });
        
        // Cases (Support Tickets) Routes
        $crm->group('/cases', function (RouteCollectorProxy $cases) {
            $cases->get('', [CasesController::class, 'index']);
            $cases->post('', [CasesController::class, 'create']);
            $cases->get('/{id}', [CasesController::class, 'show']);
            $cases->put('/{id}', [CasesController::class, 'update']);
            $cases->delete('/{id}', [CasesController::class, 'delete']);
            $cases->post('/{id}/status', [CasesController::class, 'updateStatus']);
            $cases->post('/{id}/assign', [CasesController::class, 'assign']);
        });
        
        // Activities Routes (Tasks, Calls, Meetings)
        $crm->group('/activities', function (RouteCollectorProxy $activities) {
            $activities->get('', [ActivitiesController::class, 'index']);
            $activities->get('/upcoming', [ActivitiesController::class, 'upcoming']);
            $activities->get('/overdue', [ActivitiesController::class, 'overdue']);
            $activities->post('/tasks', [ActivitiesController::class, 'createTask']);
            $activities->post('/calls', [ActivitiesController::class, 'createCall']);
            $activities->post('/meetings', [ActivitiesController::class, 'createMeeting']);
            $activities->put('/{type}/{id}', [ActivitiesController::class, 'update']);
            $activities->delete('/{type}/{id}', [ActivitiesController::class, 'delete']);
        });
        
        // Analytics Routes
        $crm->group('/analytics', function (RouteCollectorProxy $analytics) {
            $analytics->get('/sales', [AnalyticsController::class, 'salesAnalytics']);
            $analytics->get('/leads', [AnalyticsController::class, 'leadAnalytics']);
            $analytics->get('/activities', [AnalyticsController::class, 'activityAnalytics']);
            $analytics->get('/conversion', [AnalyticsController::class, 'conversionAnalytics']);
            $analytics->get('/team-performance', [AnalyticsController::class, 'teamPerformance']);
        });
        
        // AI Features
        $crm->group('/ai', function (RouteCollectorProxy $ai) {
            $ai->post('/chat', [AIController::class, 'chat']);
            $ai->get('/chat/{conversation_id}', [AIController::class, 'getConversation']);
            $ai->get('/conversations', [AIController::class, 'listConversations']);
            $ai->post('/score-batch', [AIController::class, 'scoreLeadsBatch']);
            $ai->post('/insights', [AIController::class, 'generateInsights']);
        });
    });
};