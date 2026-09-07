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
        'scribe_dir' => storage_path('app/laravel-docs/cache/scribe'),
        'generated_path' => storage_path('app/laravel-docs/raw/scribe'),
    ],

    'database' => [
        'connection' => null,
    ],

    'code' => [
        'executable' => 'phpdoc',
        'auto_download_phar' => true,
        'link_external_docs' => true,
        'phar_url' => 'https://phpdoc.org/phpDocumentor.phar',
        'phar_path' => storage_path('app/laravel-docs/bin/phpDocumentor.phar'),
        'cache_path' => storage_path('app/laravel-docs/cache/phpdocumentor'),
        'paths' => [
            app_path(),
        ],
    ],

];
