<?php
// API entry point that handles PATH_INFO properly

// Set up the path info manually since Apache mod_rewrite doesn't preserve it
$_SERVER['PATH_INFO'] = str_replace('/api/v8', '', $_SERVER['REQUEST_URI']);
$_SERVER['PATH_INFO'] = strtok($_SERVER['PATH_INFO'], '?'); // Remove query string

// Now include the actual API
require_once __DIR__ . '/custom/api/index.php';