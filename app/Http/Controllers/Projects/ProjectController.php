<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectExportKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Component;
use App\Models\ComponentFork;
use App\Models\Project;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Project CRUD (SPEC §6.1, §15.4): the dashboard project list and detail
 * pages (CSR) plus create / rename / delete. Per-plan project limits come
 * from platform settings via the entitlement (§7.1, §8.7), so admins retune
 * them without a deploy.
 */
class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $entitlement = app(EntitlementService::class)->for($request->user());

        $projects = $request->user()->projects()
            ->withCount('components')
            ->latest()
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'components_count' => $project->components_count,
                'created_at' => $project->created_at->toIso8601String(),
                'url' => route('dashboard.projects.show', $project),
            ]);

        return Inertia::render('dashboard/projects/index', [
            'projects' => $projects,
            'limits' => [
                'plan' => $entitlement->plan()->value,
                'limit' => $entitlement->projectLimit(),
                'used' => $projects->count(),
            ],
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(StoreProjectRequest $request): RedirectResponse|JsonResponse
    {
        // Resolved per request (not constructor-injected): routes memoize
        // controller instances, and limits must track settings changes.
        $entitlement = app(EntitlementService::class)->for($request->user());
        $limit = $entitlement->projectLimit();

        if ($limit !== null && $request->user()->projects()->count() >= $limit) {
            $plan = Str::headline($entitlement->plan()->value);
            $message = "Your {$plan} plan includes {$limit} ".Str::plural('project', $limit).'. Upgrade to create more projects.';

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'project_limit_reached',
                    'message' => $message,
                    'upgrade' => [
                        'cta' => 'Upgrade to create more projects',
                        'pricing_url' => '/pricing',
                    ],
                ], 422);
            }

            throw ValidationException::withMessages(['name' => $message]);
        }

        $project = $request->user()->projects()->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'url' => route('dashboard.projects.show', $project),
                ],
            ], 201);
        }

        return to_route('dashboard.projects.show', $project);
    }

    public function show(Request $request, Project $project): Response
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $components = $project->components()
            ->with('usageCategory')
            ->orderBy('project_components.is_dependency')
            ->orderBy('components.name')
            ->get()
            ->map(fn (Component $component): array => [
                'id' => $component->id,
                'slug' => $component->slug,
                'basename' => $component->basename,
                'name' => $component->name,
                'level' => $component->level->value,
                'access_level' => $component->access_level->value,
                'is_dependency' => (bool) $component->pivot->is_dependency,
                'url' => $component->publicUrl(),
            ]);

        $latestExport = $project->exports()
            ->where('kind', ProjectExportKind::Pack->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $latestScaffold = $project->exports()
            ->where('kind', ProjectExportKind::Scaffold->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $forks = $project->forks()
            ->with('component')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ComponentFork $fork): array => [
                'id' => $fork->id,
                'name' => $fork->component->name,
                'slug' => $fork->component->slug,
                'url' => $fork->component->publicUrl(),
                'framework' => $fork->framework,
                'status' => $fork->status->value,
                'error' => $fork->error,
                'preview_url' => $fork->previewUrl(),
                'created_at' => $fork->created_at->toIso8601String(),
            ]);

        return Inertia::render('dashboard/projects/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'created_at' => $project->created_at->toIso8601String(),
            ],
            'components' => $components,
            // Pack zip export (SPEC §6.2): POST queues the build; the page
            // polls this prop until `latest.status` flips to ready.
            'export' => [
                'url' => route('dashboard.projects.export', $project),
                'available' => true,
                'latest' => $latestExport === null ? null : [
                    'id' => $latestExport->id,
                    'status' => $latestExport->status->value,
                    'framework' => $latestExport->framework,
                    'download_url' => $latestExport->downloadUrl(),
                ],
            ],
            // Starter scaffold (SPEC §6.3): Pro-only; same queued-build →
            // poll → stream flow as the pack zip.
            'scaffold' => [
                'url' => route('dashboard.projects.scaffold', $project),
                'available' => app(EntitlementService::class)->for($request->user())->canScaffold(),
                'latest' => $latestScaffold === null ? null : [
                    'id' => $latestScaffold->id,
                    'status' => $latestScaffold->status->value,
                    'framework' => $latestScaffold->framework,
                    'download_url' => $latestScaffold->downloadUrl(),
                ],
            ],
            // GitHub repo export (SPEC §6.4): Pro-only and needs a connected
            // GitHub account; the page posts JSON and shows the repo URL
            // straight from the response (no build/poll cycle). Queried
            // fresh — a connection created mid-session must show at once.
            'github' => [
                'url' => route('dashboard.projects.github-export', $project),
                'available' => app(EntitlementService::class)->for($request->user())->canExportToGithub(),
                'connected' => ($githubConnection = $request->user()->githubConnection()->first()) !== null,
                'account' => $githubConnection?->github_login,
            ],
            // Live-edit forks (SPEC §5.6): the page polls this prop while any
            // fork's preview rebuild is pending/building.
            'forks' => $forks,
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $project->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'project' => ['id' => $project->id, 'name' => $project->name],
            ]);
        }

        return back();
    }

    public function destroy(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $project->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return to_route('dashboard.projects.index');
    }
}
