<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\PlanProvider;
use App\Models\Order;
use App\Models\PlanPrice;
use Database\Seeders\PlanPriceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_quarterly_and_lifetime_periods_exist()
    {
        $values = array_map(fn (BillingPeriod $period): string => $period->value, BillingPeriod::cases());

        $this->assertCount(4, $values);
        $this->assertContains('quarterly', $values);
        $this->assertContains('lifetime', $values);
    }

    public function test_price_resolved_from_plan_prices_not_enum()
    {
        PlanPrice::factory()->create([
            'plan' => OrderPlan::Starter,
            'period' => BillingPeriod::Monthly,
            'provider' => PlanProvider::Paddle,
            'amount' => 9.00,
            'currency' => 'USD',
        ]);

        $price = OrderPlan::Starter->price(BillingPeriod::Monthly);

        $this->assertInstanceOf(PlanPrice::class, $price);
        $this->assertSame('9.00', $price->amount);
        $this->assertSame('USD', $price->currency);

        // Repricing in the table is picked up without a deploy.
        $price->update(['amount' => 12.00]);
        $this->assertSame('12.00', OrderPlan::Starter->price(BillingPeriod::Monthly)->amount);

        // Providers resolve independently.
        $this->assertNull(OrderPlan::Starter->price(BillingPeriod::Monthly, PlanProvider::Domestic));
    }

    public function test_lifetime_order_allows_null_ends_at()
    {
        $order = Order::factory()->create([
            'billing_period' => BillingPeriod::Lifetime,
            'ends_at' => null,
        ]);

        $this->assertSame(BillingPeriod::Lifetime, $order->billing_period);
        $this->assertNull($order->ends_at);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'billing_period' => 'lifetime',
            'ends_at' => null,
        ]);
    }

    public function test_seeded_price_ladder_matches_spec()
    {
        $this->seed(PlanPriceSeeder::class);

        $this->assertSame(16, PlanPrice::count());
        $this->assertSame(8, PlanPrice::query()->where('provider', PlanProvider::Paddle)->count());
        $this->assertSame(8, PlanPrice::query()->where('provider', PlanProvider::Domestic)->count());

        $expected = [
            // plan, period, paddle USD amount, domestic CNY amount
            ['starter', 'monthly', '9.00', '65.00'],
            ['starter', 'quarterly', '24.00', '173.00'],
            ['starter', 'yearly', '72.00', '518.00'],
            ['starter', 'lifetime', '149.00', '1073.00'],
            ['pro', 'monthly', '15.00', '108.00'],
            ['pro', 'quarterly', '36.00', '259.00'],
            ['pro', 'yearly', '108.00', '778.00'],
            ['pro', 'lifetime', '299.00', '2153.00'],
        ];

        foreach ($expected as [$plan, $period, $usd, $cny]) {
            $paddle = PlanPrice::query()
                ->where('plan', $plan)
                ->where('period', $period)
                ->where('provider', PlanProvider::Paddle)
                ->sole();

            $this->assertSame($usd, $paddle->amount);
            $this->assertSame('USD', $paddle->currency);

            $domestic = PlanPrice::query()
                ->where('plan', $plan)
                ->where('period', $period)
                ->where('provider', PlanProvider::Domestic)
                ->sole();

            $this->assertSame($cny, $domestic->amount);
            $this->assertSame('CNY', $domestic->currency);
            $this->assertNull($domestic->paddle_price_id);
        }
    }

    public function test_enterprise_plan_no_longer_exists()
    {
        $this->assertSame(
            [OrderPlan::Free, OrderPlan::Starter, OrderPlan::Pro],
            OrderPlan::cases(),
        );
        $this->assertNull(OrderPlan::tryFrom('enterprise'));
    }
}
