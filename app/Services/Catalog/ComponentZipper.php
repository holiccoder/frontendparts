<?php

namespace App\Services\Catalog;

use App\Enums\ComponentLevel;
use App\Models\Component;
use App\Services\Library\CompositionGraph;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Single-component export zip (SPEC §2.4, §6.1): the component plus its full
 * transitive closure, one authored source file per component organized by
 * level, per-component sample data as importable TS modules under `data/`,
 * and a README with the file map + resolved dependency install instructions.
 * Sources are the library files verbatim — preview instrumentation
 * (`data-fp-*`) is added at preview-build time and never touches the library
 * tree, so exports are clean by construction. The ONLY transformation is
 * rewriting import specifiers that point at other closure components into
 * the zip layout, so a downloaded zip compiles as-is (P0).
 */
class ComponentZipper
{
    public function __construct(
        private readonly ComponentContent $content = new ComponentContent,
        private readonly CompositionGraph $graph = new CompositionGraph,
    ) {}

    /**
     * Build the closure zip into a temp file and return its path. The caller
     * deletes the file after streaming (`deleteFileAfterSend`).
     */
    public function build(Component $component, string $framework): string
    {
        $members = $this->closure($component);
        $membersBySlug = collect($members)->keyBy(fn (Component $member): string => $member->slug)->all();
        $extension = $framework === 'vue' ? 'vue' : 'tsx';

        $path = (string) tempnam(sys_get_temp_dir(), 'fp-component-');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        /** @var list<array{path: string, component: string, note: string}> $fileMap */
        $fileMap = [];

        /** @var list<string> $usedDataNames */
        $usedDataNames = [];

        foreach ($members as $member) {
            $content = $this->content->for($member);
            $source = $content['files'][$framework][0]['code'] ?? null;

            if ($source !== null) {
                $entry = $this->zipSourcePath($member, $extension);
                $zip->addFromString($entry, $this->rewriteImports($source, $member, $entry, $membersBySlug, $framework));
                $fileMap[] = ['path' => $entry, 'component' => $member->name, 'note' => $member->level->value];
            }

            if ($content['data'] !== []) {
                $dataName = $this->uniqueDataName($member, $usedDataNames);
                $entry = "data/{$dataName}.ts";
                $zip->addFromString($entry, $this->dataModule($content['data']));
                $fileMap[] = ['path' => $entry, 'component' => $member->name, 'note' => 'sample data'];
            }
        }

        $deps = $this->resolveDeps($members, $framework);

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
        $rank = fn (Component $member): int => match ($member->level) {
            ComponentLevel::Element => 0,
            ComponentLevel::Block => 1,
            ComponentLevel::Section => 2,
            ComponentLevel::Page => 3,
        };

        return Component::query()
            ->whereIn('id', [$component->id, ...$component->descendantIds()])
            ->get()
            ->sort(fn (Component $a, Component $b): int => [$rank($a), $a->slug] <=> [$rank($b), $b->slug])
            ->values()
            ->all();
    }

    /**
     * components/{level-directory}/{PascalName}.{tsx|vue} — the zip location
     * of one component's entry source (SPEC §2.4).
     */
    private function zipSourcePath(Component $member, string $extension): string
    {
        return "components/{$member->level->directory()}/".Str::studly($member->basename).".{$extension}";
    }

    /**
     * Rewrite ES import specifiers that resolve (via the composition graph's
     * own resolution) to another component IN THE CLOSURE into the zip
     * layout, extensionless — e.g. `../../elements/section-title-01` (or the
     * explicit `…/index.vue` form) becomes `../elements/SectionTitle01` from
     * `components/sections/TitleShowcase01.*`. npm packages, CSS, self and
     * non-closure imports stay untouched.
     *
     * @param  array<string, Component>  $membersBySlug
     */
    private function rewriteImports(string $source, Component $member, string $zipEntry, array $membersBySlug, string $framework): string
    {
        $componentsRoot = (string) config("library.{$framework}_path", '');

        if ($componentsRoot === '') {
            return $source;
        }

        $entryFile = $componentsRoot.'/'.$member->slug.($framework === 'vue' ? '/index.vue' : '/index.tsx');

        // Mirrors CompositionGraph::importPaths, split into prefix/path/suffix
        // groups so only the quoted specifier is replaced (quote style and
        // everything around it is preserved).
        $pattern = '/(?<prefix>import\s+(?:[^\'";]*?\s+from\s+)?[\'"])(?<path>[^\'"]+)(?<suffix>[\'"])/';

        return (string) preg_replace_callback(
            $pattern,
            function (array $matches) use ($member, $zipEntry, $membersBySlug, $entryFile, $componentsRoot): string {
                $resolved = $this->graph->resolveImport($matches['path'], $entryFile, $componentsRoot);

                if ($resolved === null || $resolved === $member->slug || ! isset($membersBySlug[$resolved])) {
                    return $matches[0];
                }

                $target = $this->zipSourcePath($membersBySlug[$resolved], pathinfo($zipEntry, PATHINFO_EXTENSION));

                return $matches['prefix'].$this->relativeSpecifier($zipEntry, $target).$matches['suffix'];
            },
            $source,
        );
    }

    /**
     * Relative import specifier from the exporting file's zip location to the
     * target's zip location, extensionless (`./Button` for same-directory
     * targets, `../elements/SectionTitle01` otherwise).
     */
    private function relativeSpecifier(string $fromZipEntry, string $toZipEntry): string
    {
        $target = (string) preg_replace('/\.(tsx|vue)$/', '', $toZipEntry);

        $fromSegments = explode('/', dirname($fromZipEntry));
        $toSegments = explode('/', $target);

        while ($fromSegments !== [] && $toSegments !== [] && $fromSegments[0] === $toSegments[0]) {
            array_shift($fromSegments);
            array_shift($toSegments);
        }

        $ups = str_repeat('../', count($fromSegments));

        return ($ups === '' ? './' : $ups).implode('/', $toSegments);
    }

    /**
     * `data/` entries are flat, so same-basename components from different
     * levels get the level directory prefixed to keep entry names unique.
     *
     * @param  list<string>  $used
     */
    private function uniqueDataName(Component $member, array &$used): string
    {
        $name = $member->basename;

        if (in_array($name, $used, true)) {
            $name = "{$member->level->directory()}-{$name}";
        }

        $used[] = $name;

        return $name;
    }

    /**
     * data/{kebab-slug}.ts — the component's data.json as a typed TS module.
     *
     * @param  array<string, mixed>  $data
     */
    private function dataModule(array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'export default '.$json.' as const;'."\n";
    }

    /**
     * Resolve the closure's logical `@deps` to pinned packages for the chosen
     * framework via library/deps.registry.json (SPEC §2.5). Deps missing from
     * the registry map to null so the README can flag them instead of
     * inventing a package name.
     *
     * @param  list<Component>  $members
     * @return array<string, string|null> logical dep → pinned package
     */
    private function resolveDeps(array $members, string $framework): array
    {
        $logical = collect($members)
            ->flatMap(fn (Component $member): array => $member->deps ?? [])
            ->unique()
            ->sort()
            ->values();

        $registry = [];
        $registryPath = (string) config('library.registry_path', '');

        if ($registryPath !== '' && is_file($registryPath)) {
            $decoded = json_decode((string) file_get_contents($registryPath), true);
            $registry = is_array($decoded) ? $decoded : [];
        }

        return $logical
            ->mapWithKeys(fn (string $dep): array => [$dep => $registry[$dep][$framework] ?? null])
            ->all();
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
