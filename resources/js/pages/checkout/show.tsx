import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

/**
 * Paddle.js v2 global (loaded from the Paddle CDN on mount).
 */
declare global {
    interface Window {
        Paddle?: {
            Initialize: (options: { token: string; environment?: 'sandbox' | 'production' }) => void;
            Checkout: {
                open: (options: {
                    items: { priceId: string; quantity: number }[];
                    customer?: { id: string };
                    customData?: Record<string, string>;
                    settings?: { successUrl?: string; allowLogout?: boolean };
                }) => void;
            };
        };
    }
}

interface PeriodPrice {
    amount: string | null;
    currency: string;
    price_id: string | null;
}

interface CheckoutProps {
    plan: string;
    selectedPeriod: string;
    periods: Record<string, PeriodPrice>;
    seats: number;
    maxSeats: number;
    checkout: {
        items: { priceId: string; quantity: number }[];
        customer?: { id: string };
        customData?: Record<string, string>;
    };
    paddle: {
        token: string | null;
        environment: 'sandbox' | 'production';
    };
    successUrl: string;
    currencySwitchUrl: string;
}

const PERIOD_LABELS: Record<string, string> = {
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
    lifetime: 'Lifetime',
};

/**
 * Paddle overlay checkout host (CSR, noindex — SPEC §15.3). Server props
 * carry the checkout session for the selected plan × period; switching the
 * period re-requests the page with a new `?period=` query.
 */
export default function Checkout({ plan, selectedPeriod, periods, seats, maxSeats, checkout, paddle, successUrl, currencySwitchUrl }: CheckoutProps) {
    const [paddleReady, setPaddleReady] = useState(false);

    useEffect(() => {
        const script = document.createElement('script');
        script.src = 'https://cdn.paddle.com/paddle/v2/paddle.js';
        script.async = true;
        script.onload = () => {
            if (window.Paddle && paddle.token) {
                window.Paddle.Initialize({ token: paddle.token, environment: paddle.environment });
                setPaddleReady(true);
            }
        };
        document.body.appendChild(script);

        return () => {
            document.body.removeChild(script);
        };
    }, [paddle.token, paddle.environment]);

    const selectPeriod = (period: string) => {
        if (period !== selectedPeriod) {
            router.get(`/checkout/${plan}`, { period, seats }, { preserveState: false });
        }
    };

    // Team tier (task 5.2): changing the seat count re-requests the page,
    // rebuilding the checkout session with the new line item quantity.
    const selectSeats = (count: number) => {
        if (count !== seats) {
            router.get(`/checkout/${plan}`, { period: selectedPeriod, seats: count }, { preserveState: false });
        }
    };

    const openCheckout = () => {
        window.Paddle?.Checkout.open({
            items: checkout.items,
            customer: checkout.customer,
            customData: checkout.customData,
            settings: { successUrl, allowLogout: false },
        });
    };

    const selected = periods[selectedPeriod];

    return (
        <AuthLayout
            title={`Checkout — ${plan.charAt(0).toUpperCase() + plan.slice(1)}`}
            description="Pick a billing period and complete your purchase securely with Paddle."
        >
            <Head title="Checkout" />

            <div className="flex flex-col gap-6">
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4" role="tablist" aria-label="Billing period">
                    {Object.entries(periods).map(([period, price]) => (
                        <button
                            key={period}
                            type="button"
                            role="tab"
                            aria-selected={period === selectedPeriod}
                            onClick={() => selectPeriod(period)}
                            className={`rounded-lg border px-3 py-2.5 text-sm font-medium transition ${
                                period === selectedPeriod
                                    ? 'border-neutral-900 bg-neutral-900 text-white dark:border-neutral-100 dark:bg-neutral-100 dark:text-neutral-900'
                                    : 'border-neutral-300 bg-white text-neutral-700 hover:border-neutral-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300'
                            }`}
                        >
                            <span className="block">{PERIOD_LABELS[period] ?? period}</span>
                            {price.amount !== null && (
                                <span className="mt-0.5 block text-xs opacity-75">
                                    {price.currency} {price.amount}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                {plan === 'team' && (
                    <div className="flex items-center justify-center gap-3">
                        <label htmlFor="seats" className="text-sm text-neutral-600 dark:text-neutral-400">
                            Seats
                        </label>
                        <input
                            id="seats"
                            type="number"
                            min={1}
                            max={maxSeats}
                            value={seats}
                            onChange={(event) => selectSeats(Math.max(1, Math.min(maxSeats, Number(event.target.value) || 1)))}
                            className="w-20 rounded-md border border-neutral-300 px-3 py-1.5 text-center text-sm focus:border-neutral-900 focus:outline-none dark:border-neutral-700 dark:bg-neutral-900"
                        />
                        <span className="text-xs text-neutral-400">per-seat pricing · every seat gets Pro features</span>
                    </div>
                )}

                {selected?.amount !== null && selected?.amount !== undefined && (
                    <p className="text-center text-sm text-neutral-600 dark:text-neutral-400">
                        {plan.charAt(0).toUpperCase() + plan.slice(1)} · {PERIOD_LABELS[selectedPeriod] ?? selectedPeriod} ·{' '}
                        <span className="font-semibold text-neutral-900 dark:text-neutral-100">
                            {selected.currency} {selected.amount}
                        </span>
                        {plan === 'team' && (
                            <>
                                {' '}
                                × {seats} {seats === 1 ? 'seat' : 'seats'} ={' '}
                                <span className="font-semibold text-neutral-900 dark:text-neutral-100">
                                    {selected.currency} {(Number(selected.amount) * seats).toFixed(2)}
                                </span>
                            </>
                        )}
                    </p>
                )}

                <Button type="button" size="lg" className="w-full" disabled={!paddleReady} onClick={openCheckout}>
                    {paddleReady ? 'Complete purchase' : 'Loading secure checkout…'}
                </Button>

                <p className="text-center text-xs text-neutral-500">
                    Payments are processed by Paddle, our merchant of record. 14-day refund window.
                </p>

                {/* Manual currency switch (SPEC §7.5): CNY routes to the domestic QR checkout. */}
                <p className="text-center text-xs text-neutral-500">
                    在中国大陆？{' '}
                    <button
                        type="button"
                        className="underline underline-offset-2 transition hover:text-neutral-700 dark:hover:text-neutral-300"
                        onClick={() => router.post(currencySwitchUrl, { currency: 'CNY' })}
                    >
                        使用人民币支付（支付宝 / 微信支付）
                    </button>
                </p>
            </div>
        </AuthLayout>
    );
}
