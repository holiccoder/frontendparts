<?php

namespace Tests\Feature\Notifications;

use App\Enums\CommissionStatus;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Filament\Resources\AffiliatePayouts\Pages\ListAffiliatePayouts;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\Order;
use App\Models\User;
use App\Notifications\AffiliateCommissionPayableNotification;
use App\Notifications\AffiliateConversionCreditedNotification;
use App\Notifications\AffiliatePayoutSentNotification;
use App\Notifications\Contracts\MarketingNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Affiliate emails (SPEC §17.6): referral conversion credited, commission
 * payable and payout sent. All three are transactional (never gated by the
 * marketing preference center) and queued like every app mail (§16).
 */
class AffiliateMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversion_credited_queued()
    {
        Notification::fake();

        $affiliate = Affiliate::factory()->create();
        $buyer = User::factory()->create();

        AffiliateReferral::factory()->converted($buyer)->create([
            'affiliate_id' => $affiliate->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'amount' => '108.00',
            'currency' => 'USD',
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $order->update(['status' => OrderStatus::Active, 'starts_at' => now()]);

        $commission = AffiliateCommission::query()->where('order_id', $order->id)->sole();

        Notification::assertSentTo(
            $affiliate->user,
            AffiliateConversionCreditedNotification::class,
            fn (AffiliateConversionCreditedNotification $notification): bool => $notification->commission->is($commission),
        );

        // Queued (SPEC §16) and transactional (§17.6) — not marketing.
        $this->assertContains(ShouldQueue::class, class_implements(AffiliateConversionCreditedNotification::class));
        $this->assertNotContains(MarketingNotification::class, class_implements(AffiliateConversionCreditedNotification::class));

        // A repeat activation returns the existing commission — no re-mail.
        $order->update(['amount' => '108.00']);

        $this->assertSame(
            1,
            Notification::sent($affiliate->user, AffiliateConversionCreditedNotification::class)->count(),
        );
    }

    public function test_payable_queued()
    {
        Notification::fake();

        $commission = AffiliateCommission::factory()->create([
            'status' => CommissionStatus::Pending,
        ]);

        // Default knobs: 14-day refund window + 30-day holding = 44 days.
        $this->travel(45)->days();

        $this->artisan('affiliates:mark-payable')->assertSuccessful();

        Notification::assertSentTo(
            $commission->affiliate->user,
            AffiliateCommissionPayableNotification::class,
            fn (AffiliateCommissionPayableNotification $notification): bool => $notification->commission->is($commission),
        );

        $this->assertContains(ShouldQueue::class, class_implements(AffiliateCommissionPayableNotification::class));
        $this->assertNotContains(MarketingNotification::class, class_implements(AffiliateCommissionPayableNotification::class));

        // The commission itself is flipped — and a second run sends nothing.
        $this->assertSame(CommissionStatus::Payable, $commission->refresh()->status);

        $this->artisan('affiliates:mark-payable')->assertSuccessful();

        $this->assertSame(
            1,
            Notification::sent($commission->affiliate->user, AffiliateCommissionPayableNotification::class)->count(),
        );
    }

    public function test_payout_sent_queued()
    {
        Notification::fake();

        $this->actingAs(Admin::factory()->create(), 'admin');

        $affiliate = Affiliate::factory()->create([
            'payout_method' => ['method' => 'wise', 'email' => 'aff@example.com'],
        ]);

        $payout = AffiliatePayout::factory()->create([
            'affiliate_id' => $affiliate->id,
            'method' => ['method' => 'wise', 'email' => 'aff@example.com'],
        ]);

        $payout->commissions()->attach(
            AffiliateCommission::factory()->payable()->create(['affiliate_id' => $affiliate->id])->id,
        );

        Livewire::test(ListAffiliatePayouts::class)
            ->callTableAction('markPaid', $payout, data: ['reference' => 'wise-txn-123'])
            ->assertHasNoTableActionErrors();

        Notification::assertSentTo(
            $affiliate->user,
            AffiliatePayoutSentNotification::class,
            fn (AffiliatePayoutSentNotification $notification): bool => $notification->payout->is($payout),
        );

        $this->assertContains(ShouldQueue::class, class_implements(AffiliatePayoutSentNotification::class));
        $this->assertNotContains(MarketingNotification::class, class_implements(AffiliatePayoutSentNotification::class));
    }
}
