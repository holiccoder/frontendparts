<?php

namespace App\Filament\Widgets;

use App\Jobs\BuildComponentPreview;
use App\Models\LibrarySyncRun;
use App\Models\PreviewBuildFailure;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P0 system health (SPEC §8.6 row 6): failed preview builds with a retry
 * path, the last library:sync run, and the failed queue job count.
 */
class SystemHealthWidget extends Widget
{
    protected string $view = 'filament.widgets.system-health';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return Collection<int, PreviewBuildFailure>
     */
    public function failedBuilds(): Collection
    {
        return PreviewBuildFailure::query()
            ->with('component:id,slug,name')
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    public function lastSyncRun(): ?LibrarySyncRun
    {
        return LibrarySyncRun::query()->latest('created_at')->first();
    }

    public function failedJobsCount(): int
    {
        return DB::table('failed_jobs')->count();
    }

    /**
     * Re-dispatch the preview build for one recorded failure; a successful
     * build clears its failure rows (see BuildComponentPreview).
     */
    public function retryBuild(int $failureId): void
    {
        $failure = PreviewBuildFailure::query()->find($failureId);

        if ($failure === null) {
            return;
        }

        BuildComponentPreview::dispatch($failure->component_id, [$failure->framework]);

        Notification::make()
            ->title('Preview build retried')
            ->body("Re-queued the {$failure->framework} build.")
            ->success()
            ->send();
    }
}
