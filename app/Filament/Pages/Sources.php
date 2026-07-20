<?php

namespace App\Filament\Pages;

use App\Models\Component;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Sources (SPEC §15.5): read-only overview of the distinct citation sources
 * referenced by components — drives the §9 attribution/takedown workflow.
 * No backing model; rows are aggregated straight from the components table.
 */
class Sources extends Page
{
    protected string $view = 'filament.pages.sources';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Library';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Sources';

    /**
     * Distinct citation sources with component counts and latest sync date.
     *
     * @return Collection<int, object{source_name: string, source_url: ?string, components_count: int, latest_added_at: string}>
     */
    public function sources(): Collection
    {
        return Component::query()
            ->select('source_name', 'source_url')
            ->selectRaw('COUNT(*) as components_count')
            ->selectRaw('MAX(created_at) as latest_added_at')
            ->whereNotNull('source_name')
            ->groupBy('source_name', 'source_url')
            ->orderByDesc('components_count')
            ->orderBy('source_name')
            ->get();
    }
}
