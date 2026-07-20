import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

interface LicenseSummary {
    plan: string;
    billing_period: string;
    status: string;
    amount: string;
    currency: string;
    starts_at: string | null;
    ends_at: string | null;
}

interface CheckoutSuccessProps {
    license: LicenseSummary | null;
    nextSteps: { label: string; href: string }[];
}

const STATUS_LABELS: Record<string, string> = {
    pending: 'Confirming payment',
    active: 'Active',
    past_due: 'Payment due',
    cancelled: 'Cancelled',
    expired: 'Expired',
    refunded: 'Refunded',
};

/**
 * Post-payment confirmation (CSR, noindex — SPEC §15.3): license summary +
 * next steps. The webhook may land after Paddle bounces the buyer here, so
 * a still-pending order is shown as "confirming".
 */
export default function CheckoutSuccess({ license, nextSteps }: CheckoutSuccessProps) {
    return (
        <AuthLayout title="You're all set" description="Thanks for your purchase — here is your license summary.">
            <Head title="Purchase complete" />

            <div className="flex flex-col gap-6">
                {license ? (
                    <dl className="divide-y divide-neutral-200 rounded-xl border border-neutral-200 text-sm dark:divide-neutral-800 dark:border-neutral-800">
                        <div className="flex items-center justify-between px-4 py-3">
                            <dt className="text-neutral-500">Plan</dt>
                            <dd className="font-medium capitalize">{license.plan}</dd>
                        </div>
                        <div className="flex items-center justify-between px-4 py-3">
                            <dt className="text-neutral-500">Billing period</dt>
                            <dd className="font-medium capitalize">{license.billing_period}</dd>
                        </div>
                        <div className="flex items-center justify-between px-4 py-3">
                            <dt className="text-neutral-500">License status</dt>
                            <dd className="font-medium">{STATUS_LABELS[license.status] ?? license.status}</dd>
                        </div>
                        <div className="flex items-center justify-between px-4 py-3">
                            <dt className="text-neutral-500">Amount</dt>
                            <dd className="font-medium">
                                {license.currency} {license.amount}
                            </dd>
                        </div>
                        {license.ends_at && (
                            <div className="flex items-center justify-between px-4 py-3">
                                <dt className="text-neutral-500">Current term until</dt>
                                <dd className="font-medium">{new Date(license.ends_at).toLocaleDateString()}</dd>
                            </div>
                        )}
                        {license.billing_period === 'lifetime' && (
                            <div className="flex items-center justify-between px-4 py-3">
                                <dt className="text-neutral-500">Access</dt>
                                <dd className="font-medium">Never expires</dd>
                            </div>
                        )}
                    </dl>
                ) : (
                    <p className="text-center text-sm text-neutral-500">Your license is being prepared — check your dashboard in a moment.</p>
                )}

                <div className="flex flex-col gap-2">
                    {nextSteps.map((step) => (
                        <Button key={step.href} asChild variant="outline" className="w-full">
                            <Link href={step.href}>{step.label}</Link>
                        </Button>
                    ))}
                </div>
            </div>
        </AuthLayout>
    );
}
