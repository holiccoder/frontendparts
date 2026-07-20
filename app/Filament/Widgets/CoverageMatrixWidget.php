<?php

namespace App\Filament\Widgets;

use App\Enums\CategoryType;
use App\Enums\ComponentStatus;
use App\Models\Category;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P0 coverage matrix (SPEC §8.6 row 5): 12 industries × 32 usage patterns
 * heatmap of published component counts. Cells below 3 are flagged red
 * ("what to build next"), 3–5 amber, above 5 green.
 */
class CoverageMatrixWidget extends Widget
{
    protected string $view = 'filament.widgets.coverage-matrix';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array{industries: Collection<int, Category>, usages: Collection<int, Category>, cells: array<int, array<int, int>>, covered: int, total: int, coverage_pct: float}
     */
    public function matrixData(): array
    {
        $industries = Category::query()
            ->where('type', CategoryType::Industry)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        $usages = Category::query()
            ->where('type', CategoryType::Usage)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        $counts = DB::table('component_industry')
            ->join('components', 'components.id', '=', 'component_industry.component_id')
            ->where('components.status', ComponentStatus::Published->value)
            ->groupBy('component_industry.category_id', 'components.usage_category_id')
            ->select(
                'component_industry.category_id as industry_id',
                'components.usage_category_id as usage_id',
                DB::raw('COUNT(*) as aggregate'),
            )
            ->get()
            ->mapWithKeys(fn (object $row): array => ["{$row->industry_id}:{$row->usage_id}" => (int) $row->aggregate]);

        $cells = [];
        $covered = 0;

        foreach ($industries as $industry) {
            foreach ($usages as $usage) {
                $count = $counts->get("{$industry->id}:{$usage->id}", 0);
                $cells[$industry->id][$usage->id] = $count;

                if ($count >= 3) {
                    $covered++;
                }
            }
        }

        $total = $industries->count() * $usages->count();

        return [
            'industries' => $industries,
            'usages' => $usages,
            'cells' => $cells,
            'covered' => $covered,
            'total' => $total,
            'coverage_pct' => $total === 0 ? 0.0 : round($covered / $total * 100, 1),
        ];
    }

    /**
     * Heat level for one cell: <3 = critical (build next), 3–5 = warning,
     * >5 = ok.
     */
    public static function cellTone(int $count): string
    {
        return match (true) {
            $count < 3 => 'critical',
            $count <= 5 => 'warning',
            default => 'ok',
        };
    }
}
