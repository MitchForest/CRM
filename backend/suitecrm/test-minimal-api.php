<?php
// Minimal test to identify the V8 API issue
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Register autoloader first
require_once 'vendor/autoload.php';

// Now check if TimeDate class exists
echo "1. Checking TimeDate class:\n";
if (class_exists('TimeDate')) {
    echo "   - TimeDate class: FOUND\n";
} else {
    echo "   - TimeDate class: NOT FOUND\n";
    
    // Try to load it manually
    if (file_exists('include/TimeDate.php')) {
        require_once 'include/TimeDate.php';
        echo "   - Loaded from include/TimeDate.php\n";
    } else {
        echo "   - File include/TimeDate.php not found\n";
    }
}

// Check if we can load the API now
echo "\n2. Testing API Core:\n";
try {
    if (file_exists('Api/Core/app.php')) {
        echo "   - Api/Core/app.php exists\n";
        
        // Set up minimal environment
        if (!defined('sugarEntry')) define('sugarEntry', true);
        
        // Load config
        require_once 'config.php';
        
        // Try to initialize API
        echo "   - Attempting to load API...\n";
        require_once 'Api/Core/app.php';
        echo "   - API loaded successfully!\n";
    } else {
        echo "   - Api/Core/app.php NOT FOUND\n";
    }
} catch (Exception $e) {
    echo "   - Exception: " . $e->getMessage() . "\n";
    echo "   - File: " . $e->getFile() . "\n";
    echo "   - Line: " . $e->getLine() . "\n";
} catch (Throwable $e) {
    echo "   - Fatal Error: " . $e->getMessage() . "\n";
    echo "   - File: " . $e->getFile() . "\n";
    echo "   - Line: " . $e->getLine() . "\n";
}