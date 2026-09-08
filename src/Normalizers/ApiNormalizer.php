<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Normalizers;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

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

        $normalized = $this->fromScribeCollection($payload);
        $openApi = [];

        if ($this->files->exists($sourcePath.'/openapi.json')) {
            $openApi = json_decode($this->files->get($sourcePath.'/openapi.json'), true) ?? [];
        } elseif ($this->files->exists($sourcePath.'/openapi.yaml')) {
            $openApi = Yaml::parse($this->files->get($sourcePath.'/openapi.yaml')) ?? [];
        }
        foreach ($normalized['groups'] as &$group) {
            foreach ($group['endpoints'] as &$endpoint) {
                $uri = explode('?', (string) $endpoint['uri'], 2)[0];
                $operation = $openApi['paths'][$uri][strtolower((string) $endpoint['method'])] ?? [];

                if (isset($operation['requestBody'])) {
                    $endpoint['body_schema'] = $operation['requestBody'];
                }
            }
        }

        return $normalized;
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
        $items = is_array($payload['item'] ?? null) ? $payload['item'] : ($payload['groups'] ?? []);

        return ['groups' => $this->collectionGroups($items, '', $payload['auth'] ?? [])];
    }

    /**
     * @param  array<mixed>  $items
     * @param  array<string, mixed>  $auth
     * @return list<array{name: string, endpoints: list<array<string, mixed>>}>
     */
    private function collectionGroups(array $items, string $parent, array $auth): array
    {
        $groups = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemAuth = $item['auth'] ?? $auth;
            $children = $item['item'] ?? $item['endpoints'] ?? null;

            if (is_array($children)) {
                $name = ($parent === '' ? '' : $parent.' / ').($item['name'] ?? 'API');
                foreach ($this->collectionGroups($children, $name, $itemAuth) as $group) {
                    $groups[$group['name']] ??= ['name' => $group['name'], 'endpoints' => []];
                    array_push($groups[$group['name']]['endpoints'], ...$group['endpoints']);
                }

                continue;
            }

            if (! isset($item['request']) && ! isset($item['uri']) && ! isset($item['url'])) {
                continue;
            }

            $name = $parent === '' ? 'API' : $parent;
            $groups[$name] ??= ['name' => $name, 'endpoints' => []];
            $groups[$name]['endpoints'][] = $this->collectionEndpoint($item, $itemAuth);
        }

        return array_values($groups);
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @param  array<string, mixed>  $auth
     * @return array<string, mixed>
     */
    private function collectionEndpoint(array $endpoint, array $auth): array
    {
        $request = is_array($endpoint['request'] ?? null) ? $endpoint['request'] : [];
        $auth = $request['auth'] ?? $auth;
        $url = $request['url'] ?? $endpoint['uri'] ?? $endpoint['url'] ?? '';
        $uri = is_array($url) ? (string) ($url['raw'] ?? '') : (string) $url;
        $uri = preg_replace('~^\{\{baseUrl\}\}~', '', $uri) ?? $uri;
        $uri = preg_replace('~/:([a-zA-Z_][a-zA-Z0-9_]*)~', '/{$1}', $uri) ?? $uri;
        $parameters = [];
        foreach (['path' => 'variable', 'query' => 'query'] as $location => $key) {
            foreach (is_array($url) ? ($url[$key] ?? []) : [] as $parameter) {
                if (! is_array($parameter) || ($parameter['disabled'] ?? false)) {
                    continue;
                }
                $parameters[] = [
                    'name' => $parameter['key'] ?? $parameter['id'] ?? '',
                    'in' => $location,
                    'example' => $parameter['value'] ?? '',
                    'description' => $parameter['description'] ?? '',
                    'required' => $location === 'path',
                ];
            }
        }
        $headers = $this->collectionHeaders($request['header'] ?? []);

        if (($auth['type'] ?? '') === 'bearer') {
            $headers['Authorization'] ??= 'Bearer {YOUR_BEARER_TOKEN}';
        }
        $responses = $endpoint['responses'] ?? $endpoint['response'] ?? [];
        foreach ($responses as &$response) {
            if (is_array($response) && isset($response['code'])) {
                $response['status'] = $response['code'];
                $response['headers'] = $this->collectionHeaders($response['header'] ?? []);
            }
        }
        unset($response);

        return [
            'method' => strtoupper((string) ($request['method'] ?? $endpoint['method'] ?? 'GET')),
            'uri' => $uri,
            'name' => (string) ($endpoint['name'] ?? $endpoint['title'] ?? ''),
            'description' => (string) ($endpoint['description'] ?? $request['description'] ?? ''),
            'controller' => (string) ($endpoint['controller'] ?? $endpoint['action'] ?? ''),
            'authenticated' => (bool) ($endpoint['authenticated'] ?? (isset($auth['type']) && $auth['type'] !== 'noauth')),
            'headers' => $headers,
            'parameters' => $endpoint['parameters'] ?? ($parameters ?: ($request['query'] ?? [])),
            'body' => $endpoint['body'] ?? $request['body'] ?? [],
            'responses' => $responses,
        ];
    }

    /**
     * @param  array<mixed>  $headers
     * @return array<string, string>
     */
    private function collectionHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            if (is_array($header) && isset($header['key']) && ! ($header['disabled'] ?? false)) {
                $normalized[(string) $header['key']] = (string) ($header['value'] ?? '');
            }
        }

        return $normalized;
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
