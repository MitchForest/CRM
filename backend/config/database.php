<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

require_once __DIR__ . '/../vendor/autoload.php';

$capsule = new Capsule;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? 'mysql',
    'database'  => $_ENV['DB_DATABASE'] ?? 'suitecrm',
    'username'  => $_ENV['DB_USERNAME'] ?? 'suitecrm',
    'password'  => $_ENV['DB_PASSWORD'] ?? 'suitecrm',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

// Set the event dispatcher used by Eloquent models
$capsule->setEventDispatcher(new Dispatcher(new Container));

// Make this Capsule instance available globally
$capsule->setAsGlobal();

// Setup the Eloquent ORM
$capsule->bootEloquent();

return $capsule;
