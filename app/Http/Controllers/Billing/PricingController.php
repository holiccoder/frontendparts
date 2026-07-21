<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingPeriod;
use App\Enums\OrderPlan;
use App\Enums\PlanProvider;
use App\Http\Controllers\Controller;
use App\Models\PlanPrice;
use App\Services\Billing\RegionDetector;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/pricing` (SSR, SEO-indexed — SPEC §7.2, §15.1): plan × period toggle
 * fed straight from `plan_prices`, the feature comparison from the SPEC
 * §7.1 matrix, and a billing FAQ. Yearly is pushed as the best value;
 * lifetime is a permanent offering, not a promo. A plan × period combo
 * without a price row is rendered as unavailable, never crashing the page.
 *
 * Currency follows RegionDetector (SPEC §7.5): USD buyers see the Paddle
 * rows, CNY buyers the domestic rows, and the page surfaces the manual
 * currency switch. Both providers' rows come from `plan_prices` — never
 * hardcoded.
 */
class PricingController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function __invoke(Request $request, RegionDetector $region): Response
    {
        $currency = $region->preferredCurrency($request);
        $provider = $currency === RegionDetector::CNY ? PlanProvider::Domestic : PlanProvider::Paddle;

        return Inertia::render('pricing', [
            'periods' => array_map(
                fn (BillingPeriod $period): string => $period->value,
                BillingPeriod::cases(),
            ),
            'plans' => [
                'starter' => $this->plan(OrderPlan::Starter, 'The full library for one developer.', $provider),
                'pro' => $this->plan(OrderPlan::Pro, 'Library plus project scaffolding and exports.', $provider),
                'team' => $this->plan(OrderPlan::Team, 'Everything in Pro, for every seat on your team — priced per seat.', $provider),
            ],
            'currency' => $currency,
            'currencySwitchUrl' => route('billing.currency.switch'),
            'comparison' => $this->comparison(),
            'faq' => $this->faq(),
            'meta' => [
                'title' => 'Pricing — Starter & Pro plans for the full library',
                'description' => 'Full React & Vue component library: monthly, quarterly, yearly or lifetime billing, '
                    ."a {$this->refundWindow()}-day refund window and code you keep forever.",
                'canonical' => URL::to('/pricing'),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]);
    }

    /**
     * A paid plan with every billing period mapped to its `plan_prices`
     * row — prices are never hardcoded, so admin repricing (SPEC §7.3)
     * shows up without a deploy.
     *
     * @return array{name: string, tagline: string, checkout_url: string, prices: array<string, array{amount: string|null, currency: string, per_month: string|null}>}
     */
    private function plan(OrderPlan $plan, string $tagline, PlanProvider $provider = PlanProvider::Paddle): array
    {
        return [
            'name' => ucfirst($plan->value),
            'tagline' => $tagline,
            'checkout_url' => route('checkout.show', ['plan' => $plan->value]),
            'prices' => collect(BillingPeriod::cases())
                ->mapWithKeys(function (BillingPeriod $period) use ($plan, $provider): array {
                    $price = $plan->price($period, $provider);

                    return [$period->value => [
                        'amount' => $price?->amount,
                        'currency' => $price?->currency ?? ($provider === PlanProvider::Domestic ? 'CNY' : 'USD'),
                        'per_month' => $this->perMonth($price, $period),
                    ]];
                })
                ->all(),
        ];
    }

    /**
     * Monthly-equivalent figure for recurring periods (SPEC §7.2 shows
     * "$72 ($6/mo)"); lifetime is a one-time payment with no monthly
     * figure.
     */
    private function perMonth(?PlanPrice $price, BillingPeriod $period): ?string
    {
        if ($price === null) {
            return null;
        }

        return match ($period) {
            BillingPeriod::Monthly => $price->amount,
            BillingPeriod::Quarterly => number_format((float) $price->amount / 3, 2, '.', ''),
            BillingPeriod::Yearly => number_format((float) $price->amount / 12, 2, '.', ''),
            BillingPeriod::Lifetime => null,
        };
    }

    /**
     * SPEC §7.1 feature matrix, plus the settings-driven project limits
     * (§8.7). `true` renders a check, `false` a dash, strings verbatim.
     *
     * @return list<array{feature: string, free: bool|string, starter: bool|string, pro: bool|string}>
     */
    private function comparison(): array
    {
        return [
            ['feature' => 'Browse + preview full catalog', 'free' => true, 'starter' => true, 'pro' => true],
            ['feature' => 'Components copy/download', 'free' => 'Free subset (20–30%)', 'starter' => '100%', 'pro' => '100%'],
            ['feature' => 'React + Vue versions', 'free' => 'Free subset', 'starter' => true, 'pro' => true],
            ['feature' => 'Pack builder', 'free' => 'Free subset', 'starter' => true, 'pro' => true],
            ['feature' => 'Projects', 'free' => $this->projectLimit(OrderPlan::Free), 'starter' => $this->projectLimit(OrderPlan::Starter), 'pro' => $this->projectLimit(OrderPlan::Pro)],
            ['feature' => 'Next.js / Nuxt scaffolding', 'free' => false, 'starter' => false, 'pro' => true],
            ['feature' => 'New drops', 'free' => 'Free subset', 'starter' => true, 'pro' => 'Early access'],
            ['feature' => 'Future pro features', 'free' => false, 'starter' => false, 'pro' => true],
        ];
    }

    private function projectLimit(OrderPlan $plan): string
    {
        $limit = $this->settings->get("plans.project_limit.{$plan->value}");

        return $limit === null ? 'Unlimited' : (string) $limit;
    }

    /**
     * Billing FAQ derived from SPEC §7 (mechanics §7.3, license §7.4).
     *
     * @return list<array{question: string, answer: string}>
     */
    private function faq(): array
    {
        $window = $this->refundWindow();

        return [
            [
                'question' => 'Can I get a refund?',
                'answer' => "Yes. Every purchase is covered by a {$window}-day refund window from the date of payment — contact support and we refund in full through Paddle.",
            ],
            [
                'question' => 'Can I cancel my subscription anytime?',
                'answer' => 'Anytime. Cancelling stops the next renewal, and you keep full access until the end of the period you already paid for.',
            ],
            [
                'question' => 'What does “lifetime” mean?',
                'answer' => 'A single one-time payment for permanent access — a permanent offering, not a promotion. You get the full catalog and every future drop, forever.',
            ],
            [
                'question' => 'What happens when my subscription ends?',
                'answer' => 'Code you already downloaded stays yours to use forever under the license — usage rights never expire. Access to new downloads and future drops ends with the subscription.',
            ],
            [
                'question' => 'Can I use the components in client work?',
                'answer' => 'Yes — every paid plan covers unlimited personal and commercial projects, including client work. Redistribution, resale or publishing as a competing library is not allowed.',
            ],
            [
                'question' => 'What do I get on the Free plan?',
                'answer' => 'A rotating 20–30% subset of the catalog to browse, preview, copy and download — no card required. Upgrade when you need the full library.',
            ],
        ];
    }

    private function refundWindow(): int
    {
        return (int) $this->settings->get('billing.refund_window_days');
    }
}
