<?php
// Direct test of KB endpoint
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Set up environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v8/kb/categories';
$_GET = [];

// Capture all output and errors
ob_start();
$errorOutput = '';

// Set error handler to capture errors
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorOutput) {
    $errorOutput .= "Error [$errno]: $errstr in $errfile on line $errline\n";
    return true;
});

// Set exception handler
set_exception_handler(function($exception) use (&$errorOutput) {
    $errorOutput .= "Exception: " . $exception->getMessage() . "\n";
    $errorOutput .= "File: " . $exception->getFile() . "\n";
    $errorOutput .= "Line: " . $exception->getLine() . "\n";
    $errorOutput .= "Trace:\n" . $exception->getTraceAsString() . "\n";
});

try {
    // Include the index file
    require_once __DIR__ . '/index.php';
} catch (Throwable $e) {
    $errorOutput .= "Fatal Error: " . $e->getMessage() . "\n";
    $errorOutput .= "File: " . $e->getFile() . "\n";
    $errorOutput .= "Line: " . $e->getLine() . "\n";
    $errorOutput .= "Trace:\n" . $e->getTraceAsString() . "\n";
}

$output = ob_get_clean();

echo "=== ERROR OUTPUT ===\n";
echo $errorOutput;
echo "\n=== NORMAL OUTPUT ===\n";
echo $output;
echo "\n=== PHP ERRORS ===\n";
$lastError = error_get_last();
if ($lastError) {
    print_r($lastError);
}