<?php

namespace App\Services\Library;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;

/**
 * Parses the docblock annotation from a component's index.tsx / index.vue
 * (SPEC §8.2). The annotation is the metadata source of truth.
 *
 * @phpstan-type AnnotationArray array{
 *     slug: string,
 *     name: string,
 *     level: ComponentLevel,
 *     usage: string,
 *     industries: list<string>,
 *     tags: list<string>,
 *     access: AccessLevel,
 *     sourceUrl: ?string,
 *     deps: list<string>,
 *     version: string,
 * }
 */
class AnnotationParser
{
    /**
     * Required fields; industries / tags / deps may be empty.
     *
     * @var list<string>
     */
    private const REQUIRED = ['component', 'name', 'level', 'usage', 'access', 'version'];

    /**
     * Parse the first docblock found in the given source contents.
     *
     * @return AnnotationArray
     *
     * @throws AnnotationException
     */
    public function parse(string $contents): array
    {
        if (! preg_match('/\/\*\*(?<block>.*?)\*\//s', $contents, $matches)) {
            throw new AnnotationException('Missing annotation docblock');
        }

        $fields = $this->extractFields($matches['block']);

        foreach (self::REQUIRED as $field) {
            if (! array_key_exists($field, $fields) || trim($fields[$field]) === '') {
                throw new AnnotationException("Missing required annotation field: @{$field}");
            }
        }

        $level = ComponentLevel::tryFrom(trim($fields['level']));
        if ($level === null) {
            throw new AnnotationException(
                "Unknown level '".trim($fields['level'])."' (expected element, block, section or page)"
            );
        }

        $access = $this->parseAccess(trim($fields['access']));

        $deps = $this->parseList($fields['deps'] ?? '');
        foreach ($deps as $dep) {
            if (! preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $dep)) {
                throw new AnnotationException(
                    "Invalid @deps entry '{$dep}': logical names only, no versions or @ scopes"
                );
            }
        }

        $source = trim($fields['source'] ?? '');

        return [
            'slug' => trim($fields['component']),
            'name' => trim($fields['name']),
            'level' => $level,
            'usage' => trim($fields['usage']),
            'industries' => $this->parseList($fields['industries'] ?? ''),
            'tags' => $this->parseList($fields['tags'] ?? ''),
            'access' => $access,
            'sourceUrl' => $source === '' ? null : $source,
            'deps' => $deps,
            'version' => trim($fields['version']),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractFields(string $block): array
    {
        $fields = [];

        foreach (preg_split('/\R/', $block) as $line) {
            if (preg_match('/@(?<tag>[a-zA-Z]+)\s*(?<value>.*)$/', $line, $lineMatches)) {
                $fields[strtolower($lineMatches['tag'])] = trim($lineMatches['value']);
            }
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function parseList(string $value): array
    {
        $items = array_filter(
            array_map(trim(...), explode(',', $value)),
            fn (string $item): bool => $item !== '',
        );

        return array_values($items);
    }

    /**
     * @throws AnnotationException
     */
    private function parseAccess(string $value): AccessLevel
    {
        return match (strtolower($value)) {
            'free' => AccessLevel::Free,
            'pro', 'paid' => AccessLevel::Paid,
            default => throw new AnnotationException(
                "Unknown access '{$value}' (expected free or pro)"
            ),
        };
    }
}
