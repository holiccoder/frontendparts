<?php

namespace App\Jobs;

use App\Enums\ProjectExportStatus;
use App\Models\ProjectExport;
use App\Services\Projects\ProjectPackZipper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Assembles a project's pack zip onto the `exports` disk (SPEC §6.2, §10.3
 * NFR-4 — heavy export work is queued, the POST endpoint returns
 * immediately). Marks the ProjectExport ready with its disk path on success;
 * on failure the row is marked failed and the exception rethrown so the job
 * lands in failed_jobs for the admin system-health row (SPEC §8.6).
 */
class BuildProjectPackZip implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $exportId,
    ) {}

    public function handle(ProjectPackZipper $zipper): void
    {
        $export = ProjectExport::query()->with('project')->find($this->exportId);

        if ($export === null || $export->project === null) {
            return;
        }

        try {
            $tempPath = $zipper->build($export->project, $export->framework);

            $path = "project-exports/{$export->id}-{$export->framework}.zip";

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
