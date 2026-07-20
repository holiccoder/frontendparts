<?php

namespace Tests\Feature\Admin;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Filament\Widgets\LatestOrdersWidget;
use App\Filament\Widgets\PlanMixChartWidget;
use App\Filament\Widgets\RevenueStatsWidget;
use App\Filament\Widgets\RevenueTrendChartWidget;
use App\Models\Admin;
use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\RevenueStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1 revenue & growth widgets (SPEC §8.6 rows 1–2, 4): the counting rules
 * are tested against RevenueStats directly; each widget then proves it
 * renders the service data.
 */
class RevenueWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_mrr_normalizes_monthly_quarterly_yearly()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));

        // Contributing orders (the §7.3 states that still entitle).
        $this->paidOrder(OrderPlan::Starter, OrderStatus::Active, BillingPeriod::Monthly, 30.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Active, BillingPeriod::Quarterly, 90.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::PastDue, BillingPeriod::Yearly, 360.00);
        // Cancelled with ends_at in the future: access runs to period end, still contributes.
        $this->paidOrder(OrderPlan::Starter, OrderStatus::Cancelled, BillingPeriod::Monthly, 12.00, endsAt: now()->addDays(10));

        // None of these may contribute.
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Pending, BillingPeriod::Monthly, 999.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Expired, BillingPeriod::Monthly, 999.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Refunded, BillingPeriod::Monthly, 999.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Cancelled, BillingPeriod::Monthly, 999.00, endsAt: now()->subDay());
        $this->paidOrder(OrderPlan::Free, OrderStatus::Active, BillingPeriod::Monthly, 999.00);

        // 30 + 90/3 + 360/12 + 12 = 102.
        $this->assertSame(102.00, app(RevenueStats::class)->mrr());
    }

    public function test_lifetime_excluded_from_mrr_but_in_revenue()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));

        // Lifetime one-off: real revenue, but not recurring.
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Active, BillingPeriod::Lifetime, 500.00);
        // A subscription in the same month.
        $this->paidOrder(OrderPlan::Starter, OrderStatus::Active, BillingPeriod::Monthly, 30.00);
        // An expired subscription from two months ago is historical revenue.
        $this->paidOrder(OrderPlan::Starter, OrderStatus::Expired, BillingPeriod::Monthly, 45.00, startsAt: now()->subMonths(2));
        // Never paid / returned money is not revenue.
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Pending, BillingPeriod::Yearly, 999.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Refunded, BillingPeriod::Monthly, 999.00);

        $stats = app(RevenueStats::class);

        // MRR only sees the active subscription — lifetime is excluded.
        $this->assertSame(30.00, $stats->mrr());

        $trend = $stats->revenueTrend();

        $this->assertCount(12, $trend['labels']);
        $this->assertSame(now()->format('M Y'), $trend['labels'][11]);

        // Current month: the lifetime spike is its own dataset.
        $this->assertSame(500.00, $trend['lifetime'][11]);
        $this->assertSame(30.00, $trend['subscription'][11]);

        // Two months ago: the expired order counts as subscription revenue.
        $this->assertSame(45.00, $trend['subscription'][9]);
        $this->assertSame(0.00, $trend['lifetime'][9]);
    }

    public function test_plan_mix_counts_by_plan_and_period()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));

        $this->paidOrder(OrderPlan::Starter, OrderStatus::Active, BillingPeriod::Monthly, 9.00);
        $this->paidOrder(OrderPlan::Starter, OrderStatus::Active, BillingPeriod::Monthly, 9.00);
        $this->paidOrder(OrderPlan::Starter, OrderStatus::Active, BillingPeriod::Yearly, 90.00);
        // PastDue grace still counts.
        $this->paidOrder(OrderPlan::Starter, OrderStatus::PastDue, BillingPeriod::Quarterly, 27.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Active, BillingPeriod::Lifetime, 299.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Active, BillingPeriod::Lifetime, 299.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Active, BillingPeriod::Lifetime, 299.00);
        // Cancelled but still valid counts; lapsed / pending do not.
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Cancelled, BillingPeriod::Quarterly, 45.00, endsAt: now()->addMonth());
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Cancelled, BillingPeriod::Monthly, 15.00, endsAt: now()->subDay());
        $this->paidOrder(OrderPlan::Starter, OrderStatus::Pending, BillingPeriod::Monthly, 9.00);

        $mix = app(RevenueStats::class)->planMix();

        $this->assertSame(
            ['Starter · Monthly', 'Starter · Quarterly', 'Starter · Yearly', 'Pro · Quarterly', 'Pro · Lifetime'],
            $mix['labels'],
        );
        $this->assertSame([2, 1, 1, 1, 3], $mix['data']);
    }

    public function test_kpi_week_over_week_deltas()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));

        User::factory()->count(2)->create(['created_at' => now()->subDays(2)]);
        User::factory()->create(['created_at' => now()->subDays(10)]);
        User::factory()->create(['created_at' => now()->subDays(30)]);

        $growth = app(RevenueStats::class)->userGrowth();

        $this->assertSame(4, $growth['total']);
        $this->assertSame(2, $growth['this_week']);
        $this->assertSame(1, $growth['last_week']);

        // Subscribers are distinct users: bob's two orders count once; the
        // expired order's owner does not count at all.
        $bob = User::factory()->create();
        $this->paidOrder(OrderPlan::Starter, OrderStatus::Active, BillingPeriod::Monthly, 9.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::PastDue, BillingPeriod::Yearly, 199.00);
        $this->paidOrder(OrderPlan::Starter, OrderStatus::Active, BillingPeriod::Monthly, 9.00, user: $bob);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Active, BillingPeriod::Quarterly, 45.00, user: $bob);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Expired, BillingPeriod::Monthly, 19.00);

        $this->assertSame(3, app(RevenueStats::class)->activeSubscribers());

        Component::factory()->count(2)->inReview()->create();
        Component::factory()->draft()->create();

        $this->assertSame(2, app(RevenueStats::class)->awaitingReview());
    }

    public function test_revenue_stats_widget_renders_kpis()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        $admin = Admin::factory()->create();

        $buyer = User::factory()->create(['created_at' => now()->subDays(2)]);
        User::factory()->create(['created_at' => now()->subDays(2)]);
        $this->paidOrder(OrderPlan::Starter, OrderStatus::Active, BillingPeriod::Monthly, 30.00, user: $buyer);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Active, BillingPeriod::Quarterly, 90.00, user: $buyer);
        Component::factory()->inReview()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(RevenueStatsWidget::class)
            ->assertSee('Registered users')
            ->assertSee('+2 this week')
            ->assertSee('Active subscribers')
            ->assertSee('MRR')
            ->assertSee('$60.00')
            ->assertSee('Awaiting review')
            ->assertSee('Needs attention');
    }

    public function test_revenue_trend_chart_renders_service_data()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        $admin = Admin::factory()->create();

        $this->paidOrder(OrderPlan::Starter, OrderStatus::Active, BillingPeriod::Monthly, 30.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Active, BillingPeriod::Lifetime, 543.25);

        $this->actingAs($admin, 'admin');

        Livewire::test(RevenueTrendChartWidget::class)
            ->assertSee('Revenue · last 12 months')
            ->assertSee('Subscription revenue')
            ->assertSee('Lifetime revenue')
            // Chart.js payload embeds the lifetime spike in the JSON data.
            ->assertSee('543.25');
    }

    public function test_plan_mix_chart_renders_service_data()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        $admin = Admin::factory()->create();

        $this->paidOrder(OrderPlan::Starter, OrderStatus::Active, BillingPeriod::Monthly, 9.00);
        $this->paidOrder(OrderPlan::Pro, OrderStatus::Active, BillingPeriod::Lifetime, 299.00);

        $this->actingAs($admin, 'admin');

        Livewire::test(PlanMixChartWidget::class)
            ->assertSee('Plan mix · active orders')
            ->assertSee('Starter · Monthly')
            ->assertSee('Pro · Lifetime');
    }

    public function test_latest_orders_widget_lists_recent_orders_with_paddle_link()
    {
        $this->travelTo(Carbon::parse('2026-07-15 12:00:00'));
        $admin = Admin::factory()->create();

        $recent = Order::factory()->create([
            'paddle_transaction_id' => 'txn_test_recent',
            'created_at' => now(),
        ]);
        $noTransaction = Order::factory()->create([
            'paddle_transaction_id' => null,
            'created_at' => now()->subHour(),
        ]);
        // Push the oldest order out of the top 10.
        Order::factory()->count(10)->create(['created_at' => now()->subHours(6)]);
        $evicted = Order::factory()->create(['created_at' => now()->subDays(5)]);

        $this->actingAs($admin, 'admin');

        Livewire::test(LatestOrdersWidget::class)
            ->assertCanSeeTableRecords([$recent, $noTransaction])
            ->assertCanNotSeeTableRecords([$evicted])
            ->assertSee('vendors.paddle.com/transactions-v2/txn_test_recent')
            ->assertSee('—');
    }

    public function test_latest_orders_paddle_link_follows_sandbox_config()
    {
        config()->set('cashier.sandbox', true);

        $admin = Admin::factory()->create();

        Order::factory()->create(['paddle_transaction_id' => 'txn_sandbox_1']);

        $this->actingAs($admin, 'admin');

        Livewire::test(LatestOrdersWidget::class)
            ->assertSee('sandbox-vendors.paddle.com/transactions-v2/txn_sandbox_1');
    }

    /**
     * An order in a deterministic state; starts/ends default to a fresh
     * period from "now" unless given.
     */
    private function paidOrder(
        OrderPlan $plan,
        OrderStatus $status,
        BillingPeriod $period,
        float $amount,
        ?Carbon $startsAt = null,
        ?Carbon $endsAt = null,
        ?User $user = null,
    ): Order {
        return Order::factory()->create([
            'user_id' => $user?->id ?? User::factory(),
            'plan' => $plan,
            'status' => $status,
            'billing_period' => $period,
            'amount' => $amount,
            'starts_at' => $startsAt ?? now(),
            'ends_at' => $endsAt ?? match ($period) {
                BillingPeriod::Monthly => now()->addMonth(),
                BillingPeriod::Quarterly => now()->addMonths(3),
                BillingPeriod::Yearly => now()->addYear(),
                BillingPeriod::Lifetime => null,
            },
            'cancelled_at' => $status === OrderStatus::Cancelled ? now() : null,
        ]);
    }
}
