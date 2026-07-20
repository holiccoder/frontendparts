<?php

namespace App\Jobs;

use App\Models\Component;
use App\Models\PreviewBuildFailure;
use App\Services\Library\PreviewScreenshotter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Renders the built preview HTML at the QA viewport widths (375/768/1280)
 * into `{slug}/{version}/shots/{framework}-{width}.png` (SPEC §5.2 step 5).
 * Browser-stack failures are recorded in preview_build_failures for the
 * admin system-health widget; the job never throws for a missing browser.
 */
class CaptureComponentScreenshots implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $frameworks
     */
    public function __construct(
        public int $componentId,
        public array $frameworks,
    ) {}

    public function handle(PreviewScreenshotter $screenshotter): void
    {
        $component = Component::query()->find($this->componentId);

        if ($component === null) {
            return;
        }

        $disk = Storage::disk((string) config('library.preview_disk', 'previews'));
        $widths = (array) config('library.screenshot_widths', [375, 768, 1280]);

        foreach ($this->frameworks as $framework) {
            $relative = $component->previewPath($framework);

            if ($relative === null || ! $disk->exists($relative)) {
                continue;
            }

            try {
                foreach ($widths as $width) {
                    $screenshotter->capture(
                        $disk->path($relative),
                        $disk->path(dirname($relative)."/shots/{$framework}-{$width}.png"),
                        (int) $width,
                    );
                }

                PreviewBuildFailure::query()
                    ->where('component_id', $component->id)
                    ->where('framework', $framework)
                    ->delete();
            } catch (Throwable $exception) {
                PreviewBuildFailure::query()->updateOrCreate(
                    ['component_id' => $component->id, 'framework' => $framework],
                    ['error' => Str::limit($exception->getMessage(), 4000, '')],
                );
            }
        }
    }
}
