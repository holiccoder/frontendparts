<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ComponentForkStatus;
use App\Http\Controllers\Controller;
use App\Models\ComponentFork;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves a live-edit fork's rebuilt preview artifacts (SPEC §5.6) to the
 * project owner's sandboxed iframes. Owner-only (scoped bindings keep the
 * fork inside its project); the recorded build path must match the fork's
 * own `forks/{id}/{framework}.html`, which also keeps the URL immune to
 * path traversal. Fork previews rebuild in place, so responses are
 * no-cache; CSP `sandbox allow-scripts` mirrors the iframe sandbox.
 */
class ComponentForkPreviewController extends Controller
{
    /**
     * The rebuilt preview HTML document.
     */
    public function show(Request $request, Project $project, ComponentFork $fork): Response
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $path = $this->artifactPath($fork);

        $disk = Storage::disk((string) config('library.preview_disk', 'previews'));

        abort_if(! $disk->exists($path), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => (string) config('library.preview_csp', 'sandbox allow-scripts'),
        ]);
    }

    /**
     * One viewport screenshot (`{framework}-{width}.png`) of the fork's
     * rebuilt preview, for the project page's fork thumbnails.
     */
    public function shot(Request $request, Project $project, ComponentFork $fork, string $file): Response
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $path = $this->artifactPath($fork);

        $disk = Storage::disk((string) config('library.preview_disk', 'previews'));
        $shot = dirname($path)."/shots/{$file}";

        abort_if(! $disk->exists($shot), 404);

        return response($disk->get($shot), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The fork's recorded artifact path — 404 unless the rebuild is ready
     * AND the recorded path is the fork's own (never a caller-supplied one).
     */
    private function artifactPath(ComponentFork $fork): string
    {
        $path = $fork->previewPath();

        abort_if($fork->status !== ComponentForkStatus::Ready || $path !== "forks/{$fork->id}/{$fork->framework}.html", 404);

        return $path;
    }
}
