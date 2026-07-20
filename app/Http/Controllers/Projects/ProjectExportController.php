<?php

namespace App\Http\Controllers\Projects;

use App\Enums\AccessLevel;
use App\Enums\ProjectExportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\BuildProjectPackZip;
use App\Models\Project;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * POST /dashboard/projects/{project}/export (SPEC §6.2): queues the pack zip
 * build (NFR-4 — heavy work is queued, the request returns immediately) and
 * the dashboard polls the project page until the export is ready, then
 * streams it from the download route. React/Vue is chosen here, at export
 * time (SPEC §6.1).
 *
 * Gating (SPEC §7.1, defense in depth): the entitlement is resolved at export
 * time, so a project holding paid components (e.g. added before a plan
 * expired) answers the established 403 upgrade payload.
 */
class ProjectExportController extends Controller
{
    public function __invoke(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'framework' => ['sometimes', 'nullable', Rule::in(['react', 'vue'])],
        ]);

        $framework = $validated['framework'] ?? 'react';

        $containsPaid = $project->components()
            ->where('access_level', AccessLevel::Paid)
            ->exists();

        if ($containsPaid && ! app(EntitlementService::class)->for($request->user())->hasFullLibrary()) {
            if (! $request->expectsJson()) {
                return back()->withErrors([
                    'export' => 'This project contains paid components and your plan no longer covers them. Upgrade to export.',
                ]);
            }

            return response()->json([
                'error' => 'upgrade_required',
                'upgrade' => [
                    'cta' => 'Upgrade to download',
                    'pricing_url' => '/pricing',
                ],
            ], 403);
        }

        $export = $project->exports()->create([
            'user_id' => $request->user()->id,
            'framework' => $framework,
            'status' => ProjectExportStatus::Pending,
        ]);

        BuildProjectPackZip::dispatch($export->id);

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

        return back()->with('notice', "Building your {$framework} zip — the download link appears here as soon as it's ready.");
    }
}
