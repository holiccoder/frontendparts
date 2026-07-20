<?php

namespace App\Services\Projects;

use App\Models\Component;
use App\Models\Project;
use App\Services\Catalog\ClosureZip;
use ZipArchive;

/**
 * Project pack zip (SPEC §6.2): the project's full component closure — the
 * `project_components` set is closure-complete by construction (SPEC §6.1
 * auto-add) — organized `components/` by level, `data/` sample-data modules,
 * a merged `package.json` dependency snippet (closure deps resolved and
 * deduped via the registry, SPEC §2.5), Tailwind CSS 4 setup notes and a
 * README. React/Vue is chosen at export time (SPEC §6.1) and only that
 * framework's sources are included. Assembly reuses the shared ClosureZip
 * kernel so import rewriting matches the single-component export.
 */
class ProjectPackZipper
{
    public function __construct(
        private readonly ClosureZip $closureZip = new ClosureZip,
    ) {}

    /**
     * Build the pack zip into a temp file and return its path. The caller
     * (BuildProjectPackZip) moves it onto the exports disk.
     */
    public function build(Project $project, string $framework): string
    {
        $members = $this->members($project);

        ['entries' => $entries, 'fileMap' => $fileMap] = $this->closureZip->entries($members, $framework);

        $deps = $this->closureZip->resolveDeps($members, $framework);

        $entries['package.json'] = $this->packageJson($deps);
        $entries['TAILWIND.md'] = $this->tailwindNotes();
        $entries['README.md'] = $this->readme($project, $members, $fileMap, $deps, $framework);

        $path = (string) tempnam(sys_get_temp_dir(), 'fp-pack-');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($entries as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        $zip->close();

        return $path;
    }

    /**
     * The pack's components: every row of the project set (direct picks plus
     * auto-added dependencies, deduplicated), ordered elements → blocks →
     * sections → pages, then by slug (SPEC §2.4 deterministic ordering).
     *
     * @return list<Component>
     */
    private function members(Project $project): array
    {
        return $this->closureZip->order($project->components()->get()->all());
    }

    /**
     * Merged `package.json` snippet: the closure's resolved npm deps as a
     * pinned `dependencies` map, deduped by package name. Deps missing from
     * the registry are NOT invented here — the README flags them instead
     * (SPEC §2.5).
     *
     * @param  array<string, string|null>  $deps
     */
    private function packageJson(array $deps): string
    {
        $dependencies = [];

        foreach (array_filter($deps) as $pinned) {
            ['name' => $name, 'version' => $version] = $this->packageSpec($pinned);
            $dependencies[$name] = $version;
        }

        ksort($dependencies);

        return json_encode([
            'name' => 'frontendparts-pack',
            'private' => true,
            'description' => 'Dependency snippet for your FrontendParts pack — merge `dependencies` into your app\'s package.json (or run the install command from README.md).',
            'dependencies' => (object) $dependencies,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * Split a registry pin (`lucide-react@^1.25.0`, `@scope/pkg@^2.0.0`) into
     * package name + version range.
     *
     * @return array{name: string, version: string}
     */
    private function packageSpec(string $pinned): array
    {
        $at = strrpos($pinned, '@');

        if ($at === false || $at === 0) {
            return ['name' => $pinned, 'version' => '*'];
        }

        return ['name' => substr($pinned, 0, $at), 'version' => substr($pinned, $at + 1)];
    }

    private function tailwindNotes(): string
    {
        return implode("\n", [
            '# Tailwind CSS 4 setup',
            '',
            'Every component in this pack is styled with Tailwind utilities only — no CSS files ship in the pack. Your app must have **Tailwind CSS 4** configured for them to render correctly.',
            '',
            '## Vite (React or Vue)',
            '',
            '1. Install Tailwind and the Vite plugin:',
            '',
            '   ```bash',
            '   npm install tailwindcss @tailwindcss/vite',
            '   ```',
            '',
            '2. Register the plugin in `vite.config.ts`:',
            '',
            '   ```ts',
            "   import tailwindcss from '@tailwindcss/vite';",
            '',
            '   export default defineConfig({',
            '       plugins: [tailwindcss()],',
            '   });',
            '   ```',
            '',
            '3. Import Tailwind once in your global CSS entry:',
            '',
            '   ```css',
            '   @import "tailwindcss";',
            '   ```',
            '',
            'Tailwind 4 scans your source files automatically — drop the pack\'s `components/` and `data/` folders anywhere under your source root and the utilities are picked up.',
            '',
            '## Next.js / Nuxt',
            '',
            'A fully scaffolded Next.js 15 / Nuxt 4 app (Tailwind pre-wired, routes assembled) is available on Pro plans from the project page.',
            '',
        ]);
    }

    /**
     * @param  list<Component>  $members
     * @param  list<array{path: string, component: string, note: string}>  $fileMap
     * @param  array<string, string|null>  $deps
     */
    private function readme(Project $project, array $members, array $fileMap, array $deps, string $framework): string
    {
        $frameworkLabel = $framework === 'vue' ? 'Vue 3 (SFC)' : 'React (TSX)';
        $count = count($members);

        $lines = [
            "# {$project->name}",
            '',
            "{$frameworkLabel} sources for the {$count} ".($count === 1 ? 'component' : 'components')." in your FrontendParts project **{$project->name}** — your picks plus their full composition closure.",
            '',
            '## Files',
            '',
        ];

        foreach ($fileMap as $file) {
            $lines[] = "- `{$file['path']}` — {$file['component']} ({$file['note']})";
        }

        $lines[] = '- `package.json` — merged npm dependency snippet for the whole closure';
        $lines[] = '- `TAILWIND.md` — Tailwind CSS 4 setup notes';
        $lines[] = '';
        $lines[] = '## Dependencies';
        $lines[] = '';

        $pinned = array_filter($deps);
        $unresolved = array_keys(array_filter($deps, fn (?string $package): bool => $package === null));

        if ($pinned === [] && $unresolved === []) {
            $lines[] = 'No npm dependencies — this pack is zero-dep (framework + Tailwind only).';
        } else {
            if ($pinned !== []) {
                $lines[] = 'Merge the `dependencies` block from `package.json` into your app, or install directly:';
                $lines[] = '';
                $lines[] = '```bash';
                $lines[] = 'npm install '.implode(' ', $pinned);
                $lines[] = '```';
            }

            foreach ($unresolved as $dep) {
                $lines[] = "- ⚠️ `{$dep}` is not in the FrontendParts dependency registry — resolve its package manually.";
            }
        }

        $lines[] = '';
        $lines[] = '## Requirements';
        $lines[] = '';
        $lines[] = '- **Tailwind CSS 4** must be configured in your project — see `TAILWIND.md`.';
        $lines[] = '- **Import order: elements → blocks → sections → pages.** Parents import their children; wire imports so a level never imports from a higher one.';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
