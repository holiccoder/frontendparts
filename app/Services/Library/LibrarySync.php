<?php

namespace App\Services\Library;

use App\Enums\CategoryType;
use App\Jobs\BuildComponentPreview;
use App\Models\Category;
use App\Models\Component;
use App\Models\LibrarySyncRun;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * library:sync orchestrator (SPEC §8.3): scan folders → parse annotations →
 * validate → upsert DB → queue preview builds for changed components and
 * their dependents. Every run is recorded in library_sync_runs.
 */
class LibrarySync
{
    public function __construct(
        private readonly ComponentScanner $scanner = new ComponentScanner,
        private readonly CompositionGraph $graph = new CompositionGraph,
        private readonly ParamsValidator $paramsValidator = new ParamsValidator,
    ) {}

    public function run(): SyncResult
    {
        $reactPath = (string) config('library.react_path');
        $vuePath = (string) config('library.vue_path');
        $registry = $this->loadRegistry((string) config('library.registry_path'));

        $react = $this->scanner->scan($reactPath, 'react');
        $vue = $this->scanner->scan($vuePath, 'vue');

        $slugs = array_values(array_unique([...array_keys($react), ...array_keys($vue)]));
        sort($slugs);

        /** @var array<string, list<string>> $status */
        $status = array_fill_keys($slugs, []);

        if ($registry === null) {
            foreach ($slugs as $slug) {
                $status[$slug][] = 'deps registry is missing or invalid';
            }

            return $this->finish(count($slugs), 0, $status);
        }

        $this->validateComponents($slugs, $react, $vue, $registry, $status);

        $edgesBySlug = $this->graphEdges($react, $vue, $reactPath, $vuePath, $status);

        $this->validateChildSlices($react, $vue, $edgesBySlug, $status);

        [$upserted, $changedIds] = $this->upsert($slugs, $react, $vue, $edgesBySlug, $status);

        $rebuiltIds = $this->queueRebuilds($changedIds);

        return $this->finish(count($slugs), $upserted, $status, $rebuiltIds);
    }

    /**
     * @return array<string, array{react: string, vue: string}>|null
     */
    private function loadRegistry(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, ParsedComponent>  $react
     * @param  array<string, ParsedComponent>  $vue
     * @param  array<string, mixed>  $registry
     * @param  array<string, list<string>>  $status
     */
    private function validateComponents(array $slugs, array $react, array $vue, array $registry, array &$status): void
    {
        foreach ($slugs as $slug) {
            $reactSide = $react[$slug] ?? null;
            $vueSide = $vue[$slug] ?? null;

            if ($reactSide === null) {
                $status[$slug][] = 'Missing react twin';
            }

            if ($vueSide === null) {
                $status[$slug][] = 'Missing vue twin';
            }

            $canonical = $reactSide ?? $vueSide;

            foreach (array_filter([$reactSide, $vueSide]) as $side) {
                foreach ($side->errors as $error) {
                    $status[$slug][] = "{$side->framework}: {$error}";
                }
            }

            if ($canonical === null) {
                continue;
            }

            $this->validateTaxonomy($canonical, $status[$slug]);

            foreach (array_filter([$reactSide, $vueSide]) as $side) {
                foreach ($this->paramsValidator->validateSchema($side->params, "{$side->framework} params.json") as $error) {
                    $status[$slug][] = $error;
                }

                foreach ($this->paramsValidator->validateData($side->data, $side->params, "{$side->framework} data.json") as $error) {
                    $status[$slug][] = $error;
                }
            }

            foreach ($canonical->deps as $dep) {
                if (! array_key_exists($dep, $registry)) {
                    $status[$slug][] = "Dep '{$dep}' is not in deps.registry.json";
                }
            }
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function validateTaxonomy(ParsedComponent $component, array &$errors): void
    {
        $usageExists = Category::query()
            ->where('type', CategoryType::Usage->value)
            ->where('slug', $component->usage)
            ->exists();

        if (! $usageExists) {
            $errors[] = "Unknown usage category '{$component->usage}'";
        }

        foreach ($component->industries as $industry) {
            $industryExists = Category::query()
                ->where('type', CategoryType::Industry->value)
                ->where('slug', $industry)
                ->exists();

            if (! $industryExists) {
                $errors[] = "Unknown industry '{$industry}'";
            }
        }
    }

    /**
     * Derive composition edges from both trees and validate cycles / depth.
     *
     * @param  array<string, ParsedComponent>  $react
     * @param  array<string, ParsedComponent>  $vue
     * @param  array<string, list<string>>  $status
     * @return array<string, list<string>> canonical (react-preferred) edges per slug
     */
    private function graphEdges(array $react, array $vue, string $reactPath, string $vuePath, array &$status): array
    {
        $maxDepth = (int) config('library.max_depth', 10);

        $reactEdges = $this->graph->edges($react, $reactPath);
        $vueEdges = $this->graph->edges($vue, $vuePath);

        foreach (['react' => $reactEdges, 'vue' => $vueEdges] as $framework => $edges) {
            foreach ($this->graph->validate($edges, $maxDepth) as $slug => $messages) {
                foreach ($messages as $message) {
                    $status[$slug][] = "{$framework}: {$message}";
                }
            }
        }

        $canonical = [];

        foreach (array_keys($status) as $slug) {
            $canonical[$slug] = $reactEdges[$slug] ?? $vueEdges[$slug] ?? [];
        }

        return $canonical;
    }

    /**
     * Child-slice rule (SPEC §3.3): a composite's data.json may carry a
     * `children` key mapping child slug → object or array of objects; every
     * slice must validate against that child's params.json schema.
     *
     * @param  array<string, ParsedComponent>  $react
     * @param  array<string, ParsedComponent>  $vue
     * @param  array<string, list<string>>  $edgesBySlug
     * @param  array<string, list<string>>  $status
     */
    private function validateChildSlices(array $react, array $vue, array $edgesBySlug, array &$status): void
    {
        foreach (['react' => $react, 'vue' => $vue] as $framework => $components) {
            foreach ($components as $slug => $component) {
                $children = $component->data['children'] ?? null;

                if ($children === null) {
                    continue;
                }

                if (! is_array($children)) {
                    $status[$slug][] = "{$framework} data.json: 'children' must map child slug → object or array of objects";

                    continue;
                }

                foreach ($children as $childSlug => $slices) {
                    $childFullSlug = $this->matchChild($edgesBySlug[$slug] ?? [], (string) $childSlug);

                    if ($childFullSlug === null) {
                        $status[$slug][] = "{$framework} data.json: children.{$childSlug} does not match any imported child component";

                        continue;
                    }

                    $childParams = $components[$childFullSlug]->params ?? [];

                    foreach ($this->normalizeSlices($slices) as $index => $slice) {
                        if (! is_array($slice)) {
                            $status[$slug][] = "{$framework} data.json: children.{$childSlug} slice {$index} must be an object";

                            continue;
                        }

                        $context = "{$framework} data.json → children.{$childSlug}".(is_int($index) ? "[{$index}]" : '');

                        foreach ($this->paramsValidator->validateData($slice, $childParams, $context) as $error) {
                            $status[$slug][] = $error;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param  list<string>  $childFullSlugs
     */
    private function matchChild(array $childFullSlugs, string $childSlug): ?string
    {
        foreach ($childFullSlugs as $fullSlug) {
            if ($fullSlug === $childSlug || Str::after($fullSlug, '/') === $childSlug) {
                return $fullSlug;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeSlices(mixed $slices): array
    {
        if (! is_array($slices)) {
            return [$slices];
        }

        if (array_is_list($slices)) {
            return array_values($slices);
        }

        return [$slices];
    }

    /**
     * @param  array<string, ParsedComponent>  $react
     * @param  array<string, ParsedComponent>  $vue
     * @param  array<string, list<string>>  $edgesBySlug
     * @param  array<string, list<string>>  $status
     * @return array{0: int, 1: list<int>} upserted count and changed component ids
     */
    private function upsert(array $slugs, array $react, array $vue, array $edgesBySlug, array $status): array
    {
        $upserted = 0;
        $changedIds = [];
        $models = [];

        foreach ($slugs as $slug) {
            if ($status[$slug] !== []) {
                continue;
            }

            $canonical = $react[$slug] ?? $vue[$slug];
            $hash = hash('sha256', collect([$react[$slug] ?? null, $vue[$slug] ?? null])
                ->filter()
                ->map(fn (ParsedComponent $side): string => $side->sourceHash())
                ->implode('|'));

            $usageCategory = Category::query()
                ->where('type', CategoryType::Usage->value)
                ->where('slug', $canonical->usage)
                ->firstOrFail();

            $model = Component::query()->where('slug', $slug)->first();
            $changed = $model === null || $model->source_hash !== $hash;

            $attributes = [
                'name' => $canonical->name,
                'level' => $canonical->level,
                'usage_category_id' => $usageCategory->id,
                'access_level' => $canonical->access,
                'version' => $canonical->version,
                'source_url' => $canonical->sourceUrl,
                'deps' => $canonical->deps === [] ? null : $canonical->deps,
                'source_hash' => $hash,
            ];

            if ($model === null) {
                $model = Component::query()->create([...$attributes, 'slug' => $slug]);
            } else {
                $model->update($attributes);
            }

            $industryIds = Category::query()
                ->where('type', CategoryType::Industry->value)
                ->whereIn('slug', $canonical->industries)
                ->pluck('id');

            $model->industries()->sync($industryIds);

            $tagIds = collect($canonical->tags)
                ->map(fn (string $tag): Tag => Tag::query()->firstOrCreate(
                    ['slug' => $tag],
                    ['name' => Str::headline(str_replace('-', ' ', $tag))],
                ))
                ->pluck('id');

            $model->tags()->sync($tagIds);

            $models[$slug] = $model;
            $upserted++;

            if ($changed) {
                $changedIds[] = $model->id;
            }
        }

        $this->replaceChildEdges($models, $edgesBySlug);

        return [$upserted, $changedIds];
    }

    /**
     * Replace component_children edges for each upserted parent. Children that
     * failed validation (and are not already in the DB) are skipped.
     *
     * @param  array<string, Component>  $models
     * @param  array<string, list<string>>  $edgesBySlug
     */
    private function replaceChildEdges(array $models, array $edgesBySlug): void
    {
        $idsBySlug = Component::query()->pluck('id', 'slug');

        foreach ($models as $slug => $model) {
            DB::table('component_children')->where('parent_id', $model->id)->delete();

            foreach (array_values(array_unique($edgesBySlug[$slug] ?? [])) as $sortOrder => $childSlug) {
                $childId = $idsBySlug[$childSlug] ?? null;

                if ($childId === null || $childId === $model->id) {
                    continue;
                }

                DB::table('component_children')->insert([
                    'parent_id' => $model->id,
                    'child_id' => $childId,
                    'slot' => null,
                    'sort_order' => $sortOrder,
                ]);
            }
        }
    }

    /**
     * Queue preview builds for changed components and all transitive parents
     * (dependents), per framework (SPEC §5.2, §8.3).
     *
     * @param  list<int>  $changedIds
     * @return list<int>
     */
    private function queueRebuilds(array $changedIds): array
    {
        if ($changedIds === []) {
            return [];
        }

        $affected = array_values($changedIds);
        $frontier = $changedIds;

        while ($frontier !== []) {
            $parents = DB::table('component_children')
                ->whereIn('child_id', $frontier)
                ->pluck('parent_id')
                ->all();

            $parents = array_values(array_diff($parents, $affected));
            $affected = [...$affected, ...$parents];
            $frontier = $parents;
        }

        foreach ($affected as $componentId) {
            BuildComponentPreview::dispatch($componentId, ['react', 'vue']);
        }

        return $affected;
    }

    /**
     * @param  array<string, list<string>>  $status
     * @param  list<int>  $rebuiltIds
     */
    private function finish(int $scanned, int $upserted, array $status, array $rebuiltIds = []): SyncResult
    {
        $result = new SyncResult($scanned, $upserted, $status, $rebuiltIds);

        LibrarySyncRun::query()->create([
            'scanned' => $result->scanned,
            'upserted' => $result->upserted,
            'errors' => $result->failures() === [] ? null : $result->failures(),
        ]);

        return $result;
    }
}
