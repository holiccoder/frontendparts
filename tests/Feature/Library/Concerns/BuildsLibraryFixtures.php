<?php

namespace Tests\Feature\Library\Concerns;

use App\Models\Category;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Builds throwaway library component trees (react + vue) on disk and points
 * the library.* config at them, so each test composes exactly the fixture it
 * needs.
 */
trait BuildsLibraryFixtures
{
    protected string $libraryRoot;

    protected function setUpLibraryFixtures(): void
    {
        $this->libraryRoot = storage_path('framework/testing/library-'.Str::random(8));

        mkdir($this->libraryRoot.'/react/src/components', 0777, true);
        mkdir($this->libraryRoot.'/vue/src/components', 0777, true);

        config()->set('library.react_path', $this->libraryRoot.'/react/src/components');
        config()->set('library.vue_path', $this->libraryRoot.'/vue/src/components');
        config()->set('library.registry_path', $this->libraryRoot.'/deps.registry.json');

        $this->writeRegistry([
            'lucide' => ['react' => 'lucide-react@^1.25.0', 'vue' => 'lucide-vue-next@^1.0.0'],
        ]);
    }

    protected function tearDownLibraryFixtures(): void
    {
        File::deleteDirectory($this->libraryRoot);
    }

    /**
     * Write a component into the fixture tree(s).
     *
     * @param  array<string, ?string>  $annotation  overrides; null omits the line
     * @param  array<string, mixed>|null  $params
     * @param  array<string, mixed>|null  $data
     * @param  list<string>  $imports  extra import paths (relative or @/ alias)
     * @param  list<string>  $frameworks
     */
    protected function libraryComponent(
        string $fullSlug,
        array $annotation = [],
        ?array $params = null,
        ?array $data = null,
        ?string $source = null,
        array $imports = [],
        array $frameworks = ['react', 'vue'],
        ?string $rawParams = null,
        ?string $rawData = null,
    ): void {
        [$levelDir, $slug] = explode('/', $fullSlug, 2);
        $level = match ($levelDir) {
            'elements' => 'element',
            'blocks' => 'block',
            'sections' => 'section',
            'pages' => 'page',
        };

        $fields = [
            'component' => $slug,
            'name' => Str::headline(str_replace('-', ' ', $slug)),
            'level' => $level,
            'usage' => 'pricing',
            'industries' => '',
            'tags' => '',
            'access' => 'free',
            'source' => '',
            'deps' => '',
            'version' => '1.0.0',
        ];

        foreach ($annotation as $key => $value) {
            if ($value === null) {
                unset($fields[$key]);
            } else {
                $fields[$key] = $value;
            }
        }

        $docblock = "/**\n";
        foreach ($fields as $tag => $value) {
            $docblock .= " * @{$tag} {$value}\n";
        }
        $docblock .= " */\n";

        $params ??= [
            'heading' => ['type' => 'string', 'default' => 'Heading', 'description' => 'Heading text.'],
        ];
        $data ??= ['heading' => 'Sample heading'];

        foreach ($frameworks as $framework) {
            $dir = "{$this->libraryRoot}/{$framework}/src/components/{$levelDir}/{$slug}";

            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $importLines = implode("\n", array_map(
                fn (string $path, int $i): string => "import Child{$i} from '{$path}';",
                $imports,
                array_keys($imports),
            ));

            if ($source !== null) {
                $contents = $docblock.$source;
            } elseif ($framework === 'react') {
                $contents = $docblock.$importLines."\nexport default function Component() { return <div />; }\n";
            } else {
                $contents = "<script setup lang=\"ts\">\n{$docblock}{$importLines}\n</script>\n\n<template><div /></template>\n";
            }

            file_put_contents($dir.($framework === 'react' ? '/index.tsx' : '/index.vue'), $contents);
            file_put_contents($dir.'/params.json', $rawParams ?? json_encode($params, JSON_PRETTY_PRINT));
            file_put_contents($dir.'/data.json', $rawData ?? json_encode($data, JSON_PRETTY_PRINT));
        }
    }

    /**
     * @param  array<string, array{react: string, vue: string}>  $registry
     */
    protected function writeRegistry(array $registry): void
    {
        file_put_contents($this->libraryRoot.'/deps.registry.json', json_encode($registry, JSON_PRETTY_PRINT));
    }

    /**
     * @param  list<string>  $usage
     * @param  list<string>  $industries
     */
    protected function seedTaxonomy(array $usage = ['pricing'], array $industries = []): void
    {
        foreach ($usage as $slug) {
            Category::factory()->usage()->create([
                'slug' => $slug,
                'name' => Str::headline(str_replace('-', ' ', $slug)),
            ]);
        }

        foreach ($industries as $slug) {
            Category::factory()->industry()->create([
                'slug' => $slug,
                'name' => Str::headline(str_replace('-', ' ', $slug)),
            ]);
        }
    }
}
