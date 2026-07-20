<?php

namespace App\Services\Library;

use App\Enums\AccessLevel;
use App\Enums\ComponentLevel;

/**
 * Walks a library components directory (`library/{app}/src/components`)
 * and turns every `{level}/{slug}` component folder into a ParsedComponent.
 */
class ComponentScanner
{
    public function __construct(
        private readonly AnnotationParser $parser = new AnnotationParser,
    ) {}

    /**
     * @return array<string, ParsedComponent> keyed by full slug (`{level-dir}/{slug}`)
     */
    public function scan(string $componentsPath, string $framework): array
    {
        $components = [];

        if (! is_dir($componentsPath)) {
            return $components;
        }

        $indexFile = $framework === 'vue' ? 'index.vue' : 'index.tsx';

        foreach (ComponentLevel::cases() as $level) {
            $levelPath = $componentsPath.DIRECTORY_SEPARATOR.$level->directory();

            if (! is_dir($levelPath)) {
                continue;
            }

            foreach (scandir($levelPath) ?: [] as $slug) {
                $componentPath = $levelPath.DIRECTORY_SEPARATOR.$slug;

                if ($slug[0] === '.' || ! is_dir($componentPath)) {
                    continue;
                }

                $filePath = $componentPath.DIRECTORY_SEPARATOR.$indexFile;

                if (! is_file($filePath)) {
                    continue;
                }

                $component = $this->parseComponent($componentPath, $filePath, $level, $slug, $framework);
                $components[$component->fullSlug()] = $component;
            }
        }

        ksort($components);

        return $components;
    }

    private function parseComponent(
        string $componentPath,
        string $filePath,
        ComponentLevel $level,
        string $slug,
        string $framework,
    ): ParsedComponent {
        $errors = [];
        $source = (string) file_get_contents($filePath);

        $annotation = [
            'slug' => $slug,
            'name' => $slug,
            'level' => $level,
            'usage' => '',
            'industries' => [],
            'tags' => [],
            'access' => AccessLevel::Free,
            'sourceUrl' => null,
            'deps' => [],
            'version' => '0.0.0',
        ];

        try {
            $annotation = $this->parser->parse($source);
        } catch (AnnotationException $exception) {
            $errors[] = $exception->getMessage();
        }

        [$params, $paramsJson, $paramsError] = $this->readJson($componentPath, 'params.json');
        if ($paramsError !== null) {
            $errors[] = $paramsError;
        }

        [$data, $dataJson, $dataError] = $this->readJson($componentPath, 'data.json');
        if ($dataError !== null) {
            $errors[] = $dataError;
        }

        return new ParsedComponent(
            slug: $annotation['slug'],
            name: $annotation['name'],
            level: $annotation['level'],
            usage: $annotation['usage'],
            industries: $annotation['industries'],
            tags: $annotation['tags'],
            access: $annotation['access'],
            sourceUrl: $annotation['sourceUrl'],
            deps: $annotation['deps'],
            version: $annotation['version'],
            filePath: $filePath,
            source: $source,
            params: $params,
            data: $data,
            framework: $framework,
            paramsJson: $paramsJson,
            dataJson: $dataJson,
            errors: $errors,
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: ?string, 2: ?string}
     */
    private function readJson(string $componentPath, string $file): array
    {
        $path = $componentPath.DIRECTORY_SEPARATOR.$file;

        if (! is_file($path)) {
            return [[], null, "Missing {$file}"];
        }

        $raw = (string) file_get_contents($path);
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [[], $raw, "Invalid {$file}: not a JSON object"];
        }

        return [$decoded, $raw, null];
    }
}
