<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CommissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Affiliate dashboard (SPEC §17.4, CSR zone): the self-serve home of the
 * affiliate program. Non-affiliates get the join flow (terms acceptance
 * against `/affiliate-terms`, §17.7); affiliates get the overview stats
 * (clicks, signups, conversion rate, pending/payable/paid earnings), the
 * referral link card, the commissions table, payout history and the
 * payout-method form.
 *
 * Suspended affiliates keep full read access to their history (the core
 * engine already stops their code from recording clicks and earning) but
 * the page is read-only for them: the payout-method form is disabled and
 * the update endpoint rejects them — see AffiliatePayoutMethodController.
 */
class AffiliateController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function __invoke(Request $request): Response
    {
        $affiliate = $request->user()->affiliate;

        if ($affiliate === null) {
            return Inertia::render('dashboard/affiliate', [
                'affiliate' => null,
                'stats' => null,
                'commissions' => [],
                'payouts' => [],
                'settings' => $this->programSettings(),
                'terms_url' => route('legal.affiliate-terms'),
            ]);
        }

        return Inertia::render('dashboard/affiliate', [
            'affiliate' => [
                'code' => $affiliate->code,
                'status' => $affiliate->status->value,
                'referral_url' => $affiliate->referralUrl(),
                'terms_accepted_at' => $affiliate->terms_accepted_at?->toIso8601String(),
                'payout_method' => $affiliate->payout_method,
            ],
            'stats' => $this->stats($affiliate),
            'commissions' => $this->commissions($affiliate),
            'payouts' => $this->payouts($affiliate),
            'settings' => $this->programSettings(),
            'terms_url' => route('legal.affiliate-terms'),
        ]);
    }

    /**
     * Overview numbers (SPEC §17.4): clicks and signups from the referral
     * log, the signup conversion rate, and lifetime earnings per status
     * bucketed per currency (an affiliate can hold USD and CNY commissions).
     *
     * @return array{clicks: int, signups: int, conversion_rate: float|null, earnings: array<string, list<array{currency: string, amount: string}>>}
     */
    private function stats(Affiliate $affiliate): array
    {
        $clicks = $affiliate->referrals()->count();
        $signups = $affiliate->referrals()->whereNotNull('referred_user_id')->count();

        $earnings = [
            CommissionStatus::Pending->value => [],
            CommissionStatus::Payable->value => [],
            CommissionStatus::Paid->value => [],
        ];

        $affiliate->commissions()
            ->selectRaw('status, currency, SUM(amount) as total')
            ->whereIn('status', array_keys($earnings))
            ->groupBy('status', 'currency')
            ->get()
            ->each(function (AffiliateCommission $row) use (&$earnings): void {
                $earnings[$row->status->value][] = [
                    'currency' => $row->currency,
                    'amount' => number_format((float) $row->getAttribute('total'), 2, '.', ''),
                ];
            });

        return [
            'clicks' => $clicks,
            'signups' => $signups,
            'conversion_rate' => $clicks > 0 ? round($signups / $clicks * 100, 1) : null,
            'earnings' => $earnings,
        ];
    }

    /**
     * The commissions table, newest first (all statuses incl. voided — the
     * affiliate sees exactly what the admin sees).
     *
     * @return list<array<string, mixed>>
     */
    private function commissions(Affiliate $affiliate): array
    {
        return $affiliate->commissions()
            ->with('order:id,plan')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (AffiliateCommission $commission): array => [
                'id' => $commission->id,
                'order_id' => $commission->order_id,
                'plan' => $commission->order?->plan->value,
                'amount' => $commission->amount,
                'currency' => $commission->currency,
                'status' => $commission->status->value,
                'payable_at' => $commission->payable_at?->toIso8601String(),
                'voided_reason' => $commission->voided_reason,
                'created_at' => $commission->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Payout history, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function payouts(Affiliate $affiliate): array
    {
        return $affiliate->payouts()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AffiliatePayout $payout): array => [
                'id' => $payout->id,
                'amount' => $payout->amount,
                'currency' => $payout->currency,
                'status' => $payout->status->value,
                'method' => $payout->method,
                'reference' => $payout->reference,
                'paid_at' => $payout->paid_at?->toIso8601String(),
                'created_at' => $payout->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * The program knobs the page displays (SPEC §8.7 — always current).
     *
     * @return array{commission_rate: int, cookie_days: int, holding_days: int, payout_threshold: int}
     */
    private function programSettings(): array
    {
        return [
            'commission_rate' => (int) $this->settings->get('affiliate.commission_rate'),
            'cookie_days' => (int) $this->settings->get('affiliate.cookie_days'),
            'holding_days' => (int) $this->settings->get('affiliate.holding_days'),
            'payout_threshold' => (int) $this->settings->get('affiliate.payout_threshold'),
        ];
    }
}
