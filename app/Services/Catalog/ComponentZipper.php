<?php

namespace App\Services\Catalog;

use App\Models\Component;
use ZipArchive;

/**
 * Single-component export zip (SPEC §2.4, §6.1): the component plus its full
 * transitive closure, one authored source file per component organized by
 * level, per-component sample data as importable TS modules under `data/`,
 * and a README with the file map + resolved dependency install instructions.
 * Assembly (source entries, data modules, import rewriting, dep resolution)
 * lives in the shared ClosureZip kernel; this class adds the single-component
 * README and the ZipArchive plumbing.
 */
class ComponentZipper
{
    public function __construct(
        private readonly ClosureZip $closureZip = new ClosureZip,
    ) {}

    /**
     * Build the closure zip into a temp file and return its path. The caller
     * deletes the file after streaming (`deleteFileAfterSend`).
     */
    public function build(Component $component, string $framework): string
    {
        $members = $this->closure($component);

        $path = (string) tempnam(sys_get_temp_dir(), 'fp-component-');

        ['entries' => $entries, 'fileMap' => $fileMap] = $this->closureZip->entries($members, $framework);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($entries as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        $deps = $this->closureZip->resolveDeps($members, $framework);

        $zip->addFromString('README.md', $this->readme($component, $fileMap, $deps, $framework));
        $zip->close();

        return $path;
    }

    /**
     * The component plus its transitive descendants (SPEC §2.2), deduplicated
     * and ordered elements → blocks → sections → pages, then by slug
     * (SPEC §2.4 deterministic ordering).
     *
     * @return list<Component>
     */
    private function closure(Component $component): array
    {
        return $this->closureZip->order(
            Component::query()
                ->whereIn('id', [$component->id, ...$component->descendantIds()])
                ->get()
                ->all()
        );
    }

    /**
     * @param  list<array{path: string, component: string, note: string}>  $fileMap
     * @param  array<string, string|null>  $deps
     */
    private function readme(Component $component, array $fileMap, array $deps, string $framework): string
    {
        $frameworkLabel = $framework === 'vue' ? 'Vue 3 (SFC)' : 'React (TSX)';

        $lines = [
            "# {$component->name}",
            '',
            "{$frameworkLabel} sources for **{$component->name}** and its composition closure, exported from FrontendParts.",
            '',
        ];

        if ($component->source_url !== null) {
            $label = $component->source_name ?? $component->source_url;
            $lines[] = "Layout reference: [{$label}]({$component->source_url}) — recreation for study/production use, see the FrontendParts license.";
            $lines[] = '';
        }

        $lines[] = '## Files';
        $lines[] = '';

        foreach ($fileMap as $file) {
            $lines[] = "- `{$file['path']}` — {$file['component']} ({$file['note']})";
        }

        $lines[] = '';
        $lines[] = '## Dependencies';
        $lines[] = '';

        $pinned = array_filter($deps);
        $unresolved = array_keys(array_filter($deps, fn (?string $package): bool => $package === null));

        if ($pinned === [] && $unresolved === []) {
            $lines[] = 'No npm dependencies — this closure is zero-dep (framework + Tailwind only).';
        } else {
            if ($pinned !== []) {
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
        $lines[] = '- **Tailwind CSS 4** must be configured in your project — components are styled with Tailwind utilities only.';
        $lines[] = '- **Import order: elements → blocks → sections → pages.** Parents import their children; wire imports so a level never imports from a higher one.';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
