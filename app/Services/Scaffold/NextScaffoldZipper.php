<?php

namespace App\Services\Scaffold;

use App\Models\Component;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Next.js starter scaffold (SPEC §6.3, FR-5): App Router, TypeScript-only,
 * Next 15 + React 19 + Tailwind 4. The closure's components ship under
 * `components/` (React sources, imports rewritten by the shared ClosureZip
 * kernel); page-level picks each become an `app/{kebab-name}/page.tsx`
 * route; loose selected sections are assembled into `app/page.tsx` in
 * selection order with their sample-data modules spread in as props.
 * Static starter files are stubs under resources/scaffold/next/.
 */
class NextScaffoldZipper extends ScaffoldZipper
{
    public function scaffoldFramework(): string
    {
        return 'next';
    }

    public function sourceFramework(): string
    {
        return 'react';
    }

    /**
     * @return array<string, string>
     */
    protected function staticEntries(Project $project): array
    {
        return [
            'app/globals.css' => $this->stub('globals.css'),
            'tsconfig.json' => $this->stub('tsconfig.json'),
            'next.config.ts' => $this->stub('next.config.ts'),
            'postcss.config.mjs' => $this->stub('postcss.config.mjs'),
            '.gitignore' => $this->stub('gitignore'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function layoutEntries(Project $project): array
    {
        return [
            'app/layout.tsx' => $this->stub('layout.tsx', [
                '{{ project_name }}' => $project->name,
            ]),
        ];
    }

    /**
     * app/page.tsx — the loose selections rendered in selection order, each
     * with its sample-data module spread into its props. The `as const` data
     * modules widen via a ComponentProps cast so the starter type-checks.
     *
     * @param  list<array{component: Component, name: string, source: string, data: string|null}>  $picks
     * @return array<string, string>
     */
    protected function indexEntries(Project $project, array $picks): array
    {
        $imports = [];
        $rendered = [];

        foreach ($picks as $pick) {
            $imports[] = "import {$pick['name']} from '{$this->specifier('app/page.tsx', $pick['source'])}';";

            if ($pick['data'] !== null) {
                $dataLocal = Str::camel($pick['name']).'Data';
                $imports[] = "import {$dataLocal} from '{$this->specifier('app/page.tsx', $pick['data'])}';";
                $rendered[] = "            <{$pick['name']} {...({$dataLocal} as ComponentProps<typeof {$pick['name']}>)} />";
            } else {
                $rendered[] = "            <{$pick['name']} />";
            }
        }

        if ($imports !== []) {
            array_unshift($imports, "import type { ComponentProps } from 'react';");
        }

        $body = $rendered === []
            // Only page-level picks (or an empty project): the routes carry
            // the app, the index stays a valid empty shell.
            ? '        <main />'
            : "        <main>\n".implode("\n", $rendered)."\n        </main>";

        return [
            'app/page.tsx' => implode("\n", [
                ...$imports,
                ...($imports === [] ? [] : ['']),
                'export default function Home() {',
                '    return (',
                $body,
                '    );',
                '}',
                '',
            ]),
        ];
    }

    /**
     * app/{kebab-name}/page.tsx — one App Router route per page-level
     * component, rendering it with its sample data.
     *
     * @param  array{component: Component, name: string, source: string, data: string|null}  $pick
     * @return array<string, string>
     */
    protected function routeEntries(Component $page, array $pick): array
    {
        $entry = "app/{$page->basename}/page.tsx";

        $imports = [
            "import {$pick['name']} from '{$this->specifier($entry, $pick['source'])}';",
        ];

        if ($pick['data'] !== null) {
            array_unshift($imports, "import type { ComponentProps } from 'react';");
            $dataLocal = Str::camel($pick['name']).'Data';
            $imports[] = "import {$dataLocal} from '{$this->specifier($entry, $pick['data'])}';";
            $render = "        <{$pick['name']} {...({$dataLocal} as ComponentProps<typeof {$pick['name']}>)} />";
        } else {
            $render = "        <{$pick['name']} />";
        }

        return [
            $entry => implode("\n", [
                ...$imports,
                '',
                'export default function Page() {',
                '    return (',
                $render,
                '    );',
                '}',
                '',
            ]),
        ];
    }

    /**
     * Next 15 + React 19 + Tailwind 4 baseline with the closure's npm deps
     * merged into `dependencies` at registry-pinned versions, deduped by
     * package name (baseline wins on collision). Deps missing from the
     * registry are NOT invented — the README flags them (SPEC §2.5).
     *
     * @param  array<string, string|null>  $deps
     */
    protected function packageJson(Project $project, array $deps): string
    {
        $dependencies = [
            'next' => '^15.3.0',
            'react' => '^19.1.0',
            'react-dom' => '^19.1.0',
        ];

        foreach (array_filter($deps) as $pinned) {
            ['name' => $name, 'version' => $version] = $this->packageSpec($pinned);
            $dependencies[$name] ??= $version;
        }

        ksort($dependencies);

        $devDependencies = [
            '@tailwindcss/postcss' => '^4.1.0',
            '@types/node' => '^22.15.0',
            '@types/react' => '^19.1.0',
            '@types/react-dom' => '^19.1.0',
            'tailwindcss' => '^4.1.0',
            'typescript' => '^5.8.0',
        ];

        ksort($devDependencies);

        return json_encode([
            'name' => Str::slug($project->name) ?: 'frontendparts-next-starter',
            'version' => '0.1.0',
            'private' => true,
            'scripts' => [
                'dev' => 'next dev',
                'build' => 'next build',
                'start' => 'next start',
                'lint' => 'next lint',
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
            "# {$project->name} — Next.js starter",
            '',
            'A runnable **Next.js 15 + React 19 + Tailwind 4** app scaffolded from your FrontendParts project — your picks plus their full composition closure, wired into the App Router.',
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
            $lines[] = "- `/{$page->basename}` — {$page->name} (`app/{$page->basename}/page.tsx`)";
        }

        $lines[] = '';
        $lines[] = '## Files';
        $lines[] = '';

        foreach ($fileMap as $file) {
            $lines[] = "- `{$file['path']}` — {$file['component']} ({$file['note']})";
        }

        $lines[] = '- `app/` — App Router layout, global CSS (Tailwind 4 import) and page routes';
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
            $lines[] = 'No extra npm dependencies — this starter is zero-dep beyond the Next.js / React / Tailwind baseline.';
            $lines[] = '';
        }

        $lines[] = '## Notes';
        $lines[] = '';
        $lines[] = '- Tailwind CSS 4 is pre-wired via `@tailwindcss/postcss` and the `@import "tailwindcss";` in `app/globals.css`.';
        $lines[] = '- Sample data lives in `data/*.ts` modules and is spread into each component\'s props on the assembled pages — edit there to re-skin content.';
        $lines[] = '- Sample images are remote URLs by design (SPEC FR-5.4); swap them for your own assets in `data/` or drop files into `public/`.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Load a starter stub from resources/scaffold/next/ with placeholder
     * replacement.
     *
     * @param  array<string, string>  $replacements
     */
    private function stub(string $name, array $replacements = []): string
    {
        $contents = (string) file_get_contents(resource_path("scaffold/next/{$name}.stub"));

        return strtr($contents, $replacements);
    }
}
