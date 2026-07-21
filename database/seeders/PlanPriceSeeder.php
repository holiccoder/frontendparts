<?php

namespace Database\Seeders;

use App\Enums\PlanProvider;
use App\Models\PlanPrice;
use Illuminate\Database\Seeder;

class PlanPriceSeeder extends Seeder
{
    /**
     * Price ladder per SPEC §7.2 (paddle/USD) and §7.5 domestic
     * placeholders (domestic/CNY at ~7.2× USD, rounded).
     *
     * Team (task 5.2) is priced PER SEAT at ≈ Starter × 1.5 for the
     * recurring periods; the lifetime seat follows SPEC §7.2's lifetime
     * convention (≈ 2.5–3× yearly) at 2.5× the yearly seat price. CNY
     * amounts keep the ~7.2× USD rounding convention.
     *
     * @var array<string, array<string, array{paddle: float, domestic: float}>>
     */
    private const LADDER = [
        'starter' => [
            'monthly' => ['paddle' => 9.00, 'domestic' => 65.00],
            'quarterly' => ['paddle' => 24.00, 'domestic' => 173.00],
            'yearly' => ['paddle' => 72.00, 'domestic' => 518.00],
            'lifetime' => ['paddle' => 149.00, 'domestic' => 1073.00],
        ],
        'pro' => [
            'monthly' => ['paddle' => 15.00, 'domestic' => 108.00],
            'quarterly' => ['paddle' => 36.00, 'domestic' => 259.00],
            'yearly' => ['paddle' => 108.00, 'domestic' => 778.00],
            'lifetime' => ['paddle' => 299.00, 'domestic' => 2153.00],
        ],
        'team' => [
            'monthly' => ['paddle' => 13.50, 'domestic' => 97.00],
            'quarterly' => ['paddle' => 36.00, 'domestic' => 259.00],
            'yearly' => ['paddle' => 108.00, 'domestic' => 778.00],
            'lifetime' => ['paddle' => 270.00, 'domestic' => 1944.00],
        ],
    ];

    public function run(): void
    {
        foreach (self::LADDER as $plan => $periods) {
            foreach ($periods as $period => $prices) {
                PlanPrice::query()->updateOrCreate(
                    [
                        'plan' => $plan,
                        'period' => $period,
                        'provider' => PlanProvider::Paddle->value,
                    ],
                    ['amount' => $prices['paddle'], 'currency' => 'USD', 'paddle_price_id' => null],
                );

                PlanPrice::query()->updateOrCreate(
                    [
                        'plan' => $plan,
                        'period' => $period,
                        'provider' => PlanProvider::Domestic->value,
                    ],
                    ['amount' => $prices['domestic'], 'currency' => 'CNY', 'paddle_price_id' => null],
                );
            }
        }
    }
}
