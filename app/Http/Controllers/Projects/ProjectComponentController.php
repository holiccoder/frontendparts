<?php

namespace App\Http\Controllers\Projects;

use App\Enums\AccessLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectComponentRequest;
use App\Models\Component;
use App\Models\Project;
use App\Services\Billing\EntitlementService;
use App\Services\Projects\ProjectComponentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Project component-set endpoints (SPEC §6.1): add-to-project with the
 * auto-add descendant closure, and direct-pick removal with the orphan
 * pruning cascade. Answers JSON for API consumers (catalog "Add to project"
 * UI) and redirects with flashes for the Inertia dashboard pages.
 *
 * Gating (SPEC §7.1, FR-7.6): Free-plan users may only add `free` components;
 * paid components answer a 403 upgrade payload in the established shape.
 */
class ProjectComponentController extends Controller
{
    public function store(StoreProjectComponentRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $component = Component::query()->findOrFail($request->validated('component_id'));

        if ($component->access_level === AccessLevel::Paid
            && ! app(EntitlementService::class)->for($request->user())->hasFullLibrary()) {
            return response()->json([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to add paid components to projects',
                    'pricing_url' => '/pricing',
                ],
            ], 403);
        }

        app(ProjectComponentService::class)->add($project, $component);

        if ($request->expectsJson()) {
            return response()->json([
                'added' => [
                    'id' => $component->id,
                    'slug' => $component->slug,
                    'name' => $component->name,
                ],
                'components_count' => $project->components()->count(),
            ], 201);
        }

        return back();
    }

    public function destroy(Request $request, Project $project, Component $component): RedirectResponse|JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        // Only direct picks are removable; dependencies follow the cascade.
        $isDirectPick = DB::table('project_components')
            ->where('project_id', $project->id)
            ->where('component_id', $component->id)
            ->where('is_dependency', false)
            ->exists();

        abort_unless($isDirectPick, 404);

        $pruned = app(ProjectComponentService::class)->remove($project, $component);

        $notice = $pruned->isEmpty()
            ? "Removed {$component->name}."
            : "Removed {$component->name} and pruned {$pruned->count()} unused "
                .Str::plural('dependency', $pruned->count()).': '
                .$pruned->pluck('name')->join(', ').'.';

        if ($request->expectsJson()) {
            return response()->json([
                'removed' => $component->id,
                'pruned' => $pruned
                    ->map(fn (Component $dependency): array => [
                        'id' => $dependency->id,
                        'slug' => $dependency->slug,
                        'name' => $dependency->name,
                    ])
                    ->values(),
                'notice' => $notice,
            ]);
        }

        return back()->with('notice', $notice);
    }
}
