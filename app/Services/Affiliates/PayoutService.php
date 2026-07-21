<?php

namespace App\Services\Affiliates;

use App\Enums\CommissionStatus;
use App\Enums\PayoutStatus;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Notifications\AffiliatePayoutSentNotification;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The monthly payout batch (SPEC §17.1 step 5, §17.5).
 *
 * - `batch()` sweeps every `payable` commission not yet attached to a
 *   payout into one `processing` payout per affiliate + currency. A group
 *   is only batched when its total clears the USD-denominated
 *   `affiliate.payout_threshold` — CNY groups are normalized at the
 *   configured `fx.cny_to_usd` rate (§17.2). Below-threshold balances roll
 *   over to the next month. The affiliate's saved payout method is
 *   snapshotted onto the payout so later edits never rewrite history.
 * - `markPaid()` settles a `processing` payout after the admin sends the
 *   money manually (PayPal / Wise): the payout and every attached
 *   commission flip to `paid`, the provider reference is stored, and the
 *   affiliate is mailed (§17.6).
 */
class PayoutService
{
    public function __construct(
        private readonly Settings $settings = new Settings,
    ) {}

    /**
     * Create the processing payouts for this batch run.
     *
     * @return Collection<int, AffiliatePayout>
     */
    public function batch(): Collection
    {
        $threshold = (string) $this->settings->get('affiliate.payout_threshold');
        $fxRate = (string) $this->settings->get('fx.cny_to_usd');

        $groups = AffiliateCommission::query()
            ->where('status', CommissionStatus::Payable->value)
            ->whereDoesntHave('payout')
            ->with('affiliate')
            ->get()
            ->groupBy(fn (AffiliateCommission $commission): string => $commission->affiliate_id.':'.$commission->currency);

        $payouts = new Collection;

        foreach ($groups as $commissions) {
            $total = '0';

            foreach ($commissions as $commission) {
                $total = bcadd($total, (string) $commission->amount, 2);
            }

            /** @var AffiliateCommission $first */
            $first = $commissions->first();

            // The threshold is USD-denominated (SPEC §17.2): CNY groups
            // normalize at the platform FX rate before comparing.
            $totalUsd = $first->currency === 'CNY' ? bcmul($total, $fxRate, 2) : $total;

            if (bccomp($totalUsd, $threshold, 2) < 0) {
                continue;
            }

            $payout = AffiliatePayout::query()->create([
                'affiliate_id' => $first->affiliate_id,
                'amount' => $total,
                'currency' => $first->currency,
                'status' => PayoutStatus::Processing,
                'method' => $first->affiliate->payout_method,
            ]);

            $payout->commissions()->attach($commissions->modelKeys());

            $payouts->push($payout);
        }

        return $payouts;
    }

    /**
     * Mark a processing payout paid with the provider reference, settle its
     * commissions and mail the affiliate (SPEC §17.5, §17.6).
     */
    public function markPaid(AffiliatePayout $payout, string $reference): void
    {
        DB::transaction(function () use ($payout, $reference): void {
            $payout->update([
                'status' => PayoutStatus::Paid,
                'reference' => $reference,
                'paid_at' => now(),
            ]);

            AffiliateCommission::query()
                ->whereIn('id', $payout->commissions()->pluck('affiliate_commissions.id'))
                ->update(['status' => CommissionStatus::Paid->value]);
        });

        $payout->loadMissing('affiliate.user');
        $payout->affiliate->user?->notify(new AffiliatePayoutSentNotification($payout->refresh()));
    }
}
