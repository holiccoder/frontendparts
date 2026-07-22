<?php

namespace Tests\Feature\Notifications;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\WelcomeToProNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Order-paid welcome mail (SPEC §16.1): queued when an order becomes Active;
 * carries the license summary + first steps and never duplicates Paddle's
 * merchant-of-record purchase documentation.
 */
class OrderPaidTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_to_pro_queued_on_activation()
    {
        Notification::fake();

        $order = Order::factory()->create([
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
        ]);

        $order->update(['status' => OrderStatus::Active]);

        Notification::assertSentTo(
            $order->user,
            WelcomeToProNotification::class,
            fn (WelcomeToProNotification $notification): bool => $notification->order->is($order),
        );

        // It is a queued notification (SPEC §16: all sends queued).
        $this->assertContains(ShouldQueue::class, class_implements(WelcomeToProNotification::class));

        // No re-send when an unrelated field changes afterwards.
        $order->update(['amount' => 199.00]);

        $this->assertSame(
            1,
            Notification::sent($order->user, WelcomeToProNotification::class)->count(),
        );
    }

    public function test_email_contains_license_summary_not_invoice()
    {
        $order = Order::factory()->create([
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Active,
            'billing_period' => BillingPeriod::Yearly,
            'amount' => 108.00,
            'currency' => 'USD',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);

        $html = (string) (new WelcomeToProNotification($order))->toMail($order->user)->render();

        // Plan summary + first steps.
        $this->assertStringContainsString('Pro', $html);
        $this->assertStringContainsString('unlimited personal and commercial use', $html);
        $this->assertStringContainsString(route('dashboard'), $html);
        $this->assertStringContainsString(route('dashboard.orders.index'), $html);

        // Paddle (merchant of record) sends its own receipts/invoices — this
        // mail never duplicates them (SPEC §16.1).
        $this->assertStringNotContainsString('invoice', strtolower($html));
        $this->assertStringNotContainsString('receipt', strtolower($html));
    }
}
