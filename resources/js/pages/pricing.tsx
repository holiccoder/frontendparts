import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { PageMeta } from '@/types/catalog';
import { Link, router } from '@inertiajs/react';
import { Check, Minus } from 'lucide-react';
import { useState } from 'react';

type Period = 'monthly' | 'quarterly' | 'yearly' | 'lifetime';

interface PeriodPrice {
    amount: string | null;
    currency: string;
    per_month: string | null;
}

interface PaidPlan {
    name: string;
    tagline: string;
    checkout_url: string;
    prices: Record<Period, PeriodPrice>;
}

type CellValue = boolean | string;

interface ComparisonRow {
    feature: string;
    free: CellValue;
    starter: CellValue;
    pro: CellValue;
}

interface FaqItem {
    question: string;
    answer: string;
}

interface PricingProps {
    periods: Period[];
    plans: {
        starter: PaidPlan;
        pro: PaidPlan;
        team: PaidPlan;
    };
    currency: string;
    currencySwitchUrl: string;
    comparison: ComparisonRow[];
    faq: FaqItem[];
    meta: PageMeta;
}

const PERIOD_LABELS: Record<Period, string> = {
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
    lifetime: 'Lifetime',
};

const PERIOD_UNITS: Record<Period, string> = {
    monthly: 'month',
    quarterly: 'quarter',
    yearly: 'year',
    lifetime: 'once',
};

function formatAmount(amount: string, currency: string): string {
    const value = Number(amount);
    const formatted = Number.isInteger(value) ? value.toFixed(0) : value.toFixed(2);

    return currency === 'USD' ? `$${formatted}` : `${currency} ${formatted}`;
}

function Cell({ value }: { value: CellValue }) {
    if (value === true) {
        return <Check className="h-4 w-4 text-neutral-900" aria-label="Included" />;
    }

    if (value === false) {
        return <Minus className="h-4 w-4 text-neutral-300" aria-label="Not included" />;
    }

    return <span className="text-neutral-600">{value}</span>;
}

function PaidPlanCard({ plan, period }: { plan: PaidPlan; period: Period }) {
    const price = plan.prices[period];
    const bestValue = period === 'yearly';
    return (
        <div
            className={`relative flex flex-col rounded-2xl border bg-white p-8 transition ${
                bestValue ? 'border-neutral-900 shadow-[0_8px_30px_rgb(0,0,0,0.08)]' : 'border-neutral-200'
            }`}
        >
            {bestValue && (
                <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-neutral-900 px-3 py-1 text-xs font-semibold tracking-wide text-white uppercase">
                    Best value
                </span>
            )}

            <h3 className="text-sm font-semibold tracking-wide uppercase">{plan.name}</h3>

            <div className="mt-4 flex items-baseline gap-1.5">
                {price.amount !== null ? (
                    <>
                        <span className="text-4xl font-semibold tracking-tight">{formatAmount(price.amount, price.currency)}</span>
                        {period !== 'lifetime' && <span className="text-sm text-neutral-500">/ {PERIOD_UNITS[period]}</span>}
                    </>
                ) : (
                    <span className="text-4xl font-semibold tracking-tight text-neutral-300">—</span>
                )}
            </div>

            <p className="mt-1 text-xs text-neutral-400">
                {price.amount === null && 'Currently unavailable for this period.'}
                {price.amount !== null && period === 'monthly' && 'Billed monthly · cancel anytime.'}
                {price.amount !== null &&
                    (period === 'quarterly' || period === 'yearly') &&
                    price.per_month !== null &&
                    `≈ ${formatAmount(price.per_month, price.currency)}/mo · billed ${PERIOD_UNITS[period]}ly.`}
                {price.amount !== null && period === 'lifetime' && 'One-time payment · access forever.'}
            </p>

            <p className="mt-3 text-sm leading-6 text-neutral-500">{plan.tagline}</p>

            {price.amount !== null ? (
                <Link
                    href={`${plan.checkout_url}?period=${period}`}
                    className={`mt-8 inline-flex items-center justify-center rounded-md px-4 py-2.5 text-sm font-semibold transition ${
                        bestValue
                            ? 'bg-neutral-900 text-white hover:bg-neutral-700'
                            : 'border border-neutral-300 text-neutral-700 hover:border-neutral-400 hover:bg-neutral-50'
                    }`}
                >
                    Get {plan.name}
                </Link>
            ) : (
                <span className="mt-8 inline-flex cursor-not-allowed items-center justify-center rounded-md border border-neutral-200 px-4 py-2.5 text-sm font-semibold text-neutral-300">
                    Unavailable
                </span>
            )}
        </div>
    );
}

/**
 * Team tier card (task 5.2): per-seat pricing with a seat count selector.
 * The displayed amount is the per-seat plan_prices row for the selected
 * period; checkout receives the chosen seats as a query param.
 */
function TeamPlanCard({ plan, period }: { plan: PaidPlan; period: Period }) {
    const [seats, setSeats] = useState(3);
    const price = plan.prices[period];
    const available = price.amount !== null;

    const total = available ? (Number(price.amount) * seats).toFixed(2) : null;

    return (
        <div className="flex flex-col gap-6 rounded-2xl border border-neutral-200 bg-white p-8 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex max-w-md flex-col gap-2">
                <h3 className="text-sm font-semibold tracking-wide uppercase">{plan.name}</h3>
                <div className="flex items-baseline gap-1.5">
                    {available ? (
                        <>
                            <span className="text-3xl font-semibold tracking-tight">{formatAmount(price.amount, price.currency)}</span>
                            <span className="text-sm text-neutral-500">/ seat{period !== 'lifetime' ? ` / ${PERIOD_UNITS[period]}` : ''}</span>
                        </>
                    ) : (
                        <span className="text-3xl font-semibold tracking-tight text-neutral-300">—</span>
                    )}
                </div>
                <p className="mt-1 text-xs text-neutral-400">
                    {available
                        ? 'Every seat gets everything in Pro — full library, scaffolding and exports.'
                        : 'Currently unavailable for this period.'}
                </p>
                <p className="text-sm leading-6 text-neutral-500">{plan.tagline}</p>
            </div>

            <div className="flex min-w-52 flex-col gap-2">
                <label htmlFor="team-seats" className="text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                    Seats
                </label>
                <input
                    id="team-seats"
                    type="number"
                    min={1}
                    max={100}
                    value={seats}
                    disabled={!available}
                    onChange={(event) => setSeats(Math.max(1, Math.min(100, Number(event.target.value) || 1)))}
                    className="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm focus:border-neutral-900 focus:outline-none"
                />
                {total !== null && (
                    <p className="text-sm text-neutral-600">
                        Total: <span className="font-semibold text-neutral-900">{formatAmount(total, price.currency)}</span>
                        {period !== 'lifetime' && <span className="text-neutral-400"> / {PERIOD_UNITS[period]}</span>}
                    </p>
                )}
                {available ? (
                    <Link
                        href={`${plan.checkout_url}?period=${period}&seats=${seats}`}
                        className="mt-2 inline-flex items-center justify-center rounded-md border border-neutral-300 px-4 py-2.5 text-sm font-semibold text-neutral-700 transition hover:border-neutral-400 hover:bg-neutral-50"
                    >
                        Get {plan.name}
                    </Link>
                ) : (
                    <span className="mt-2 inline-flex cursor-not-allowed items-center justify-center rounded-md border border-neutral-200 px-4 py-2.5 text-sm font-semibold text-neutral-300">
                        Unavailable
                    </span>
                )}
            </div>
        </div>
    );
}

/**
 * `/pricing` (SSR — SPEC §7.2, §15.1): plan × period toggle with prices
 * from `plan_prices` via Inertia props, the SPEC §7.1 feature comparison
 * and a billing FAQ. Yearly is highlighted as the best value; lifetime is
 * presented as a permanent offering. The period toggle is client-side only;
 * the currency switch (SPEC §7.5) posts the choice to the server, which
 * persists it in the session and re-prices the page.
 */
export default function Pricing({ periods, plans, currency, currencySwitchUrl, comparison, faq, meta }: PricingProps) {
    const [period, setPeriod] = useState<Period>('yearly');

    const switchCurrency = (option: string) => {
        if (option !== currency) {
            router.post(currencySwitchUrl, { currency: option }, { preserveScroll: true });
        }
    };

    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            {/* Hero + period toggle */}
            <section className="border-b border-neutral-100">
                <div className="mx-auto max-w-7xl px-4 py-20 text-center sm:px-6 sm:py-24 lg:px-8">
                    <span className="inline-flex items-center rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold tracking-wide text-neutral-600 uppercase">
                        Pricing
                    </span>
                    <h1 className="mx-auto mt-6 max-w-2xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                        One library, every future drop
                    </h1>
                    <p className="mx-auto mt-6 max-w-xl text-lg leading-8 text-neutral-500">
                        Start free with a rotating subset. Upgrade for the entire catalog in React and Vue — monthly, yearly, or once for lifetime.
                    </p>

                    <div className="mt-10 flex justify-center">
                        <div
                            className="inline-flex flex-wrap items-center justify-center gap-1 rounded-full border border-neutral-200 bg-white p-1"
                            role="tablist"
                            aria-label="Billing period"
                        >
                            {periods.map((option) => (
                                <button
                                    key={option}
                                    type="button"
                                    role="tab"
                                    aria-selected={option === period}
                                    onClick={() => setPeriod(option)}
                                    className={`rounded-full px-4 py-2 text-sm font-medium transition ${
                                        option === period ? 'bg-neutral-900 text-white' : 'text-neutral-600 hover:text-neutral-900'
                                    }`}
                                >
                                    {PERIOD_LABELS[option]}
                                    {option === 'yearly' && (
                                        <span
                                            className={`ml-1.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase ${
                                                option === period ? 'bg-white/20 text-white' : 'bg-neutral-100 text-neutral-600'
                                            }`}
                                        >
                                            Best value
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Currency switch (SPEC §7.5): USD/Paddle vs CNY/支付宝·微信支付. */}
                    <div className="mt-4 flex justify-center">
                        <div
                            className="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-white p-1"
                            role="tablist"
                            aria-label="Currency"
                        >
                            {['USD', 'CNY'].map((option) => (
                                <button
                                    key={option}
                                    type="button"
                                    role="tab"
                                    aria-selected={option === currency}
                                    onClick={() => switchCurrency(option)}
                                    className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                                        option === currency ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:text-neutral-900'
                                    }`}
                                >
                                    {option === 'USD' ? '$ USD' : '¥ CNY'}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* Plan cards */}
            <section className="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div className="flex flex-col rounded-2xl border border-neutral-200 bg-white p-8">
                        <h3 className="text-sm font-semibold tracking-wide uppercase">Free</h3>
                        <div className="mt-4 flex items-baseline gap-1.5">
                            <span className="text-4xl font-semibold tracking-tight">$0</span>
                        </div>
                        <p className="mt-1 text-xs text-neutral-400">No card required.</p>
                        <p className="mt-3 text-sm leading-6 text-neutral-500">A rotating 20–30% subset of the catalog to try everything.</p>
                        <Link
                            href="/register"
                            className="mt-8 inline-flex items-center justify-center rounded-md border border-neutral-300 px-4 py-2.5 text-sm font-semibold text-neutral-700 transition hover:border-neutral-400 hover:bg-neutral-50"
                        >
                            Create free account
                        </Link>
                    </div>

                    <PaidPlanCard plan={plans.starter} period={period} />
                    <PaidPlanCard plan={plans.pro} period={period} />
                </div>

                {/* Team tier (task 5.2): per-seat pricing with a seat selector. */}
                <div className="mt-6">
                    <TeamPlanCard plan={plans.team} period={period} />
                </div>

                <p className="mt-8 text-center text-xs leading-5 text-neutral-400">
                    {period === 'lifetime'
                        ? 'Lifetime is a permanent offering — pay once and keep access to the full library and every future drop, forever.'
                        : currency === 'CNY'
                          ? 'Prices in CNY · 支持支付宝、微信支付 · 每个周期一次性付款，到期前邮件提醒续费。'
                          : 'Prices in USD · Secure payments by Paddle, our merchant of record · Cancel anytime, keep access until the period ends.'}
                </p>
            </section>

            {/* Feature comparison */}
            <section className="border-y border-neutral-100 bg-neutral-50/60">
                <div className="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:px-8">
                    <h2 className="text-center text-2xl font-semibold tracking-tight sm:text-3xl">Compare plans</h2>
                    <p className="mx-auto mt-3 max-w-xl text-center text-neutral-500">Every plan reads the full catalog. Paid plans unlock it.</p>
                    <div className="mt-10 overflow-x-auto rounded-2xl border border-neutral-200 bg-white">
                        <table className="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 bg-neutral-50/60 text-left">
                                    <th scope="col" className="px-6 py-4 font-semibold">
                                        Feature
                                    </th>
                                    <th scope="col" className="w-28 px-6 py-4 font-semibold">
                                        Free
                                    </th>
                                    <th scope="col" className="w-28 px-6 py-4 font-semibold">
                                        Starter
                                    </th>
                                    <th scope="col" className="w-28 px-6 py-4 font-semibold">
                                        Pro
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {comparison.map((row) => (
                                    <tr key={row.feature} className="border-b border-neutral-100 last:border-0">
                                        <th scope="row" className="px-6 py-4 text-left font-medium text-neutral-700">
                                            {row.feature}
                                        </th>
                                        <td className="px-6 py-4">
                                            <Cell value={row.free} />
                                        </td>
                                        <td className="px-6 py-4">
                                            <Cell value={row.starter} />
                                        </td>
                                        <td className="px-6 py-4">
                                            <Cell value={row.pro} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {/* FAQ */}
            <section className="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:px-8">
                <h2 className="text-center text-2xl font-semibold tracking-tight sm:text-3xl">Frequently asked questions</h2>
                <div className="mt-10 grid gap-x-12 gap-y-8 sm:grid-cols-2">
                    {faq.map((item) => (
                        <div key={item.question}>
                            <h3 className="text-base font-semibold">{item.question}</h3>
                            <p className="mt-2 text-sm leading-6 text-neutral-500">{item.answer}</p>
                        </div>
                    ))}
                </div>
            </section>
        </PublicLayout>
    );
}
