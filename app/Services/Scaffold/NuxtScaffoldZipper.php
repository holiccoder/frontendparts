<?php

namespace App\Services\Scaffold;

use App\Models\Component;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Nuxt starter scaffold (SPEC §6.3, FR-5): TypeScript-only, Nuxt 4 + Vue 3 +
 * Tailwind 4. The closure's components ship under `components/` (Vue SFC
 * sources, imports rewritten by the shared ClosureZip kernel); page-level
 * picks each become an `app/pages/{kebab-name}.vue` file-based route; loose
 * selected sections are assembled into `app/pages/index.vue` in selection
 * order with their sample-data modules bound via `v-bind`. Component imports
 * are explicit relative imports (never Nuxt auto-imports), matching the
 * documented install flow (docs/content/install/nuxt.md). Static starter
 * files are stubs under resources/scaffold/nuxt/.
 */
class NuxtScaffoldZipper extends ScaffoldZipper
{
    public function scaffoldFramework(): string
    {
        return 'nuxt';
    }

    public function sourceFramework(): string
    {
        return 'vue';
    }

    /**
     * @return array<string, string>
     */
    protected function staticEntries(Project $project): array
    {
        return [
            'app/assets/css/main.css' => $this->stub('main.css'),
            'nuxt.config.ts' => $this->stub('nuxt.config.ts'),
            'tsconfig.json' => $this->stub('tsconfig.json'),
            '.gitignore' => $this->stub('gitignore'),
        ];
    }

    /**
     * app/app.vue — the Nuxt 4 app root: document title from the project
     * name, `<NuxtPage />` rendering the file-based routes.
     *
     * @return array<string, string>
     */
    protected function layoutEntries(Project $project): array
    {
        return [
            'app/app.vue' => $this->stub('app.vue', [
                '{{ project_name }}' => $project->name,
            ]),
        ];
    }

    /**
     * app/pages/index.vue — the loose selections rendered in selection
     * order, each with its sample-data module bound via `v-bind`.
     *
     * @param  list<array{component: Component, name: string, source: string, data: string|null}>  $picks
     * @return array<string, string>
     */
    protected function indexEntries(Project $project, array $picks): array
    {
        $imports = [];
        $rendered = [];

        foreach ($picks as $pick) {
            $imports[] = "import {$pick['name']} from '{$this->specifier('app/pages/index.vue', $pick['source'])}.vue';";

            if ($pick['data'] !== null) {
                $dataLocal = Str::camel($pick['name']).'Data';
                $imports[] = "import {$dataLocal} from '{$this->specifier('app/pages/index.vue', $pick['data'])}';";
                $rendered[] = "        <{$pick['name']} v-bind=\"{$dataLocal}\" />";
            } else {
                $rendered[] = "        <{$pick['name']} />";
            }
        }

        $body = $rendered === []
            // Only page-level picks (or an empty project): the routes carry
            // the app, the index stays a valid empty shell.
            ? '    <main />'
            : "    <main>\n".implode("\n", $rendered)."\n    </main>";

        return [
            'app/pages/index.vue' => implode("\n", [
                ...($imports === [] ? [] : [
                    '<script setup lang="ts">',
                    ...$imports,
                    '</script>',
                    '',
                ]),
                '<template>',
                $body,
                '</template>',
                '',
            ]),
        ];
    }

    /**
     * app/pages/{kebab-name}.vue — one file-based route per page-level
     * component, rendering it with its sample data.
     *
     * @param  array{component: Component, name: string, source: string, data: string|null}  $pick
     * @return array<string, string>
     */
    protected function routeEntries(Component $page, array $pick): array
    {
        $entry = "app/pages/{$page->basename}.vue";

        $imports = [
            "import {$pick['name']} from '{$this->specifier($entry, $pick['source'])}.vue';",
        ];

        if ($pick['data'] !== null) {
            $dataLocal = Str::camel($pick['name']).'Data';
            $imports[] = "import {$dataLocal} from '{$this->specifier($entry, $pick['data'])}';";
            $render = "    <{$pick['name']} v-bind=\"{$dataLocal}\" />";
        } else {
            $render = "    <{$pick['name']} />";
        }

        return [
            $entry => implode("\n", [
                '<script setup lang="ts">',
                ...$imports,
                '</script>',
                '',
                '<template>',
                $render,
                '</template>',
                '',
            ]),
        ];
    }

    /**
     * Nuxt 4 + Vue 3 + Tailwind 4 baseline with the closure's npm deps
     * merged into `dependencies` at registry-pinned versions, deduped by
     * package name (baseline wins on collision). Deps missing from the
     * registry are NOT invented — the README flags them (SPEC §2.5).
     *
     * @param  array<string, string|null>  $deps
     */
    protected function packageJson(Project $project, array $deps): string
    {
        $dependencies = [
            'nuxt' => '^4.1.0',
            'vue' => '^3.5.0',
        ];

        foreach (array_filter($deps) as $pinned) {
            ['name' => $name, 'version' => $version] = $this->packageSpec($pinned);
            $dependencies[$name] ??= $version;
        }

        ksort($dependencies);

        $devDependencies = [
            '@tailwindcss/vite' => '^4.1.0',
            'tailwindcss' => '^4.1.0',
            'typescript' => '^5.8.0',
        ];

        ksort($devDependencies);

        return json_encode([
            'name' => Str::slug($project->name) ?: 'frontendparts-nuxt-starter',
            'version' => '0.1.0',
            'private' => true,
            'scripts' => [
                'dev' => 'nuxt dev',
                'build' => 'nuxt build',
                'preview' => 'nuxt preview',
                'postinstall' => 'nuxt prepare',
            ],
            'dependencies' => (object) $dependencies,
            'devDependencies' => (object) $devDependencies,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * @param  list<Component>  $pages
     * @param  list<Component>  $loose
     * @param  list<array{path: string, component: string, note: string}>  $fileMap
     * @param  array<string, string|null>  $deps
     */
    protected function readme(Project $project, array $pages, array $loose, array $fileMap, array $deps): string
    {
        $lines = [
            "# {$project->name} — Nuxt starter",
            '',
            'A runnable **Nuxt 4 + Vue 3 + Tailwind 4** app scaffolded from your FrontendParts project — your picks plus their full composition closure, wired into Nuxt\'s file-based routing.',
            '',
            '## Getting started',
            '',
            '```bash',
            'npm install',
            'npm run dev',
            '```',
            '',
            '## Routes',
            '',
        ];

        if ($loose === []) {
            $lines[] = '- `/` — placeholder index (no loose sections in this project)';
        } else {
            $lines[] = '- `/` — index assembled from your loose selections, in selection order:';
            foreach ($loose as $member) {
                $lines[] = "  - {$member->name} (`{$member->slug}`)";
            }
        }

        foreach ($pages as $page) {
            $lines[] = "- `/{$page->basename}` — {$page->name} (`app/pages/{$page->basename}.vue`)";
        }

        $lines[] = '';
        $lines[] = '## Files';
        $lines[] = '';

        foreach ($fileMap as $file) {
            $lines[] = "- `{$file['path']}` — {$file['component']} ({$file['note']})";
        }

        $lines[] = '- `app/` — Nuxt 4 app dir: `app.vue` root, `pages/` file-based routes and the Tailwind 4 CSS entry';
        $lines[] = '- `public/` — static assets (empty: sample images stay remote URLs, nothing was downloaded)';
        $lines[] = '';

        $pinned = array_filter($deps);
        $unresolved = array_keys(array_filter($deps, fn (?string $package): bool => $package === null));

        if ($unresolved !== []) {
            $lines[] = '## Dependency notes';
            $lines[] = '';

            foreach ($unresolved as $dep) {
                $lines[] = "- ⚠️ `{$dep}` is not in the FrontendParts dependency registry — resolve its package manually.";
            }

            $lines[] = '';
        }

        if ($pinned === [] && $unresolved === []) {
            $lines[] = '## Dependency notes';
            $lines[] = '';
            $lines[] = 'No extra npm dependencies — this starter is zero-dep beyond the Nuxt / Vue / Tailwind baseline.';
            $lines[] = '';
        }

        $lines[] = '## Notes';
        $lines[] = '';
        $lines[] = '- Tailwind CSS 4 is pre-wired via `@tailwindcss/vite` in `nuxt.config.ts` and the `@import \'tailwindcss\';` in `app/assets/css/main.css`.';
        $lines[] = '- Components import each other with explicit relative imports (not Nuxt auto-imports), so `components/` resolves exactly as copied — keep the level folders intact.';
        $lines[] = '- Sample data lives in `data/*.ts` modules and is bound with `v-bind` on the assembled pages — edit there to re-skin content.';
        $lines[] = '- Sample images are remote URLs by design (SPEC FR-5.4); swap them for your own assets in `data/` or drop files into `public/`.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Load a starter stub from resources/scaffold/nuxt/ with placeholder
     * replacement.
     *
     * @param  array<string, string>  $replacements
     */
    private function stub(string $name, array $replacements = []): string
    {
        $contents = (string) file_get_contents(resource_path("scaffold/nuxt/{$name}.stub"));

        return strtr($contents, $replacements);
    }
}
