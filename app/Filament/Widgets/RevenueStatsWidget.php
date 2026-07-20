<?php

namespace App\Filament\Widgets;

use App\Services\Admin\RevenueStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * P1 KPI row (SPEC §8.6 row 1): registered users with the week-over-week
 * delta, active subscribers, normalized MRR, and the review queue. All
 * math lives in RevenueStats so the counting rules stay testable.
 */
class RevenueStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $stats = app(RevenueStats::class);

        $growth = $stats->userGrowth();
        $awaitingReview = $stats->awaitingReview();

        return [
            Stat::make('Registered users', $growth['total'])
                ->description("+{$growth['this_week']} this week · {$growth['last_week']} last week")
                ->color('success'),
            Stat::make('Active subscribers', $stats->activeSubscribers())
                ->description('Starter + Pro, incl. dunning grace'),
            Stat::make('MRR', '$'.number_format($stats->mrr(), 2))
                ->description('Quarterly ÷3 · yearly ÷12 · lifetime excluded'),
            Stat::make('Awaiting review', $awaitingReview)
                ->description($awaitingReview > 0 ? 'Needs attention' : 'Queue empty')
                ->color($awaitingReview > 0 ? 'danger' : 'gray'),
        ];
    }
}
