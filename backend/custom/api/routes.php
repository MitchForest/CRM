<?php
function configureRoutes($router) {
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
    
    // Activity routes (aggregated)
    $router->get('/activities', 'Api\Controllers\ActivitiesController::list');
    $router->post('/activities', 'Api\Controllers\ActivitiesController::create');
    $router->get('/activities/upcoming', 'Api\Controllers\ActivitiesController::upcoming');
    
    // Task routes (specific)
    $router->get('/tasks', 'Api\Controllers\TasksController::list');
    $router->get('/tasks/:id', 'Api\Controllers\TasksController::get');
    $router->post('/tasks', 'Api\Controllers\TasksController::create');
    $router->put('/tasks/:id', 'Api\Controllers\TasksController::update');
    $router->delete('/tasks/:id', 'Api\Controllers\TasksController::delete');
    
    // Case (Support Ticket) routes
    $router->get('/cases', 'Api\Controllers\CasesController::list');
    $router->get('/cases/:id', 'Api\Controllers\CasesController::get');
    $router->post('/cases', 'Api\Controllers\CasesController::create');
    $router->put('/cases/:id', 'Api\Controllers\CasesController::update');
    $router->delete('/cases/:id', 'Api\Controllers\CasesController::delete');
}