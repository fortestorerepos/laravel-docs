<?php

declare(strict_types=1);

use LaravelDocs\LaravelDocs\Adapters\Concerns\FindsExecutables;

it('includes install hints when an executable cannot be resolved', function () {
    $finder = new class
    {
        use FindsExecutables {
            findExecutable as public;
        }
    };

    expect(fn () => $finder->findExecutable(
        'definitely-not-a-real-binary',
        'phpDocumentor',
        'laravel-docs.code.executable',
        [],
        'Install it with composer require --dev phpdocumentor/phpdocumentor.',
    ))->toThrow(RuntimeException::class, 'composer require --dev phpdocumentor/phpdocumentor');
});
