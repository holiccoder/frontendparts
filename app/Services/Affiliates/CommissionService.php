<?php

namespace App\Services\Affiliates;

use App\Enums\CommissionStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\Order;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The commission engine (SPEC §17.2–17.3).
 *
 * - Order paid (any activation transition into Active — Paddle webhook,
 *   domestic notify, admin) → a `pending` commission for rate × net amount.
 *   "Net" is the order's stored amount: the plan price excluding taxes
 *   (Paddle collects and remits tax on top as merchant of record, so tax
 *   never enters `orders.amount`).
 * - Attribution (§17.1 step 4, last-click): the referral code stamped on the
 *   order at checkout wins; otherwise a referred buyer's first purchase
 *   resolves through the signup link; otherwise a repeat purchase renews the
 *   buyer's existing commission chain while the recurring window (first
 *   `affiliate.recurring_months` months from the original conversion) is
 *   still open. Lifetime plans earn once — they never renew.
 * - Refund/chargeback before payout → `voided` (hooked from the same
 *   observer seam, so RefundService and the Paddle refunded webhook both
 *   land here).
 * - Self-referral (same user or same email) never earns — banned (§17.2).
 * - Suspended affiliates earn no new commissions; history is kept.
 * - The daily `affiliates:mark-payable` command flips `pending` → `payable`
 *   once the refund window (`billing.refund_window_days`) + holding period
 *   (`affiliate.holding_days`) have elapsed since the sale.
 *
 * One commission per order (unique order_id+affiliate_id) doubles as the
 * idempotency guard for replayed webhooks and repeat activations.
 */
class CommissionService
{
    public function __construct(
        private readonly Settings $settings = new Settings,
    ) {}

    /**
     * Create the pending commission for a freshly paid order, or return the
     * existing one (idempotent). Null when the order is unattributed,
     * self-referred, or the affiliate is suspended.
     */
    public function attributePaidOrder(Order $order): ?AffiliateCommission
    {
        $existing = AffiliateCommission::query()
            ->where('order_id', $order->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $attribution = $this->resolveAttribution($order);

        if ($attribution === null) {
            return null;
        }

        [$affiliate, $referral] = $attribution;

        if (! $affiliate->isActive() || $this->isSelfReferral($affiliate, $order->user)) {
            return null;
        }

        $rate = (int) $this->settings->get('affiliate.commission_rate');

        return AffiliateCommission::query()->create([
            'affiliate_id' => $affiliate->id,
            'order_id' => $order->id,
            'referral_id' => $referral?->id,
            'amount' => bcdiv(bcmul((string) $order->amount, (string) $rate, 4), '100', 2),
            'currency' => $order->currency,
            'status' => CommissionStatus::Pending,
        ]);
    }

    /**
     * Void the order's unpaid commissions on refund/chargeback (SPEC §17.1
     * step 6). Paid commissions are past payout and stay — clawbacks after
     * payout are a manual, terms-backed process (§17.7).
     */
    public function voidForOrder(Order $order, string $reason): int
    {
        return AffiliateCommission::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [CommissionStatus::Pending->value, CommissionStatus::Payable->value])
            ->update([
                'status' => CommissionStatus::Voided->value,
                'voided_reason' => $reason,
            ]);
    }

    /**
     * Void a single unpaid commission — the admin fraud control on the
     * Commissions resource (SPEC §17.5). Paid commissions are past payout
     * and stay (clawbacks after payout are a manual, terms-backed process,
     * §17.7); voiding one is a no-op.
     */
    public function void(AffiliateCommission $commission, string $reason): void
    {
        if (! in_array($commission->status, [CommissionStatus::Pending, CommissionStatus::Payable], true)) {
            return;
        }

        $commission->update([
            'status' => CommissionStatus::Voided,
            'voided_reason' => $reason,
        ]);
    }

    /**
     * Flip every pending commission past the refund window + holding period
     * to payable (SPEC §17.1 step 5). Returns the commissions that became
     * payable on this run (the 3.9.7 "commission payable" mail hangs off
     * this list).
     *
     * @return Collection<int, AffiliateCommission>
     */
    public function markPayable(): Collection
    {
        $days = (int) $this->settings->get('billing.refund_window_days')
            + (int) $this->settings->get('affiliate.holding_days');

        $due = AffiliateCommission::query()
            ->where('status', CommissionStatus::Pending->value)
            ->where('created_at', '<=', now()->subDays($days))
            ->get();

        foreach ($due as $commission) {
            $commission->update([
                'status' => CommissionStatus::Payable,
                'payable_at' => now(),
            ]);
        }

        return $due;
    }

    /**
     * Last-click attribution for a paid order (SPEC §17.1 step 4):
     * checkout-stamped code → existing commission chain (renewal window) →
     * signup-linked referral.
     *
     * @return array{0: Affiliate, 1: AffiliateReferral|null}|null
     */
    private function resolveAttribution(Order $order): ?array
    {
        // The referral code stamped at checkout — survives cookie loss and
        // wins over every older signal (last-click).
        if ($order->referral_code !== null) {
            $affiliate = Affiliate::query()->where('code', $order->referral_code)->first();

            if ($affiliate !== null) {
                return [$affiliate, $this->latestReferralFor($affiliate, $order->user_id)];
            }
        }

        $buyer = $order->user;

        if ($buyer === null) {
            return null;
        }

        // Renewal: the buyer already has a commission chain — attribute to
        // the same affiliate while the recurring window (measured from the
        // original conversion) is still open (§17.2). A renewal payment is a
        // NEW order row in the domestic lifecycle (§7.5: one-time payment
        // per period, bought again through the QR checkout); Paddle
        // auto-renewals fold into the same order row instead (§7.3), so they
        // are settled by the per-order idempotency guard above.
        $latest = $this->commissionChain($buyer)->latest('id')->first();

        if ($latest !== null) {
            $original = $this->commissionChain($buyer)
                ->where('affiliate_id', $latest->affiliate_id)
                ->oldest('id')
                ->first();

            $anchor = $original->order?->starts_at ?? $original->created_at;
            $windowMonths = (int) $this->settings->get('affiliate.recurring_months');

            if ($anchor !== null && $anchor->copy()->addMonths($windowMonths)->isFuture()) {
                return [$latest->affiliate, $original->referral];
            }

            // Window closed — the chain is spent; never fall back to the
            // signup link it grew from.
            return null;
        }

        // First purchase of a referred user (signup link, §17.1 step 3).
        $referral = AffiliateReferral::query()
            ->where('referred_user_id', $buyer->id)
            ->latest('clicked_at')
            ->latest('id')
            ->first();

        return $referral !== null ? [$referral->affiliate, $referral] : null;
    }

    /**
     * The buyer's non-voided commission history, any affiliate.
     */
    private function commissionChain(User $buyer): Builder
    {
        return AffiliateCommission::query()
            ->where('status', '!=', CommissionStatus::Voided->value)
            ->whereHas('order', fn (Builder $query): Builder => $query->where('user_id', $buyer->id));
    }

    /**
     * The affiliate's latest referral already linked to this buyer — the
     * informational `referral_id` on code-attributed commissions; null when
     * the buyer never went through the signup link (e.g. an existing user
     * who clicked and bought).
     */
    private function latestReferralFor(Affiliate $affiliate, ?int $userId): ?AffiliateReferral
    {
        if ($userId === null) {
            return null;
        }

        return $affiliate->referrals()
            ->where('referred_user_id', $userId)
            ->latest('clicked_at')
            ->latest('id')
            ->first();
    }

    /**
     * Self-referral ban (§17.2): same user account or same email address.
     */
    private function isSelfReferral(Affiliate $affiliate, ?User $buyer): bool
    {
        if ($buyer === null) {
            return false;
        }

        if ($affiliate->user_id === $buyer->id) {
            return true;
        }

        $affiliate->loadMissing('user');

        return $affiliate->user !== null
            && strcasecmp((string) $affiliate->user->email, (string) $buyer->email) === 0;
    }
}
