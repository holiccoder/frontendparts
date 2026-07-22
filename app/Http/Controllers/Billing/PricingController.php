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
 * `/pricing` (SSR, SEO-indexed): plan × period toggle fed straight from
 * `plan_prices`, a feature comparison, and a billing FAQ. Yearly is pushed
 * as the best value; lifetime is a permanent offering, not a promo. A
 * plan × period combo without a price row is rendered as unavailable, never
 * crashing the page.
 *
 * Currency follows RegionDetector: USD buyers see the Paddle rows, CNY
 * buyers the domestic rows, and the page surfaces the manual currency
 * switch. Both providers' rows come from `plan_prices` — never hardcoded.
 * Plan copy (taglines, comparison rows, FAQ) is product-specific: adjust it
 * here when you shape your own product.
 */
class PricingController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function __invoke(Request $request, RegionDetector $region): Response
    {
        $currency = $region->preferredCurrency($request);
        $provider = $currency === RegionDetector::CNY ? PlanProvider::Domestic : PlanProvider::Paddle;

        $appName = config('app.name');

        return Inertia::render('pricing', [
            'periods' => array_map(
                fn (BillingPeriod $period): string => $period->value,
                BillingPeriod::cases(),
            ),
            'plans' => [
                'starter' => $this->plan(OrderPlan::Starter, 'For individuals getting started.', $provider),
                'pro' => $this->plan(OrderPlan::Pro, 'For professionals who need everything.', $provider),
                'team' => $this->plan(OrderPlan::Team, 'Everything in Pro, for every seat on your team — priced per seat.', $provider),
            ],
            'currency' => $currency,
            'currencySwitchUrl' => route('billing.currency.switch'),
            'comparison' => $this->comparison(),
            'faq' => $this->faq(),
            'meta' => [
                'title' => "Pricing — {$appName} plans for individuals and teams",
                'description' => "{$appName} pricing: monthly, quarterly, yearly or lifetime billing, "
                    ."a {$this->refundWindow()}-day refund window and access you keep for the paid term.",
                'canonical' => URL::to('/pricing'),
                'og_image' => URL::to('/brand/logo.png'),
            ],
        ]);
    }

    /**
     * A paid plan with every billing period mapped to its `plan_prices`
     * row — prices are never hardcoded, so admin repricing shows up without
     * a deploy.
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
     * Monthly-equivalent figure for recurring periods ("$72 ($6/mo)");
     * lifetime is a one-time payment with no monthly figure.
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
     * Feature comparison rows. `true` renders a check, `false` a dash,
     * strings verbatim. Replace with your product's real feature matrix.
     *
     * @return list<array{feature: string, free: bool|string, starter: bool|string, pro: bool|string}>
     */
    private function comparison(): array
    {
        return [
            ['feature' => 'Core features', 'free' => true, 'starter' => true, 'pro' => true],
            ['feature' => 'Usage allowance', 'free' => 'Limited', 'starter' => 'Standard', 'pro' => 'Highest'],
            ['feature' => 'Priority support', 'free' => false, 'starter' => true, 'pro' => true],
            ['feature' => 'Advanced features', 'free' => false, 'starter' => false, 'pro' => true],
            ['feature' => 'Early access to new features', 'free' => false, 'starter' => false, 'pro' => true],
        ];
    }

    /**
     * Billing FAQ (refund window, cancellation, lifetime, expiry).
     *
     * @return list<array{question: string, answer: string}>
     */
    private function faq(): array
    {
        $window = $this->refundWindow();

        return [
            [
                'question' => 'Can I get a refund?',
                'answer' => "Yes. Every purchase is covered by a {$window}-day refund window from the date of payment — contact support and we refund in full.",
            ],
            [
                'question' => 'Can I cancel my subscription anytime?',
                'answer' => 'Anytime. Cancelling stops the next renewal, and you keep full access until the end of the period you already paid for.',
            ],
            [
                'question' => 'What does “lifetime” mean?',
                'answer' => 'A single one-time payment for permanent access — a permanent offering, not a promotion.',
            ],
            [
                'question' => 'What happens when my subscription ends?',
                'answer' => 'Your access continues until the end of the paid term, then your account returns to the Free plan. Your data stays put — resubscribe anytime.',
            ],
            [
                'question' => 'How does team pricing work?',
                'answer' => 'The Team plan is priced per seat: choose how many seats you need at checkout, then invite your team from the dashboard. Every seat gets the full Pro-equivalent feature set.',
            ],
        ];
    }

    private function refundWindow(): int
    {
        return (int) $this->settings->get('billing.refund_window_days');
    }
}
