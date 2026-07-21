<?php

namespace Tests\Feature\Admin;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\OrderPlan;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Filament\Resources\AffiliateCommissions\Pages\ListAffiliateCommissions;
use App\Filament\Resources\AffiliatePayouts\Pages\ListAffiliatePayouts;
use App\Filament\Resources\Affiliates\Pages\ViewAffiliate;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateReferral;
use App\Models\Order;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Affiliate admin (SPEC §17.5): the monthly payout batch groups payable
 * commissions per affiliate once they clear the threshold (below-threshold
 * balances roll over), mark-paid settles the payout and its commissions
 * with the provider reference, suspending an affiliate stops new
 * commissions, and single commissions can be voided.
 */
class AffiliateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_groups_payable_commissions_over_threshold()
    {
        Notification::fake();

        $this->actingAs(Admin::factory()->create(), 'admin');

        $affiliate = Affiliate::factory()->create([
            'payout_method' => ['method' => 'paypal', 'email' => 'aff@example.com'],
        ]);

        // 30.00 + 25.00 = 55.00 — clears the $50 default threshold.
        AffiliateCommission::factory()->payable()->create([
            'affiliate_id' => $affiliate->id,
            'amount' => '30.00',
        ]);
        AffiliateCommission::factory()->payable()->create([
            'affiliate_id' => $affiliate->id,
            'amount' => '25.00',
        ]);

        // Pending commissions are never batched.
        AffiliateCommission::factory()->create([
            'affiliate_id' => $affiliate->id,
            'amount' => '40.00',
            'status' => CommissionStatus::Pending,
        ]);

        Livewire::test(ListAffiliatePayouts::class)
            ->callAction('runBatch')
            ->assertHasNoActionErrors();

        $payout = AffiliatePayout::query()->sole();

        $this->assertSame($affiliate->id, $payout->affiliate_id);
        $this->assertSame('55.00', $payout->amount);
        $this->assertSame('USD', $payout->currency);
        $this->assertSame(PayoutStatus::Processing, $payout->status);
        // The payout method is snapshotted at batch time (SPEC §17.3).
        $this->assertSame(['method' => 'paypal', 'email' => 'aff@example.com'], $payout->method);

        $this->assertSame(2, $payout->commissions()->count());

        // A second run does not re-batch the same commissions.
        Livewire::test(ListAffiliatePayouts::class)
            ->callAction('runBatch')
            ->assertHasNoActionErrors();

        $this->assertSame(1, AffiliatePayout::count());
    }

    public function test_below_threshold_skipped()
    {
        Notification::fake();

        $this->actingAs(Admin::factory()->create(), 'admin');

        $affiliate = Affiliate::factory()->create();

        // 30.00 < $50 threshold — rolls over to the next batch.
        AffiliateCommission::factory()->payable()->create([
            'affiliate_id' => $affiliate->id,
            'amount' => '30.00',
        ]);

        Livewire::test(ListAffiliatePayouts::class)
            ->callAction('runBatch')
            ->assertHasNoActionErrors();

        $this->assertSame(0, AffiliatePayout::count());

        // The knob is settings-driven (SPEC §8.7): lowering the threshold
        // to $20 sweeps the same commission immediately.
        app(Settings::class)->set('affiliate.payout_threshold', 20);

        Livewire::test(ListAffiliatePayouts::class)
            ->callAction('runBatch')
            ->assertHasNoActionErrors();

        $this->assertSame(1, AffiliatePayout::count());
        $this->assertSame('30.00', AffiliatePayout::query()->sole()->amount);
    }

    public function test_mark_paid_sets_commissions_paid()
    {
        Notification::fake();

        $this->actingAs(Admin::factory()->create(), 'admin');

        $affiliate = Affiliate::factory()->create();
        $payout = AffiliatePayout::factory()->create([
            'affiliate_id' => $affiliate->id,
            'amount' => '80.00',
        ]);

        $commissions = AffiliateCommission::factory()->payable()->count(2)->create([
            'affiliate_id' => $affiliate->id,
            'amount' => '40.00',
        ]);

        $payout->commissions()->attach($commissions->modelKeys());

        Livewire::test(ListAffiliatePayouts::class)
            ->callTableAction('markPaid', $payout, data: ['reference' => 'pp-98765432'])
            ->assertHasNoTableActionErrors();

        $payout->refresh();

        $this->assertSame(PayoutStatus::Paid, $payout->status);
        $this->assertSame('pp-98765432', $payout->reference);
        $this->assertNotNull($payout->paid_at);

        foreach ($commissions as $commission) {
            $this->assertSame(CommissionStatus::Paid, $commission->refresh()->status);
        }
    }

    public function test_suspend_affiliate_stops_new_commissions()
    {
        Notification::fake();

        $this->actingAs(Admin::factory()->create(), 'admin');

        $affiliate = Affiliate::factory()->create();
        $buyer = User::factory()->create();

        AffiliateReferral::factory()->converted($buyer)->create([
            'affiliate_id' => $affiliate->id,
        ]);

        Livewire::test(ViewAffiliate::class, ['record' => $affiliate->id])
            ->callAction('suspend')
            ->assertHasNoActionErrors();

        $this->assertSame(AffiliateStatus::Suspended, $affiliate->refresh()->status);

        // A paid order from the referred buyer earns nothing while the
        // affiliate is suspended (SPEC §17.2 — history is kept, new
        // commissions stop).
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

        $this->assertSame(0, AffiliateCommission::count());

        // Unsuspending re-activates earning.
        Livewire::test(ViewAffiliate::class, ['record' => $affiliate->id])
            ->callAction('unsuspend')
            ->assertHasNoActionErrors();

        $this->assertSame(AffiliateStatus::Active, $affiliate->refresh()->status);

        $second = Order::factory()->create([
            'user_id' => $buyer->id,
            'plan' => OrderPlan::Pro,
            'status' => OrderStatus::Pending,
            'amount' => '108.00',
            'currency' => 'USD',
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $second->update(['status' => OrderStatus::Active, 'starts_at' => now()]);

        $this->assertSame(1, AffiliateCommission::count());
    }

    public function test_void_commission_action()
    {
        Notification::fake();

        $this->actingAs(Admin::factory()->create(), 'admin');

        $payable = AffiliateCommission::factory()->payable()->create();
        $paid = AffiliateCommission::factory()->paid()->create();

        Livewire::test(ListAffiliateCommissions::class)
            ->callTableAction('void', $payable, data: ['reason' => 'fraud review'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(CommissionStatus::Voided, $payable->refresh()->status);
        $this->assertSame('fraud review', $payable->voided_reason);

        // Paid commissions are past payout — the void action stays hidden.
        Livewire::test(ListAffiliateCommissions::class)
            ->assertTableActionHidden('void', $paid);

        $this->assertSame(CommissionStatus::Paid, $paid->refresh()->status);
    }
}
