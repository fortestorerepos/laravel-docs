<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Normalizers;

use Illuminate\Filesystem\Filesystem;

class ApiNormalizer
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return array{groups: list<array{name: string, endpoints: list<array<string, mixed>>}>}
     */
    public function normalize(string $sourcePath): array
    {
        $payload = $this->firstJson($sourcePath, ['collection.json', 'openapi.json', 'scribe.json']);

        if ($payload === null) {
            return ['groups' => []];
        }

        if (isset($payload['openapi'], $payload['paths']) && is_array($payload['paths'])) {
            return $this->fromOpenApi($payload);
        }

        return $this->fromScribeCollection($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{groups: list<array{name: string, endpoints: list<array<string, mixed>>}>}
     */
    private function fromOpenApi(array $payload): array
    {
        $groups = [];

        foreach ($payload['paths'] as $uri => $operations) {
            if (! is_array($operations)) {
                continue;
            }

            foreach ($operations as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                $tag = (string) ($operation['tags'][0] ?? 'API');
                $groups[$tag] ??= ['name' => $tag, 'endpoints' => []];
                $groups[$tag]['endpoints'][] = [
                    'method' => strtoupper((string) $method),
                    'uri' => (string) $uri,
                    'name' => (string) ($operation['summary'] ?? $operation['operationId'] ?? ''),
                    'description' => (string) ($operation['description'] ?? ''),
                    'controller' => '',
                    'authenticated' => isset($operation['security']),
                    'parameters' => $operation['parameters'] ?? [],
                    'body' => $operation['requestBody'] ?? [],
                    'responses' => $operation['responses'] ?? [],
                ];
            }
        }

        return ['groups' => array_values($groups)];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{groups: list<array{name: string, endpoints: list<array<string, mixed>>}>}
     */
    private function fromScribeCollection(array $payload): array
    {
        $groups = [];
        $items = is_array($payload['item'] ?? null) ? $payload['item'] : ($payload['groups'] ?? []);

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = (string) ($item['name'] ?? 'API');
            $endpoints = [];

            foreach ((array) ($item['item'] ?? $item['endpoints'] ?? []) as $endpoint) {
                if (! is_array($endpoint)) {
                    continue;
                }

                $request = is_array($endpoint['request'] ?? null) ? $endpoint['request'] : [];
                $url = is_array($request['url'] ?? null) ? $request['url'] : [];

                $endpoints[] = [
                    'method' => strtoupper((string) ($request['method'] ?? $endpoint['method'] ?? 'GET')),
                    'uri' => (string) ($url['raw'] ?? $endpoint['uri'] ?? $endpoint['url'] ?? ''),
                    'name' => (string) ($endpoint['name'] ?? $endpoint['title'] ?? ''),
                    'description' => (string) ($endpoint['description'] ?? $request['description'] ?? ''),
                    'controller' => (string) ($endpoint['controller'] ?? $endpoint['action'] ?? ''),
                    'authenticated' => (bool) ($endpoint['authenticated'] ?? false),
                    'parameters' => $endpoint['parameters'] ?? $request['query'] ?? [],
                    'body' => $endpoint['body'] ?? $request['body'] ?? [],
                    'responses' => $endpoint['responses'] ?? $endpoint['response'] ?? [],
                ];
            }

            $groups[] = ['name' => $name, 'endpoints' => $endpoints];
        }

        return ['groups' => $groups];
    }

    /**
     * @param  list<string>  $preferredNames
     * @return array<string, mixed>|null
     */
    private function firstJson(string $sourcePath, array $preferredNames): ?array
    {
        if (! $this->files->isDirectory($sourcePath)) {
            return null;
        }

        foreach ($preferredNames as $preferredName) {
            $path = $sourcePath.'/'.$preferredName;

            if ($this->files->exists($path)) {
                $decoded = json_decode($this->files->get($path), true);

                return is_array($decoded) ? $decoded : null;
            }
        }

        foreach ($this->files->allFiles($sourcePath) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $decoded = json_decode($this->files->get($file->getPathname()), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
