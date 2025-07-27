<?php

// Set environment to testing
$_ENV['ENVIRONMENT'] = 'testing';
$_ENV['DB_NAME'] = 'crm_test';

// Load composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Initialize Eloquent for tests
$capsule = new \Illuminate\Database\Capsule\Manager;

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? 'mysql',
    'database'  => $_ENV['DB_NAME'] ?? 'crm_test',
    'username'  => $_ENV['DB_USER'] ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? 'root',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "Test environment initialized\n";