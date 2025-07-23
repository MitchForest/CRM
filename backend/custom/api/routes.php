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
    
    // Contact routes
    $router->get('/contacts', 'Api\Controllers\ContactsController::list');
    $router->get('/contacts/:id', 'Api\Controllers\ContactsController::get');
    $router->post('/contacts', 'Api\Controllers\ContactsController::create');
    $router->put('/contacts/:id', 'Api\Controllers\ContactsController::update');
    $router->delete('/contacts/:id', 'Api\Controllers\ContactsController::delete');
    $router->get('/contacts/:id/activities', 'Api\Controllers\ContactsController::activities');
    
    // Lead routes
    $router->get('/leads', 'Api\Controllers\LeadsController::list');
    $router->get('/leads/:id', 'Api\Controllers\LeadsController::get');
    $router->post('/leads', 'Api\Controllers\LeadsController::create');
    $router->put('/leads/:id', 'Api\Controllers\LeadsController::update');
    $router->delete('/leads/:id', 'Api\Controllers\LeadsController::delete');
    $router->post('/leads/:id/convert', 'Api\Controllers\LeadsController::convert');
    
    // Opportunity routes
    $router->get('/opportunities', 'Api\Controllers\OpportunitiesController::list');
    $router->get('/opportunities/:id', 'Api\Controllers\OpportunitiesController::get');
    $router->post('/opportunities', 'Api\Controllers\OpportunitiesController::create');
    $router->put('/opportunities/:id', 'Api\Controllers\OpportunitiesController::update');
    $router->delete('/opportunities/:id', 'Api\Controllers\OpportunitiesController::delete');
    $router->post('/opportunities/:id/analyze', 'Api\Controllers\OpportunitiesController::analyze');
    
    // Task routes
    $router->get('/tasks', 'Api\Controllers\TasksController::list');
    $router->get('/tasks/:id', 'Api\Controllers\TasksController::get');
    $router->post('/tasks', 'Api\Controllers\TasksController::create');
    $router->put('/tasks/:id', 'Api\Controllers\TasksController::update');
    $router->delete('/tasks/:id', 'Api\Controllers\TasksController::delete');
    $router->put('/tasks/:id/complete', 'Api\Controllers\TasksController::complete');
    $router->get('/tasks/upcoming', 'Api\Controllers\TasksController::upcoming');
    $router->get('/tasks/overdue', 'Api\Controllers\TasksController::overdue');
    
    // Case (Support Ticket) routes
    $router->get('/cases', 'Api\Controllers\CasesController::list');
    $router->get('/cases/:id', 'Api\Controllers\CasesController::get');
    $router->post('/cases', 'Api\Controllers\CasesController::create');
    $router->put('/cases/:id', 'Api\Controllers\CasesController::update');
    $router->delete('/cases/:id', 'Api\Controllers\CasesController::delete');
    $router->post('/cases/:id/updates', 'Api\Controllers\CasesController::addUpdate');
    
    // Quote routes
    $router->get('/quotes', 'Api\Controllers\QuotesController::list');
    $router->get('/quotes/:id', 'Api\Controllers\QuotesController::get');
    $router->post('/quotes', 'Api\Controllers\QuotesController::create');
    $router->put('/quotes/:id', 'Api\Controllers\QuotesController::update');
    $router->delete('/quotes/:id', 'Api\Controllers\QuotesController::delete');
    $router->post('/quotes/:id/send', 'Api\Controllers\QuotesController::send');
    $router->post('/quotes/:id/convert-to-invoice', 'Api\Controllers\QuotesController::convertToInvoice');
    
    // Email routes
    $router->get('/emails', 'Api\Controllers\EmailsController::list');
    $router->get('/emails/:id', 'Api\Controllers\EmailsController::get');
    $router->post('/emails', 'Api\Controllers\EmailsController::create');
    $router->put('/emails/:id', 'Api\Controllers\EmailsController::update');
    $router->delete('/emails/:id', 'Api\Controllers\EmailsController::delete');
    $router->post('/emails/:id/send', 'Api\Controllers\EmailsController::send');
    $router->post('/emails/:id/reply', 'Api\Controllers\EmailsController::reply');
    $router->post('/emails/:id/forward', 'Api\Controllers\EmailsController::forward');
    $router->get('/emails/inbox', 'Api\Controllers\EmailsController::inbox');
    $router->get('/emails/sent', 'Api\Controllers\EmailsController::sent');
    $router->get('/emails/drafts', 'Api\Controllers\EmailsController::drafts');
    
    // Call routes
    $router->get('/calls', 'Api\Controllers\CallsController::list');
    $router->get('/calls/:id', 'Api\Controllers\CallsController::get');
    $router->post('/calls', 'Api\Controllers\CallsController::create');
    $router->put('/calls/:id', 'Api\Controllers\CallsController::update');
    $router->delete('/calls/:id', 'Api\Controllers\CallsController::delete');
    $router->put('/calls/:id/hold', 'Api\Controllers\CallsController::hold');
    $router->put('/calls/:id/cancel', 'Api\Controllers\CallsController::cancel');
    $router->get('/calls/upcoming', 'Api\Controllers\CallsController::upcoming');
    $router->get('/calls/today', 'Api\Controllers\CallsController::today');
    $router->post('/calls/recurring', 'Api\Controllers\CallsController::createRecurring');
    
    // Meeting routes
    $router->get('/meetings', 'Api\Controllers\MeetingsController::list');
    $router->get('/meetings/:id', 'Api\Controllers\MeetingsController::get');
    $router->post('/meetings', 'Api\Controllers\MeetingsController::create');
    $router->put('/meetings/:id', 'Api\Controllers\MeetingsController::update');
    $router->delete('/meetings/:id', 'Api\Controllers\MeetingsController::delete');
    $router->put('/meetings/:id/hold', 'Api\Controllers\MeetingsController::hold');
    $router->put('/meetings/:id/cancel', 'Api\Controllers\MeetingsController::cancel');
    $router->put('/meetings/:id/invitee-status', 'Api\Controllers\MeetingsController::updateInviteeStatus');
    $router->get('/meetings/upcoming', 'Api\Controllers\MeetingsController::upcoming');
    $router->get('/meetings/today', 'Api\Controllers\MeetingsController::today');
    $router->post('/meetings/from-template/:templateId', 'Api\Controllers\MeetingsController::createFromTemplate');
    
    // Note routes
    $router->get('/notes', 'Api\Controllers\NotesController::list');
    $router->get('/notes/:id', 'Api\Controllers\NotesController::get');
    $router->post('/notes', 'Api\Controllers\NotesController::create');
    $router->put('/notes/:id', 'Api\Controllers\NotesController::update');
    $router->delete('/notes/:id', 'Api\Controllers\NotesController::delete');
    $router->get('/notes/:id/download', 'Api\Controllers\NotesController::download');
    $router->put('/notes/:id/pin', 'Api\Controllers\NotesController::pin');
    $router->get('/notes/search', 'Api\Controllers\NotesController::search');
    $router->get('/notes/by-parent', 'Api\Controllers\NotesController::byParent');
    $router->get('/notes/recent', 'Api\Controllers\NotesController::recent');
    $router->get('/notes/pinned', 'Api\Controllers\NotesController::pinned');
    
    // Activity routes (aggregated views)
    $router->get('/activities', 'Api\Controllers\ActivitiesController::list');
    $router->post('/activities', 'Api\Controllers\ActivitiesController::create');
    $router->get('/activities/upcoming', 'Api\Controllers\ActivitiesController::upcoming');
    $router->get('/activities/recent', 'Api\Controllers\ActivitiesController::recent');
}