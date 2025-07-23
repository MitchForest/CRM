<?php
/**
 * Fix initialization issues in SuiteCRM
 * This ensures proper global variables are set
 */

if (\!defined("sugarEntry")) {
    define("sugarEntry", true);
}

// Initialize logger if not already done
if (\!isset($GLOBALS["log"]) || empty($GLOBALS["log"])) {
    require_once "include/SugarLogger/LoggerManager.php";
    $GLOBALS["log"] = LoggerManager::getLogger("SugarCRM");
}

// Ensure modules are loaded
if (\!isset($GLOBALS["moduleList"])) {
    require_once "include/modules.php";
}

// Ensure beanList and beanFiles are loaded
if (\!isset($GLOBALS["beanList"]) || \!isset($GLOBALS["beanFiles"])) {
    include "include/modules.php";
    if (file_exists("custom/application/Ext/Include/modules.ext.php")) {
        include "custom/application/Ext/Include/modules.ext.php";
    }
}
