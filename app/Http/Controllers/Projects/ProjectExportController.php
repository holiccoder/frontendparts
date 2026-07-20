<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pack zip export placeholder (SPEC §6.2). The queued zip build arrives with
 * sub-phase 2.5; until then this stub answers 501 for API consumers and a
 * form error for the Inertia dashboard, so the export button is wired and
 * cleanly replaceable.
 */
class ProjectExportController extends Controller
{
    public function __invoke(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'export_not_implemented',
                'message' => 'Pack zip export is coming soon.',
            ], 501);
        }

        return back()->withErrors(['export' => 'Pack zip export is coming soon.']);
    }
}
