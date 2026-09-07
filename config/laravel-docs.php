<?php

declare(strict_types=1);

return [

    'base_path' => storage_path('app/laravel-docs'),

    'raw_path' => storage_path('app/laravel-docs/raw'),

    'normalized_path' => storage_path('app/laravel-docs/normalized'),

    'output_path' => storage_path('app/laravel-docs/generated'),

    'process_timeout' => 300,

    'sections' => [
        'api' => true,
        'database' => true,
        'code' => true,
    ],

    'api' => [
        'generated_path' => public_path('docs'),
    ],

    'database' => [
        'java_executable' => 'java',
        'schemaspy_jar' => null,
        'arguments' => [],
    ],

    'code' => [
        'executable' => 'phpdoc',
        'paths' => [
            app_path(),
        ],
    ],

];
