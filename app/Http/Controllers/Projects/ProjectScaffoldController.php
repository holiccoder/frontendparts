<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ProjectExportKind;
use App\Enums\ProjectExportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\BuildProjectScaffold;
use App\Models\Project;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * POST /dashboard/projects/{project}/scaffold (SPEC §6.3, FR-5): queues the
 * starter assembly (NFR-4 — heavy work is queued, the request returns
 * immediately) and the dashboard polls the project page until the export is
 * ready, then streams it from the shared export download route. Next.js now;
 * Nuxt joins the framework allowlist with Phase 3.5.
 *
 * Gating (SPEC §7.1): scaffolding is Pro-only — Free/Starter resolve the
 * established 403 upgrade payload, checked at scaffold time.
 */
class ProjectScaffoldController extends Controller
{
    public function __invoke(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'framework' => ['sometimes', 'nullable', Rule::in(['next'])],
        ]);

        $framework = $validated['framework'] ?? 'next';

        if (! app(EntitlementService::class)->for($request->user())->canScaffold()) {
            if (! $request->expectsJson()) {
                return back()->withErrors([
                    'scaffold' => 'Scaffolding is a Pro feature — upgrade to generate a runnable Next.js starter.',
                ]);
            }

            return response()->json([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to Pro',
                    'pricing_url' => '/pricing',
                ],
            ], 403);
        }

        $export = $project->exports()->create([
            'user_id' => $request->user()->id,
            'framework' => $framework,
            'kind' => ProjectExportKind::Scaffold,
            'status' => ProjectExportStatus::Pending,
        ]);

        BuildProjectScaffold::dispatch($export->id);

        if ($request->expectsJson()) {
            return response()->json([
                'export' => [
                    'id' => $export->id,
                    'status' => $export->status->value,
                    'framework' => $export->framework,
                    'download_url' => null,
                ],
            ], 202);
        }

        return back()->with('notice', "Building your {$framework} starter — the download link appears here as soon as it's ready.");
    }
}
