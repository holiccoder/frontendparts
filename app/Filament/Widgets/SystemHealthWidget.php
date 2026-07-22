<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P0 system health: the failed queue job count with the most recent
 * failures for a quick diagnosis.
 */
class SystemHealthWidget extends Widget
{
    protected string $view = 'filament.widgets.system-health';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function failedJobsCount(): int
    {
        return DB::table('failed_jobs')->count();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function recentFailedJobs(): Collection
    {
        return DB::table('failed_jobs')
            ->select(['id', 'uuid', 'connection', 'queue', 'exception', 'failed_at'])
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get();
    }
}
