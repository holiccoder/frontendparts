<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Enums\ComponentForkStatus;
use App\Jobs\BuildComponentForkPreview;
use App\Models\Project;
use App\Services\Billing\EntitlementService;
use App\Services\Catalog\ComponentRouteResolver;
use App\Services\Projects\ProjectComponentService;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * POST /components/{usage}/{slug}/forks (SPEC §5.6 Save to Project):
 * persists the live-edit tab's edited sources as a customized fork linked to
 * one of the reader's projects and queues the background preview rebuild
 * (202 — the project page polls the fork's status; the original library
 * component is never modified). Only reachable while the live-edit feature
 * flag is on (404 otherwise), for signed-in verified users who OWN the
 * target project (403 otherwise), and — for paid components — behind the
 * same full-library entitlement as the edit download (403 upgrade payload).
 * Saving also adds the component to the project's set (auto-add closure,
 * SPEC §6.1) when it isn't there yet. Rate-limited 10/minute via the route.
 */
class ComponentForkController extends Controller
{
    public function __invoke(Request $request, string $usage, string $slug): JsonResponse
    {
        abort_unless((bool) app(Settings::class)->get('features.live_edit'), 404);

        $component = app(ComponentRouteResolver::class)->resolve($usage, $slug);

        // Zip-slip guard on the path regex: no leading slash, no `..`
        // segments, no backslashes — only library-relative file paths.
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'framework' => ['required', Rule::in(['react', 'vue'])],
            'entry_file' => ['nullable', 'string', 'max:255', 'regex:#^(?!/)(?!.*(?:^|/)\.\.(?:/|$))[A-Za-z0-9._\-/]+$#'],
            'files' => ['required', 'array', 'min:1', 'max:100'],
            'files.*.path' => ['required', 'string', 'max:255', 'regex:#^(?!/)(?!.*(?:^|/)\.\.(?:/|$))[A-Za-z0-9._\-/]+$#'],
            'files.*.code' => ['present', 'string', 'max:1000000'],
        ]);

        /** @var Project $project */
        $project = Project::query()->findOrFail($validated['project_id']);

        abort_unless($project->user_id === $request->user()->id, 403);

        // Plan gate (SPEC §7.1): identical to ComponentEditDownloadController
        // — a fork of paid sources is still the component's sources.
        if ($component->access_level === AccessLevel::Paid
            && ! app(EntitlementService::class)->for($request->user())->hasFullLibrary()) {
            return response()->json([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to download',
                    'pricing_url' => '/pricing',
                ],
            ], 403);
        }

        $files = collect($validated['files'])
            ->keyBy('path')
            ->map(fn (array $file): string => $file['code'])
            ->all();

        // The fork's build mounts the edited entry source — it must be among
        // the posted files (vue entries come from the repl's flat file map).
        $entryFile = $validated['entry_file'] ?? null;

        if ($validated['framework'] === 'vue') {
            if ($entryFile === null || ! array_key_exists($entryFile, $files)) {
                throw ValidationException::withMessages([
                    'entry_file' => 'The vue entry file must be one of the posted files.',
                ]);
            }
        } elseif (! array_key_exists("{$component->slug}/index.tsx", $files)) {
            throw ValidationException::withMessages([
                'files' => 'The edited sources must include the component entry file.',
            ]);
        }

        // The fork customizes a component of the project's set: saving adds
        // it (with its auto-add closure, SPEC §6.1) when not present yet.
        app(ProjectComponentService::class)->add($project, $component);

        $fork = $project->forks()->create([
            'component_id' => $component->id,
            'framework' => $validated['framework'],
            'entry_file' => $validated['framework'] === 'vue' ? $entryFile : null,
            'files' => $files,
            'status' => ComponentForkStatus::Pending,
        ]);

        BuildComponentForkPreview::dispatch($fork->id);

        return response()->json([
            'fork' => [
                'id' => $fork->id,
                'status' => $fork->status->value,
                'preview_url' => null,
                'project_url' => route('dashboard.projects.show', $project),
            ],
        ], 202);
    }
}
