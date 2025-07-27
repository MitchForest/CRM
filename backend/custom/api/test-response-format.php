<?php
// Test response format
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/controllers/BaseController.php';
require_once __DIR__ . '/controllers/KnowledgeBaseController.php';
require_once __DIR__ . '/Request.php';
require_once __DIR__ . '/Response.php';

$controller = new Api\Controllers\KnowledgeBaseController();

// Test categories response
$request = new Api\Request('GET', '/kb/categories', []);
$response = $controller->getCategories($request);

// Get the response data
$data = $response->getData();

echo "Categories Response Format:\n";
echo json_encode($data, JSON_PRETTY_PRINT);
echo "\n\n";

// Test articles response
$_GET = ['limit' => '2'];
$request = new Api\Request('GET', '/kb/articles', []);
$response = $controller->getArticles($request);
$data = $response->getData();
$_GET = [];

echo "Articles Response Format:\n";
echo json_encode($data, JSON_PRETTY_PRINT);