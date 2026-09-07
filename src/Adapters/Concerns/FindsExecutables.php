<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Adapters\Concerns;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

trait FindsExecutables
{
    private function findExecutable(?string $executable, string $toolName, string $configKey): string
    {
        if ($executable === null || $executable === '') {
            throw new RuntimeException("{$toolName} executable not configured. Configure {$configKey}.");
        }

        if (str_contains($executable, '/') || str_contains($executable, '\\')) {
            if (is_file($executable) && is_executable($executable)) {
                return $executable;
            }

            throw new RuntimeException("{$toolName} executable not found. Configure {$configKey}.");
        }

        $resolved = (new ExecutableFinder())->find($executable);

        if ($resolved === null) {
            throw new RuntimeException("{$toolName} executable not found. Configure {$configKey}.");
        }

        return $resolved;
    }
}
