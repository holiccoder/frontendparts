<?php

namespace App\Filament\Widgets;

use App\Services\Admin\RevenueStats;
use Filament\Widgets\ChartWidget;

/**
 * P1 money trend (SPEC §8.6 row 2): monthly revenue over the trailing 12
 * months with lifetime one-offs as their own dataset, so spikes don't
 * distort the subscription revenue line.
 */
class RevenueTrendChartWidget extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Revenue · last 12 months';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $trend = app(RevenueStats::class)->revenueTrend();

        return [
            'datasets' => [
                [
                    'label' => 'Subscription revenue',
                    'data' => $trend['subscription'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                ],
                [
                    'label' => 'Lifetime revenue',
                    'data' => $trend['lifetime'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                ],
            ],
            'labels' => $trend['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
