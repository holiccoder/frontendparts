<?php

namespace App\Filament\Widgets;

use App\Services\Admin\PopularityStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * P2 KPI row (SPEC §8.6 rows 1 + P2 phasing): downloads over the trailing
 * 30 days split into copies / zips / scaffolds, plus project creation and
 * export activity. All math lives in PopularityStats so the counting rules
 * stay testable.
 */
class PopularityStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 9;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $stats = app(PopularityStats::class);

        $downloads = $stats->downloads30d();
        $projects = $stats->projectTracking();

        return [
            Stat::make('Downloads · 30d', $downloads['total'])
                ->description("{$downloads['copies']} copies · {$downloads['zips']} zips · {$downloads['scaffolds']} scaffolds")
                ->color('success'),
            Stat::make('Projects', $projects['projects_total'])
                ->description("+{$projects['projects_30d']} last 30 days"),
            Stat::make('Project exports · 30d', $projects['exports_30d'])
                ->description("{$projects['packs_30d']} packs · {$projects['scaffolds_30d']} scaffolds"),
        ];
    }
}
