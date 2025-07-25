<?php
// Simple runner for KB seed
chdir('/var/www/html');
define('sugarEntry', true);
require_once('include/entryPoint.php');
require_once('seed_kb_content.php');