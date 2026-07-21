<?php

namespace App\Http\Controllers\Projects;

use App\Enums\ComponentEventType;
use App\Enums\ProjectExportKind;
use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Project;
use App\Models\ProjectExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /dashboard/projects/{project}/export/{export}/download (SPEC §6.2,
 * §6.3, §8.6): streams a built export zip from the private exports disk
 * (owner-only, scoped bindings) and records a component event for every
 * component in the export — `download` for pack zips, `scaffold` for
 * starter scaffolds — the same license download tracking the
 * single-component download records per zip.
 */
class ProjectExportDownloadController extends Controller
{
    public function __invoke(Request $request, Project $project, ProjectExport $export): StreamedResponse
    {
        abort_unless($project->user_id === $request->user()->id, 403);

        $disk = Storage::disk('exports');

        abort_unless($export->path !== null && $disk->exists($export->path), 404);

        $eventType = $export->kind === ProjectExportKind::Scaffold
            ? ComponentEventType::Scaffold
            : ComponentEventType::Download;

        $project->components()->get()->each(
            fn (Component $component) => $component->recordEvent($eventType, $request->user())
        );

        $filename = Str::slug($project->name)."-{$export->framework}.zip";

        return $disk->download($export->path, $filename);
    }
}
