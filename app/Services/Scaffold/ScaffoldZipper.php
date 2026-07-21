<?php

namespace App\Services\Scaffold;

use App\Enums\ComponentLevel;
use App\Models\Component;
use App\Models\Project;
use App\Services\Catalog\ClosureZip;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

/**
 * Starter scaffold kernel (SPEC §6.3, FR-5): turns a project into a complete
 * runnable framework starter — the closure's `components/` + `data/` modules
 * assembled by the shared ClosureZip kernel (import rewriting identical to
 * the pack zip), framework starter files around them, page-level components
 * mapped to routes and the loose selected sections assembled into the index
 * page in selection order. Sample images stay remote URLs (FR-5.4): data
 * modules ship verbatim, nothing is downloaded into `public/`.
 *
 * This abstract class holds everything framework-agnostic — member
 * partitioning, pick naming/dedupe, zip writing — so each framework (Next.js
 * now, Nuxt next) only contributes its starter files, page/route modules,
 * package.json baseline and README via the abstract template methods.
 */
abstract class ScaffoldZipper
{
    public function __construct(
        protected readonly ClosureZip $closureZip = new ClosureZip,
    ) {}

    /**
     * The scaffold framework value stored on the export row (`next`).
     */
    abstract public function scaffoldFramework(): string;

    /**
     * The library source framework the starter embeds (`react` for Next).
     */
    abstract public function sourceFramework(): string;

    /**
     * Resolve the zipper for a scaffold export's framework value.
     */
    final public static function for(string $framework): self
    {
        return match ($framework) {
            'next' => app(NextScaffoldZipper::class),
            default => throw new InvalidArgumentException("Unknown scaffold framework [{$framework}]."),
        };
    }

    /**
     * Build the starter zip into a temp file and return its path. The caller
     * (BuildProjectScaffold) moves it onto the exports disk.
     */
    final public function build(Project $project): string
    {
        $members = $this->closureZip->order($project->components()->get()->all());

        ['entries' => $entries, 'fileMap' => $fileMap, 'dataMap' => $dataMap] = $this->closureZip->entries($members, $this->sourceFramework());

        $deps = $this->closureZip->resolveDeps($members, $this->sourceFramework());

        $pages = array_values(array_filter(
            $members,
            fn (Component $member): bool => $member->level === ComponentLevel::Page,
        ));

        $loose = $this->looseSelections($project);

        $entries = [
            ...$entries,
            ...$this->staticEntries($project),
            ...$this->layoutEntries($project),
            ...$this->indexEntries($project, $this->picks($loose, $dataMap)),
            'package.json' => $this->packageJson($project, $deps),
            'README.md' => $this->readme($project, $pages, $loose, $fileMap, $deps),
        ];

        foreach ($pages as $page) {
            $entries = [...$entries, ...$this->routeEntries($page, $this->picks([$page], $dataMap)[0])];
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'fp-scaffold-');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        // Static asset root — sample images stay remote (FR-5.4), so the
        // starter ships it empty but ready.
        $zip->addEmptyDir('public');

        foreach ($entries as $entry => $contents) {
            $zip->addFromString($entry, $contents);
        }

        $zip->close();

        return $path;
    }

    /**
     * Loose selections (SPEC §6.3): the user's direct picks that are not
     * page-level components, in selection order — the order rows were added
     * to `project_components`. Auto-added closure members are not selections
     * and never land on the index page.
     *
     * @return list<Component>
     */
    final protected function looseSelections(Project $project): array
    {
        return $project->directComponents()
            ->where('components.level', '!=', ComponentLevel::Page->value)
            ->orderBy('project_components.id')
            ->get()
            ->all();
    }

    /**
     * Map components to their index/route-page import faces: a unique
     * PascalCase local name, the zip path of the component source and of its
     * sample-data module (both extensionless — each framework resolves them
     * to specifiers relative to the importing file). Basename collisions
     * across levels get the level directory prefixed, mirroring the data
     * module naming (ClosureZip::uniqueDataName).
     *
     * @param  list<Component>  $members
     * @param  array<string, string>  $dataMap  slug → `data/` entry path
     * @return list<array{component: Component, name: string, source: string, data: string|null}>
     */
    final protected function picks(array $members, array $dataMap): array
    {
        $used = [];
        $picks = [];

        foreach ($members as $member) {
            $name = Str::studly($member->basename);

            if (in_array($name, $used, true)) {
                $name = Str::studly($member->level->directory().'-'.$member->basename);
            }

            $used[] = $name;

            $picks[] = [
                'component' => $member,
                'name' => $name,
                'source' => "components/{$member->level->directory()}/".Str::studly($member->basename),
                'data' => isset($dataMap[$member->slug])
                    ? (string) preg_replace('/\.ts$/', '', $dataMap[$member->slug])
                    : null,
            ];
        }

        return $picks;
    }

    /**
     * Relative import specifier from the importing file's zip location to an
     * extensionless zip path (`../components/sections/FeatureGrid01`).
     * Mirrors ClosureZip's zip-layout specifier rule.
     */
    final protected function specifier(string $fromZipEntry, string $toZipPath): string
    {
        $fromSegments = explode('/', dirname($fromZipEntry));
        $toSegments = explode('/', $toZipPath);

        while ($fromSegments !== [] && $toSegments !== [] && $fromSegments[0] === $toSegments[0]) {
            array_shift($fromSegments);
            array_shift($toSegments);
        }

        $ups = str_repeat('../', count($fromSegments));

        return ($ups === '' ? './' : $ups).implode('/', $toSegments);
    }

    /**
     * Split a registry pin (`lucide-react@^1.25.0`, `@scope/pkg@^2.0.0`) into
     * package name + version range.
     *
     * @return array{name: string, version: string}
     */
    final protected function packageSpec(string $pinned): array
    {
        $at = strrpos($pinned, '@');

        if ($at === false || $at === 0) {
            return ['name' => $pinned, 'version' => '*'];
        }

        return ['name' => substr($pinned, 0, $at), 'version' => substr($pinned, $at + 1)];
    }

    /**
     * Static starter files (configs, global CSS, ignore rules), zip path →
     * contents.
     *
     * @return array<string, string>
     */
    abstract protected function staticEntries(Project $project): array;

    /**
     * Root layout entries wrapping every page, zip path → contents.
     *
     * @return array<string, string>
     */
    abstract protected function layoutEntries(Project $project): array;

    /**
     * The index page assembled from the loose selections in selection order,
     * zip path → contents. Always emitted — a project of only page-level
     * picks still gets a valid index.
     *
     * @param  list<array{component: Component, name: string, source: string, data: string|null}>  $picks
     * @return array<string, string>
     */
    abstract protected function indexEntries(Project $project, array $picks): array;

    /**
     * Route entries for one page-level component (App Router route, Nuxt
     * page), zip path → contents.
     *
     * @param  array{component: Component, name: string, source: string, data: string|null}  $pick
     * @return array<string, string>
     */
    abstract protected function routeEntries(Component $page, array $pick): array;

    /**
     * The starter's package.json: framework baseline deps merged with the
     * closure's registry-resolved npm deps, deduped by package name.
     *
     * @param  array<string, string|null>  $deps
     */
    abstract protected function packageJson(Project $project, array $deps): string;

    /**
     * The starter README: structure, routes, run steps, dependency notes.
     *
     * @param  list<Component>  $pages
     * @param  list<Component>  $loose
     * @param  list<array{path: string, component: string, note: string}>  $fileMap
     * @param  array<string, string|null>  $deps
     */
    abstract protected function readme(Project $project, array $pages, array $loose, array $fileMap, array $deps): string;
}
