<?php
/**
 * Bootstrap file for SuiteCRM API
 * Properly initializes SuiteCRM environment for API usage
 */

// Define this as a valid entry point
if (!defined('sugarEntry')) {
    define('sugarEntry', true);
}

// Get the SuiteCRM root directory
$suitecrmRoot = realpath(__DIR__ . '/../..');
if (!$suitecrmRoot) {
    die("Error: Could not find SuiteCRM root directory\n");
}

// Change to SuiteCRM root
chdir($suitecrmRoot);

// Simply use the standard SuiteCRM entry point which handles all initialization properly
require_once 'include/entryPoint.php';

// API-specific initialization complete
return true;