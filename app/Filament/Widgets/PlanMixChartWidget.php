<?php

namespace App\Filament\Widgets;

use App\Services\Admin\RevenueStats;
use Filament\Widgets\ChartWidget;

/**
 * P1 plan mix (SPEC §8.6 row 2): active orders by plan × billing period —
 * lifetime slices stay visible so lifetime cannibalization of subscription
 * revenue is easy to spot.
 */
class PlanMixChartWidget extends ChartWidget
{
    protected static ?int $sort = 7;

    protected ?string $heading = 'Plan mix · active orders';

    protected ?string $pollingInterval = null;

    /**
     * Slice colors covering every plan × period combination (2 × 4).
     *
     * @var list<string>
     */
    private const SLICE_COLORS = [
        '#f59e0b', '#d97706', '#fbbf24', '#b45309',
        '#10b981', '#059669', '#34d399', '#047857',
    ];

    protected function getData(): array
    {
        $mix = app(RevenueStats::class)->planMix();

        return [
            'datasets' => [
                [
                    'label' => 'Active orders',
                    'data' => $mix['data'],
                    'backgroundColor' => array_slice(self::SLICE_COLORS, 0, count($mix['data'])),
                ],
            ],
            'labels' => $mix['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
