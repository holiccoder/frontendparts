<?php

namespace App\Jobs;

use App\Enums\ProjectExportStatus;
use App\Models\ProjectExport;
use App\Services\Scaffold\ScaffoldZipper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Assembles a project's starter scaffold onto the `exports` disk (SPEC §6.3,
 * §10.3 NFR-4 — heavy export work is queued, the POST endpoint returns
 * immediately). Mirrors BuildProjectPackZip: marks the ProjectExport ready
 * with its disk path on success; on failure the row is marked failed and the
 * exception rethrown so the job lands in failed_jobs for the admin
 * system-health row (SPEC §8.6).
 */
class BuildProjectScaffold implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $exportId,
    ) {}

    public function handle(): void
    {
        $export = ProjectExport::query()->with('project')->find($this->exportId);

        if ($export === null || $export->project === null) {
            return;
        }

        try {
            $tempPath = ScaffoldZipper::for($export->framework)->build($export->project);

            $path = "project-exports/{$export->id}-scaffold-{$export->framework}.zip";

            $stream = fopen($tempPath, 'r');
            Storage::disk('exports')->put($path, $stream);
            fclose($stream);
            @unlink($tempPath);

            $export->update([
                'status' => ProjectExportStatus::Ready,
                'path' => $path,
            ]);
        } catch (Throwable $exception) {
            $export->update(['status' => ProjectExportStatus::Failed]);

            throw $exception;
        }
    }
}
