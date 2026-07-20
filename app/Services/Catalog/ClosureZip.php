<?php

namespace App\Services\Catalog;

use App\Enums\ComponentLevel;
use App\Models\Component;
use App\Services\Library\CompositionGraph;
use Illuminate\Support\Str;

/**
 * Zip assembly kernel shared by the single-component export
 * (Catalog\ComponentZipper, SPEC §2.4) and the project pack zip export
 * (Projects\ProjectPackZipper, SPEC §6.2): given a deduplicated closure of
 * components, produces the `components/{level}/…` source entries (with import
 * specifiers rewritten into the zip layout so the export compiles as-is) and
 * the `data/` sample-data modules, and resolves the closure's logical `@deps`
 * against the dependency registry (SPEC §2.5). Sources are the library files
 * verbatim — preview instrumentation (`data-fp-*`) is added at preview-build
 * time and never touches the library tree, so exports are clean by
 * construction. The ONLY transformation is import rewriting.
 */
class ClosureZip
{
    public function __construct(
        private readonly ComponentContent $content = new ComponentContent,
        private readonly CompositionGraph $graph = new CompositionGraph,
    ) {}

    /**
     * Source + data entries for one framework over the given closure.
     *
     * @param  list<Component>  $members  deduplicated closure, already ordered
     *                                    by {@see order()}
     * @return array{entries: array<string, string>, fileMap: list<array{path: string, component: string, note: string}>}
     */
    public function entries(array $members, string $framework): array
    {
        $membersBySlug = collect($members)->keyBy(fn (Component $member): string => $member->slug)->all();
        $extension = $framework === 'vue' ? 'vue' : 'tsx';

        $entries = [];

        /** @var list<array{path: string, component: string, note: string}> $fileMap */
        $fileMap = [];

        /** @var list<string> $usedDataNames */
        $usedDataNames = [];

        foreach ($members as $member) {
            $content = $this->content->for($member);
            $source = $content['files'][$framework][0]['code'] ?? null;

            if ($source !== null) {
                $entry = $this->zipSourcePath($member, $extension);
                $entries[$entry] = $this->rewriteImports($source, $member, $entry, $membersBySlug, $framework);
                $fileMap[] = ['path' => $entry, 'component' => $member->name, 'note' => $member->level->value];
            }

            if ($content['data'] !== []) {
                $dataName = $this->uniqueDataName($member, $usedDataNames);
                $entry = "data/{$dataName}.ts";
                $entries[$entry] = $this->dataModule($content['data']);
                $fileMap[] = ['path' => $entry, 'component' => $member->name, 'note' => 'sample data'];
            }
        }

        return ['entries' => $entries, 'fileMap' => $fileMap];
    }

    /**
     * Deterministic closure ordering: elements → blocks → sections → pages,
     * then by slug (SPEC §2.4).
     *
     * @param  list<Component>  $members
     * @return list<Component>
     */
    public function order(array $members): array
    {
        $rank = fn (Component $member): int => match ($member->level) {
            ComponentLevel::Element => 0,
            ComponentLevel::Block => 1,
            ComponentLevel::Section => 2,
            ComponentLevel::Page => 3,
        };

        return collect($members)
            ->sort(fn (Component $a, Component $b): int => [$rank($a), $a->slug] <=> [$rank($b), $b->slug])
            ->values()
            ->all();
    }

    /**
     * Resolve the closure's logical `@deps` to pinned packages for the chosen
     * framework via library/deps.registry.json (SPEC §2.5), deduplicated.
     * Deps missing from the registry map to null so callers can flag them
     * instead of inventing a package name.
     *
     * @param  list<Component>  $members
     * @return array<string, string|null> logical dep → pinned package
     */
    public function resolveDeps(array $members, string $framework): array
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
}
