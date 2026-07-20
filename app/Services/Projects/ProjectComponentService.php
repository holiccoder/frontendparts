<?php

namespace App\Services\Projects;

use App\Models\Component;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Project component-set mutations (SPEC §6.1).
 *
 * Auto-add closure: adding a component inserts it as a direct pick
 * (`is_dependency = false`) plus every member of its descendant closure as a
 * dependency (`is_dependency = true`), deduplicated — rows already present
 * (shared children between composites) are left untouched. A dependency the
 * user later adds directly is promoted to a direct pick.
 *
 * Removal cascade: removing a direct pick deletes it, then prunes every
 * dependency that is no longer part of ANY remaining direct pick's closure;
 * dependencies still used elsewhere stay.
 */
class ProjectComponentService
{
    public function add(Project $project, Component $component): void
    {
        $descendantIds = $component->descendantIds();

        $present = DB::table('project_components')
            ->where('project_id', $project->id)
            ->whereIn('component_id', [$component->id, ...$descendantIds])
            ->pluck('component_id')
            ->all();

        // Promote a row the user previously received as a dependency to an
        // explicit direct pick; otherwise insert the direct pick fresh.
        if (in_array($component->id, $present, true)) {
            DB::table('project_components')
                ->where('project_id', $project->id)
                ->where('component_id', $component->id)
                ->update(['is_dependency' => false, 'updated_at' => now()]);
        } else {
            $project->components()->attach($component->id, ['is_dependency' => false]);
        }

        $missing = array_diff($descendantIds, $present, [$component->id]);

        if ($missing === []) {
            return;
        }

        $now = now();

        DB::table('project_components')->insert(
            array_map(fn (int $id): array => [
                'project_id' => $project->id,
                'component_id' => $id,
                'is_dependency' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_values($missing)),
        );
    }

    /**
     * Remove a direct pick and prune its now-orphaned dependencies.
     *
     * @return Collection<int, Component> the pruned dependencies, for the
     *                                    user notice (SPEC §6.1 removal cascade)
     */
    public function remove(Project $project, Component $component): Collection
    {
        DB::table('project_components')
            ->where('project_id', $project->id)
            ->where('component_id', $component->id)
            ->delete();

        $prunedIds = DB::table('project_components')
            ->where('project_id', $project->id)
            ->where('is_dependency', true)
            ->whereNotIn('component_id', $this->requiredDependencyIds($project))
            ->pluck('component_id');

        if ($prunedIds->isEmpty()) {
            return new Collection;
        }

        DB::table('project_components')
            ->where('project_id', $project->id)
            ->whereIn('component_id', $prunedIds)
            ->delete();

        return Component::query()->whereIn('id', $prunedIds)->get();
    }

    /**
     * Union of the descendant closures of every remaining direct pick — the
     * dependency rows the project still needs.
     *
     * @return list<int>
     */
    private function requiredDependencyIds(Project $project): array
    {
        $directIds = DB::table('project_components')
            ->where('project_id', $project->id)
            ->where('is_dependency', false)
            ->pluck('component_id')
            ->all();

        $required = [];

        foreach (Component::query()->whereIn('id', $directIds)->get() as $direct) {
            $required = [...$required, ...$direct->descendantIds()];
        }

        return array_values(array_unique($required));
    }
}
