<?php

declare(strict_types=1);

namespace LaravelDocs\LaravelDocs\Normalizers;

use Illuminate\Filesystem\Filesystem;
use SimpleXMLElement;

class CodeNormalizer
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @return array{namespaces: list<array{name: string, classes: list<string>}>, classes: list<array<string, mixed>>}
     */
    public function normalize(string $sourcePath): array
    {
        $xml = $this->firstXml($sourcePath);

        if ($xml === null) {
            return ['namespaces' => [], 'classes' => []];
        }

        $types = [];

        foreach (['class', 'interface', 'trait', 'enum'] as $type) {
            foreach ($xml->xpath('//'.$type) ?: [] as $node) {
                $types[] = $this->normalizeType($node, $type);
            }
        }

        usort($types, fn (array $first, array $second): int => [$first['namespace'], $first['name']] <=> [$second['namespace'], $second['name']]);

        $namespaces = [];

        foreach ($types as $type) {
            $namespace = $type['namespace'];
            $namespaces[$namespace] ??= ['name' => $namespace, 'classes' => []];
            $namespaces[$namespace]['classes'][] = $type['name'];
        }

        return [
            'namespaces' => array_values($namespaces),
            'classes' => $types,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeType(SimpleXMLElement $node, string $type): array
    {
        $name = $this->attribute($node, 'name');
        $fqsen = $this->attribute($node, 'fqsen') ?: $this->attribute($node, 'full_name');

        return [
            'name' => $this->shortName($name, $fqsen),
            'namespace' => $this->namespaceFor($node, $name, $fqsen),
            'type' => $type,
            'summary' => trim((string) ($node->docblock->description ?? $node->description ?? $node->summary ?? '')),
            'parent' => $this->attribute($node, 'extends') ?: $this->textFromFirst($node, ['extends', 'parent']),
            'interfaces' => $this->valuesFrom($node, ['implements', 'interface']),
            'methods' => $this->methods($node),
            'properties' => $this->properties($node),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function methods(SimpleXMLElement $node): array
    {
        $methods = [];

        foreach ($node->xpath('.//method') ?: [] as $method) {
            if ($this->attribute($method, 'visibility') !== 'public') {
                continue;
            }

            $methods[] = [
                'name' => $this->attribute($method, 'name'),
                'summary' => trim((string) ($method->docblock->description ?? $method->description ?? $method->summary ?? '')),
                'parameters' => $this->parameters($method),
                'return_type' => $this->attribute($method, 'return') ?: $this->textFromFirst($method, ['return', 'response', 'type']),
            ];
        }

        return $methods;
    }

    /**
     * @return list<array{name: string, type: string, default: string}>
     */
    private function parameters(SimpleXMLElement $method): array
    {
        $parameters = [];

        foreach ($method->xpath('.//argument|.//parameter|.//param') ?: [] as $parameter) {
            $parameters[] = [
                'name' => ltrim($this->attribute($parameter, 'name'), '$'),
                'type' => $this->attribute($parameter, 'type') ?: $this->textFromFirst($parameter, ['type']),
                'default' => $this->attribute($parameter, 'default'),
            ];
        }

        return $parameters;
    }

    /**
     * @return list<array{name: string, type: string, summary: string}>
     */
    private function properties(SimpleXMLElement $node): array
    {
        $properties = [];

        foreach ($node->xpath('.//property') ?: [] as $property) {
            if ($this->attribute($property, 'visibility') !== 'public') {
                continue;
            }

            $properties[] = [
                'name' => $this->attribute($property, 'name'),
                'type' => $this->attribute($property, 'type') ?: $this->textFromFirst($property, ['type']),
                'summary' => trim((string) ($property->docblock->description ?? $property->description ?? $property->summary ?? '')),
            ];
        }

        return $properties;
    }

    private function firstXml(string $sourcePath): ?SimpleXMLElement
    {
        if (! $this->files->isDirectory($sourcePath)) {
            return null;
        }

        foreach ($this->files->allFiles($sourcePath) as $file) {
            if ($file->getExtension() !== 'xml') {
                continue;
            }

            $xml = simplexml_load_string($this->files->get($file->getPathname()));

            if ($xml instanceof SimpleXMLElement) {
                return $xml;
            }
        }

        return null;
    }

    private function attribute(SimpleXMLElement $node, string $attribute): string
    {
        return trim((string) ($node->attributes()[$attribute] ?? ''));
    }

    /**
     * @param  list<string>  $names
     */
    private function textFromFirst(SimpleXMLElement $node, array $names): string
    {
        foreach ($names as $name) {
            $result = $node->xpath('.//'.$name);

            if ($result !== false && isset($result[0])) {
                return trim((string) $result[0]);
            }
        }

        return '';
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function valuesFrom(SimpleXMLElement $node, array $names): array
    {
        $values = [];

        foreach ($names as $name) {
            foreach ($node->xpath('.//'.$name) ?: [] as $item) {
                $value = $this->attribute($item, 'name') ?: trim((string) $item);

                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    private function shortName(string $name, string $fqsen): string
    {
        $value = $name !== '' ? $name : trim($fqsen, '\\');
        $parts = explode('\\', $value);

        return (string) end($parts);
    }

    private function namespaceFor(SimpleXMLElement $node, string $name, string $fqsen): string
    {
        $namespace = $this->attribute($node, 'namespace') ?: trim((string) ($node->namespace ?? ''));

        if ($namespace !== '') {
            return trim($namespace, '\\');
        }

        $value = trim($fqsen !== '' ? $fqsen : $name, '\\');
        $parts = explode('\\', $value);
        array_pop($parts);

        return implode('\\', $parts);
    }
}
