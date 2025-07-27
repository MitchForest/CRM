<?php
// Direct test of KB articles endpoint
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up environment for articles endpoint
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v8/kb/articles';
$_SERVER['PATH_INFO'] = '/kb/articles';
$_GET = [];

// Include the API
require_once __DIR__ . '/index.php';