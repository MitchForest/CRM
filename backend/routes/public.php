<?php

use Slim\Routing\RouteCollectorProxy;
use App\Http\Controllers\FormBuilderController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\ActivityTrackingController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\LeadsController;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\SchemaController;

// Public Routes (no authentication required)
return function (RouteCollectorProxy $api) {
    
    $api->group('/public', function (RouteCollectorProxy $public) {
        
        // Public Form Endpoints (for embedding on websites)
        $public->group('/forms', function (RouteCollectorProxy $forms) {
            // Get form for embedding
            $forms->get('/{id}', [FormBuilderController::class, 'getPublicForm'])
                ->setName('public.forms.get');
            
            // Submit form data
            $forms->post('/{id}/submit', [FormBuilderController::class, 'submitForm'])
                ->setName('public.forms.submit');
        });
        
        // Public Knowledge Base (for website visitors)
        $public->group('/kb', function (RouteCollectorProxy $kb) {
            // Search articles
            $kb->get('/search', [KnowledgeBaseController::class, 'searchPublic'])
                ->setName('public.kb.search');
            
            // Get published articles
            $kb->get('/articles', [KnowledgeBaseController::class, 'getPublicArticles'])
                ->setName('public.kb.articles');
            
            // Get single article
            $kb->get('/articles/{slug}', [KnowledgeBaseController::class, 'getPublicArticle'])
                ->setName('public.kb.article');
            
            // Get categories
            $kb->get('/categories', [KnowledgeBaseController::class, 'getPublicCategories'])
                ->setName('public.kb.categories');
            
            // Article feedback
            $kb->post('/articles/{id}/feedback', [KnowledgeBaseController::class, 'submitFeedback'])
                ->setName('public.kb.feedback');
        });
        
        // Activity Tracking Endpoints
        $public->group('/track', function (RouteCollectorProxy $track) {
            // Track page view
            $track->post('/pageview', [ActivityTrackingController::class, 'trackPageView'])
                ->setName('public.track.pageview');
            
            // Track event
            $track->post('/event', [ActivityTrackingController::class, 'trackEvent'])
                ->setName('public.track.event');
            
            // Start session
            $track->post('/session/start', [ActivityTrackingController::class, 'startSession'])
                ->setName('public.track.session.start');
            
            // End session
            $track->post('/session/end', [ActivityTrackingController::class, 'endSession'])
                ->setName('public.track.session.end');
            
            // Track page exit
            $track->post('/page-exit', [ActivityTrackingController::class, 'trackPageExit'])
                ->setName('public.track.page-exit');
            
            // Track conversion
            $track->post('/conversion', [ActivityTrackingController::class, 'trackConversion'])
                ->setName('public.track.conversion');
        });
        
        // Public Chat Endpoints
        $public->group('/chat', function (RouteCollectorProxy $chat) {
            // Start chat conversation
            $chat->post('/start', [AIController::class, 'startPublicChat'])
                ->setName('public.chat.start');
            
            // Send message
            $chat->post('/message', [AIController::class, 'sendPublicMessage'])
                ->setName('public.chat.message');
            
            // Get conversation (with session token)
            $chat->get('/conversation/{session_id}', [AIController::class, 'getPublicConversation'])
                ->setName('public.chat.conversation');
        });
        
        // Lead capture from various sources
        $public->post('/capture/lead', [LeadsController::class, 'capturePublicLead'])
            ->setName('public.capture.lead');
        
        // Demo request
        $public->post('/demo-request', [LeadsController::class, 'requestDemo'])
            ->setName('public.demo-request');
        
        // Newsletter signup
        $public->post('/newsletter/subscribe', [ContactsController::class, 'subscribeNewsletter'])
            ->setName('public.newsletter.subscribe');
        
        // Marketing site assets
        $public->get('/tracking-script.js', [ActivityTrackingController::class, 'getTrackingScript'])
            ->setName('public.tracking-script');
        
        $public->get('/chat-widget.js', [AIController::class, 'getChatWidget'])
            ->setName('public.chat-widget');
        
        $public->get('/forms-embed.js', [FormBuilderController::class, 'getEmbedScript'])
            ->setName('public.forms-embed');
            
        // Public analytics endpoints for demo (normally these would be protected)
        $public->group('/analytics', function (RouteCollectorProxy $analytics) {
            $analytics->get('/visitors', [ActivityTrackingController::class, 'getVisitors'])
                ->setName('public.analytics.visitors');
            $analytics->get('/visitor-metrics', [ActivityTrackingController::class, 'getVisitorMetrics'])
                ->setName('public.analytics.visitor-metrics');
        });
    });
    
    // Schema API endpoints (public for frontend type generation)
    $api->group('/schema', function (RouteCollectorProxy $schema) {
        // Get complete database schema
        $schema->get('', [SchemaController::class, 'getFullSchema'])
            ->setName('schema.full');
        
        // Get validation rules
        $schema->get('/validation', [SchemaController::class, 'getValidationRules'])
            ->setName('schema.validation');
        
        // Get enum values
        $schema->get('/enums', [SchemaController::class, 'getEnumValues'])
            ->setName('schema.enums');
        
        // Get OpenAPI specification
        $schema->get('/openapi', [SchemaController::class, 'getOpenAPISpec'])
            ->setName('schema.openapi');
        
        // Get TypeScript types
        $schema->get('/typescript', [SchemaController::class, 'getTypeScriptTypes'])
            ->setName('schema.typescript');
        
        // Get field mapping documentation
        $schema->get('/field-mapping', [SchemaController::class, 'getFieldMapping'])
            ->setName('schema.field-mapping');
    });
    
    // OpenAPI documentation routes (frontend expects these)
    $api->group('/api-docs', function (RouteCollectorProxy $docs) {
        // Get OpenAPI JSON specification
        $docs->get('/openapi.json', function ($request, $response) {
            // Check if generated file exists
            $openApiPath = __DIR__ . '/../openapi.yaml';
            if (file_exists($openApiPath)) {
                $yaml = file_get_contents($openApiPath);
                $data = \Symfony\Component\Yaml\Yaml::parse($yaml);
                $response->getBody()->write(json_encode($data));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            // Otherwise generate dynamically
            $openapi = \OpenApi\Generator::scan([__DIR__ . '/../app/Http/Controllers']);
            $response->getBody()->write($openapi->toJson());
            return $response->withHeader('Content-Type', 'application/json');
        })->setName('api-docs.openapi');
        
        // Swagger UI (optional but useful)
        $docs->get('', function ($request, $response) {
            $html = file_get_contents(__DIR__ . '/../public/api-docs/index.html');
            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'text/html');
        })->setName('api-docs.ui');
    });
};