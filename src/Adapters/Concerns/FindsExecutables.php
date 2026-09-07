<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Adapters\Concerns;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

trait FindsExecutables
{
    /**
     * @param  list<string>  $candidateNames
     */
    private function findExecutable(
        ?string $executable,
        string $toolName,
        string $configKey,
        array $candidateNames = [],
        string $installHint = '',
    ): string {
        $names = array_values(array_unique(array_filter(array_merge(
            [$executable],
            $candidateNames,
        ))));

        foreach ($names as $name) {
            $resolved = $this->resolveExecutable((string) $name);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $message = $executable === null || $executable === ''
            ? "{$toolName} executable not configured. Configure {$configKey}."
            : "{$toolName} executable not found. Configure {$configKey}.";

        if ($installHint !== '') {
            $message .= ' '.$installHint;
        }

        throw new RuntimeException($message);
    }

    private function resolveExecutable(string $executable): ?string
    {
        if (str_contains($executable, '/') || str_contains($executable, '\\')) {
            return $this->usableFile($executable) ? $executable : null;
        }

        foreach ($this->vendorBinPaths($executable) as $path) {
            if ($this->usableFile($path)) {
                return $path;
            }
        }

        $resolved = (new ExecutableFinder)->find($executable);

        return $resolved !== null && $this->usableFile($resolved) ? $resolved : null;
    }

    /**
     * @return list<string>
     */
    private function vendorBinPaths(string $name): array
    {
        $packageRoot = dirname(__DIR__, 3);
        $directories = [
            base_path('vendor/bin'),
            $packageRoot.'/vendor/bin',
        ];
        $filenames = [$name, $name.'.bat', $name.'.cmd'];
        $paths = [];

        foreach ($directories as $directory) {
            foreach ($filenames as $filename) {
                $paths[] = $directory.'/'.$filename;
            }
        }

        return $paths;
    }

    private function usableFile(string $path): bool
    {
        return is_file($path) && (DIRECTORY_SEPARATOR === '\\' || is_executable($path));
    }
}
