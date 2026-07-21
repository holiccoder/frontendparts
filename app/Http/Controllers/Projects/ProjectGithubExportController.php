<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Billing\EntitlementService;
use App\Services\Integrations\GithubApiException;
use App\Services\Integrations\GithubClient;
use App\Services\Scaffold\ScaffoldZipper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * POST /dashboard/projects/{project}/github-export (SPEC §6.4): creates a
 * repository on the user's connected GitHub account and commits the chosen
 * starter scaffold into it in a single commit via the Git Trees API,
 * returning the repo URL. Synchronous — the API round-trip is the whole job
 * and SPEC §6.4 returns the URL in the flow; unlike the zip builds there is
 * no heavy local assembly to queue.
 *
 * Gating (SPEC §7.1): Pro-only — Free/Starter resolve the established 403
 * upgrade payload. A connected GitHub account is required; without one the
 * response prompts to connect. GitHub API failures surface as user-facing
 * errors, never silently.
 */
class ProjectGithubExportController extends Controller
{
    public function __invoke(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'framework' => ['required', Rule::in(['next', 'nuxt'])],
            // GitHub repo names: alphanumerics plus `-`, `_`, `.`, ≤ 100 chars.
            'name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'visibility' => ['required', Rule::in(['public', 'private'])],
        ]);

        if (! app(EntitlementService::class)->for($request->user())->canExportToGithub()) {
            if (! $request->expectsJson()) {
                return back()->withErrors([
                    'github' => 'GitHub export is a Pro feature — upgrade to push starters to a repository.',
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

        $connection = $request->user()->githubConnection()->first();

        if ($connection === null) {
            if (! $request->expectsJson()) {
                return back()->withErrors([
                    'github' => 'Connect your GitHub account in settings before exporting to a repository.',
                ]);
            }

            return response()->json([
                'error' => 'github_not_connected',
                'message' => 'Connect your GitHub account before exporting to a repository.',
                'connect_url' => route('connections.edit'),
            ], 422);
        }

        $files = ScaffoldZipper::for($validated['framework'])->entries($project);

        try {
            $client = new GithubClient($connection);

            $repo = $client->createRepository(
                $validated['name'],
                $validated['visibility'] === 'private',
                "{$project->name} — a {$validated['framework']} starter exported from FrontendParts",
            );

            $client->pushInitialCommit(
                $repo['owner'],
                $repo['name'],
                $repo['default_branch'],
                $files,
                "Initial commit: {$project->name} ({$validated['framework']} starter) — exported from FrontendParts",
            );
        } catch (GithubApiException $exception) {
            if (! $request->expectsJson()) {
                return back()->withErrors(['github' => $exception->getMessage()]);
            }

            return response()->json([
                'error' => 'github_api_failed',
                'message' => $exception->getMessage(),
            ], 502);
        }

        if (! $request->expectsJson()) {
            return back()->with('notice', "Exported to GitHub: {$repo['html_url']}");
        }

        return response()->json([
            'repo' => [
                'url' => $repo['html_url'],
                'full_name' => $repo['full_name'],
                'visibility' => $validated['visibility'],
                'framework' => $validated['framework'],
            ],
        ], 201);
    }
}
