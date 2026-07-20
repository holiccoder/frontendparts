<?php

namespace App\Filament\Widgets;

use App\Enums\AccessLevel;
use App\Enums\ComponentStatus;
use App\Models\Component;
use App\Models\LibrarySyncRun;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * P0 catalog stats (SPEC §8.6): published count with weekly delta, the
 * review queue (highlighted while non-empty), free/paid split, and the last
 * library sync at a glance.
 */
class CatalogStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $published = Component::query()->published()->count();
        $newThisWeek = Component::query()->published()->where('created_at', '>=', now()->subWeek())->count();
        $awaitingReview = Component::query()->where('status', ComponentStatus::InReview)->count();
        $free = Component::query()->where('access_level', AccessLevel::Free)->count();
        $paid = Component::query()->where('access_level', AccessLevel::Paid)->count();
        $lastSync = LibrarySyncRun::query()->latest('created_at')->first();

        return [
            Stat::make('Published components', $published)
                ->description("+{$newThisWeek} this week")
                ->color('success'),
            Stat::make('Awaiting review', $awaitingReview)
                ->description($awaitingReview > 0 ? 'Needs attention' : 'Queue empty')
                ->color($awaitingReview > 0 ? 'danger' : 'gray'),
            Stat::make('Free vs paid', "{$free} / {$paid}")
                ->description('Free / paid catalog split'),
            Stat::make('Last sync', $lastSync === null ? 'Never' : $lastSync->created_at->diffForHumans())
                ->description($lastSync === null
                    ? 'No sync runs yet'
                    : "Scanned {$lastSync->scanned} · upserted {$lastSync->upserted}"),
        ];
    }
}
