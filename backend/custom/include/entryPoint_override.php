<?php
/**
 * Custom entry point override to fix initialization issues
 * This file is upgrade-safe as it is in the custom directory
 */

// Initialize logger before anything else
if (\!isset($GLOBALS["log"]) || empty($GLOBALS["log"])) {
    require_once "include/SugarLogger/LoggerManager.php";
    $GLOBALS["log"] = LoggerManager::getLogger("SugarCRM");
}

// Ensure database manager is available
if (\!isset($GLOBALS["db"]) || empty($GLOBALS["db"])) {
    require_once "include/database/DBManagerFactory.php";
    $GLOBALS["db"] = DBManagerFactory::getInstance();
}
