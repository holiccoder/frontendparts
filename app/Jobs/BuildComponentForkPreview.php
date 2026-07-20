<?php

namespace App\Jobs;

use App\Enums\ComponentForkStatus;
use App\Models\ComponentFork;
use App\Services\Library\ForkPreviewBuilder;
use App\Services\Library\PreviewScreenshotter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rebuilds a live-edit fork's prebuilt preview + screenshots (SPEC §5.6,
 * NFR-4 — heavy work is queued, the save endpoint returns immediately).
 * Runs the SAME preview-build steps as the library pipeline
 * (ForkPreviewBuilder shares PreviewArtifactBuilder with PreviewBuilder)
 * against the fork's edited sources, writing to the fork-specific
 * `forks/{id}/` paths on the preview disk. The fork row itself is the
 * progress state — pending → building → ready/failed with `error` — which
 * the project page polls; failures are recorded on the row instead of
 * bubbling up, mirroring BuildComponentPreview.
 */
class BuildComponentForkPreview implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $forkId,
    ) {}

    public function handle(ForkPreviewBuilder $builder, PreviewScreenshotter $screenshotter): void
    {
        $fork = ComponentFork::query()->find($this->forkId);

        if ($fork === null) {
            return;
        }

        $fork->update([
            'status' => ComponentForkStatus::Building,
            'error' => null,
        ]);

        try {
            $path = $builder->build($fork);

            $disk = Storage::disk((string) config('library.preview_disk', 'previews'));
            $widths = (array) config('library.screenshot_widths', [375, 768, 1280]);

            foreach ($widths as $width) {
                $screenshotter->capture(
                    $disk->path($path),
                    $disk->path(dirname($path)."/shots/{$fork->framework}-{$width}.png"),
                    (int) $width,
                );
            }

            $fork->update([
                'status' => ComponentForkStatus::Ready,
                'preview_paths' => [$fork->framework => $path],
                'preview_built_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $fork->update([
                'status' => ComponentForkStatus::Failed,
                'error' => Str::limit($exception->getMessage(), 4000, ''),
            ]);
        }
    }
}
