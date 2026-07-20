<?php

namespace App\Services\Catalog;

use App\Models\Component;

/**
 * Reads a component's authored files (SPEC §3.1 two-file model) from the
 * library trees for the public component payload: the per-framework entry
 * source plus the shared params.json / data.json. Fail-soft — a component
 * whose files are not checked out locally still renders, with empty
 * files/data/params instead of an error.
 */
class ComponentContent
{
    /**
     * @return array{files: array{react: list<array{path: string, code: string}>, vue: list<array{path: string, code: string}>}, data: array<string, mixed>, params: array<string, mixed>}
     */
    public function for(Component $component): array
    {
        return [
            'files' => [
                'react' => $this->files($component, 'react'),
                'vue' => $this->files($component, 'vue'),
            ],
            'data' => $this->json($component, 'data.json'),
            'params' => $this->json($component, 'params.json'),
        ];
    }

    /**
     * @return list<array{path: string, code: string}>
     */
    private function files(Component $component, string $framework): array
    {
        $entry = $framework === 'vue' ? 'index.vue' : 'index.tsx';
        $directory = $this->directory($component, $framework);

        if ($directory === null || ! is_file($directory.'/'.$entry)) {
            return [];
        }

        return [[
            'path' => $component->slug.'/'.$entry,
            'code' => (string) file_get_contents($directory.'/'.$entry),
        ]];
    }

    /**
     * params.json / data.json are authored identically on both sides and
     * enforced by the sync QA gate; read react first, vue as fallback.
     *
     * @return array<string, mixed>
     */
    private function json(Component $component, string $file): array
    {
        foreach (['react', 'vue'] as $framework) {
            $directory = $this->directory($component, $framework);

            if ($directory === null || ! is_file($directory.'/'.$file)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($directory.'/'.$file), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function directory(Component $component, string $framework): ?string
    {
        $base = (string) config("library.{$framework}_path", '');

        if ($base === '') {
            return null;
        }

        return $base.'/'.$component->slug;
    }
}
