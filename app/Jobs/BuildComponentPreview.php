<?php

namespace App\Jobs;

use App\Models\Component;
use App\Models\PreviewBuildFailure;
use App\Services\Library\PreviewBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the self-contained preview HTML artifacts for one component
 * (SPEC §5.2). Failures are recorded in preview_build_failures for the
 * admin system-health widget instead of bubbling up, so one broken
 * framework never blocks the other; a successful build clears the
 * component's previous failure rows. Successful frameworks are handed
 * off to CaptureComponentScreenshots for the QA gate.
 */
class BuildComponentPreview implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $frameworks
     */
    public function __construct(
        public int $componentId,
        public array $frameworks,
    ) {}

    public function handle(PreviewBuilder $builder): void
    {
        $component = Component::query()->find($this->componentId);

        if ($component === null) {
            return;
        }

        $paths = $component->preview_paths ?? [];
        $builtFrameworks = [];

        foreach ($this->frameworks as $framework) {
            try {
                $paths[$framework] = $builder->build($component, $framework);
                $builtFrameworks[] = $framework;

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

        if ($builtFrameworks === []) {
            return;
        }

        $component->update([
            'preview_paths' => $paths,
            'preview_built_at' => now(),
        ]);

        CaptureComponentScreenshots::dispatch($component->id, $builtFrameworks);
    }
}
