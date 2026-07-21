<?php

namespace App\Services\Admin;

use App\Enums\ComponentEventType;
use App\Enums\ProjectExportKind;
use App\Models\Component;
use App\Models\ComponentEvent;
use App\Models\Project;
use App\Models\ProjectExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Downloads & popularity math behind the admin dashboard P2 widgets (SPEC
 * §8.6 rows 1 + 5), kept out of the Filament widgets so the counting rules
 * stay queryable and testable on their own.
 *
 * Counting rules (locked here — widgets stay thin):
 *
 * - "Downloads" are the take-away events on `component_events`: Copy (code
 *   copied from the modal), Download (single-component zip or one row per
 *   component in a pack zip), and Scaffold (one row per component in a
 *   starter-scaffold export). View and GateHit never count as downloads —
 *   views feed popularity, gate hits feed the B2 conversion funnel.
 * - The 30-day window is inclusive: `created_at >= now()->subDays(30)`.
 * - Top components rank by total engagement (views + downloads) over the
 *   trailing 30 days; ties break by views, then component id, so the order
 *   is deterministic. Components with zero in-window activity are excluded.
 * - Projects tracking counts every project row and every export row (pack
 *   or scaffold), regardless of build status — the SPEC tracks creation
 *   intent, not build outcomes.
 */
class PopularityStats
{
    /**
     * Event types that count as a download (SPEC §8.6 "components + zips +
     * scaffolds"): code copies, zip downloads, scaffold exports.
     *
     * @var list<ComponentEventType>
     */
    private const DOWNLOAD_TYPES = [
        ComponentEventType::Copy,
        ComponentEventType::Download,
        ComponentEventType::Scaffold,
    ];

    /**
     * Downloads over the trailing 30 days split by kind (SPEC §8.6 row 1:
     * "Downloads 30d (components + zips + scaffolds)").
     *
     * @return array{total: int, copies: int, zips: int, scaffolds: int}
     */
    public function downloads30d(): array
    {
        $counts = ComponentEvent::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereIn('type', self::DOWNLOAD_TYPES)
            ->get()
            ->countBy(fn (ComponentEvent $event): string => $event->type->value);

        $copies = (int) $counts->get(ComponentEventType::Copy->value, 0);
        $zips = (int) $counts->get(ComponentEventType::Download->value, 0);
        $scaffolds = (int) $counts->get(ComponentEventType::Scaffold->value, 0);

        return [
            'total' => $copies + $zips + $scaffolds,
            'copies' => $copies,
            'zips' => $zips,
            'scaffolds' => $scaffolds,
        ];
    }

    /**
     * Projects created and project exports over the trailing 30 days, with
     * the pack vs scaffold export split (SPEC §8.6 P2 projects tracking).
     *
     * @return array{projects_total: int, projects_30d: int, exports_30d: int, packs_30d: int, scaffolds_30d: int}
     */
    public function projectTracking(): array
    {
        $since = now()->subDays(30);

        return [
            'projects_total' => Project::query()->count(),
            'projects_30d' => Project::query()->where('created_at', '>=', $since)->count(),
            'exports_30d' => ProjectExport::query()->where('created_at', '>=', $since)->count(),
            'packs_30d' => ProjectExport::query()
                ->where('created_at', '>=', $since)
                ->where('kind', ProjectExportKind::Pack)
                ->count(),
            'scaffolds_30d' => ProjectExport::query()
                ->where('created_at', '>=', $since)
                ->where('kind', ProjectExportKind::Scaffold)
                ->count(),
        ];
    }

    /**
     * The most-engaged components over the trailing 30 days (SPEC §8.6 row
     * 5), each carrying `views_30d` and `downloads_30d` count attributes.
     *
     * @return Collection<int, Component>
     */
    public function topComponents(int $limit = 10): Collection
    {
        return $this->topComponentsQuery($limit)->get();
    }

    /**
     * Query behind topComponents() — exposed so the table widget can render
     * the same ranking without duplicating the counting rules.
     *
     * @return Builder<Component>
     */
    public function topComponentsQuery(int $limit = 10): Builder
    {
        $since = now()->subDays(30);
        $popularityTypes = [ComponentEventType::View, ...self::DOWNLOAD_TYPES];

        return Component::query()
            ->whereHas('events', fn (Builder $query): Builder => $query
                ->whereIn('type', $popularityTypes)
                ->where('created_at', '>=', $since))
            ->withCount([
                'events as views_30d' => fn (Builder $query): Builder => $query
                    ->where('type', ComponentEventType::View)
                    ->where('created_at', '>=', $since),
                'events as downloads_30d' => fn (Builder $query): Builder => $query
                    ->whereIn('type', self::DOWNLOAD_TYPES)
                    ->where('created_at', '>=', $since),
                // Plain-alias ordering works on both sqlite and MySQL; a raw
                // "views + downloads" expression would not.
                'events as activity_30d' => fn (Builder $query): Builder => $query
                    ->whereIn('type', $popularityTypes)
                    ->where('created_at', '>=', $since),
            ])
            ->orderByDesc('activity_30d')
            ->orderByDesc('views_30d')
            ->orderBy('id')
            ->limit($limit);
    }
}
