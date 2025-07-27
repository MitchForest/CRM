#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use OpenApi\Generator;

// Include all directories that might have OpenAPI annotations
$openapi = Generator::scan([
    __DIR__ . '/app/Http/Controllers',
    __DIR__ . '/app/Models',
    __DIR__ . '/app/Services',
    __DIR__ . '/routes'
]);

// Save as YAML
file_put_contents(__DIR__ . '/openapi-generated.yaml', $openapi->toYaml());

// Save as JSON
file_put_contents(__DIR__ . '/public/api-docs/openapi.json', $openapi->toJson());

echo "OpenAPI documentation generated successfully!\n";
echo "- YAML: openapi-generated.yaml\n";
echo "- JSON: public/api-docs/openapi.json\n";