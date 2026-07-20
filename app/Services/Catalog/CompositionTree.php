<?php

namespace App\Services\Catalog;

use App\Models\Component;
use Illuminate\Support\Facades\DB;

/**
 * Composition tree payload (SPEC §5.5) built from the component_children
 * graph: recursive {slug, basename, usage, name, level, instances, children}.
 * Sibling pivot rows pointing at the same child collapse into one node whose
 * `instances` count drives the "×n → #1 #2 #3" chips. `basename` + `usage`
 * let the tree link straight to each child's component page. Depth-capped at
 * config('library.max_depth') and cycle-guarded so a malformed graph can
 * never recurse forever.
 */
class CompositionTree
{
    /**
     * @return array{slug: string, basename: string, usage: string, name: string, level: string, instances: int, children: list<array>}
     */
    public function for(Component $component): array
    {
        return [
            'slug' => $component->slug,
            'basename' => $component->basename,
            'usage' => $component->usageCategory->slug,
            'name' => $component->name,
            'level' => $component->level->value,
            'instances' => 1,
            'children' => $this->childrenOf($component->id, [$component->id]),
        ];
    }

    /**
     * @param  list<int>  $path  ancestor ids on the current branch (cycle guard)
     * @return list<array{slug: string, basename: string, usage: string, name: string, level: string, instances: int, children: list<array>}>
     */
    private function childrenOf(int $parentId, array $path, int $depth = 1): array
    {
        if ($depth >= (int) config('library.max_depth', 10)) {
            return [];
        }

        $rows = DB::table('component_children')
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->get(['child_id']);

        $grouped = $rows->groupBy('child_id');

        if ($grouped->isEmpty()) {
            return [];
        }

        $children = Component::query()
            ->whereIn('id', $grouped->keys()->all())
            ->with('usageCategory')
            ->get()
            ->keyBy('id');

        $nodes = [];

        foreach ($grouped as $childId => $instances) {
            $child = $children->get((int) $childId);

            if ($child === null || in_array($child->id, $path, true)) {
                continue;
            }

            $nodes[] = [
                'slug' => $child->slug,
                'basename' => $child->basename,
                'usage' => $child->usageCategory->slug,
                'name' => $child->name,
                'level' => $child->level->value,
                'instances' => $instances->count(),
                'children' => $this->childrenOf($child->id, [...$path, $child->id], $depth + 1),
            ];
        }

        return $nodes;
    }
}
