import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, Check, Copy, Handshake } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Affiliate',
        href: '/dashboard/affiliate',
    },
];

interface MoneyBucket {
    currency: string;
    amount: string;
}

interface PayoutMethod {
    method: string;
    email: string;
    account_name: string | null;
}

interface CommissionRow {
    id: number;
    order_id: number;
    plan: string | null;
    amount: string;
    currency: string;
    status: string;
    payable_at: string | null;
    voided_reason: string | null;
    created_at: string;
}

interface PayoutRow {
    id: number;
    amount: string;
    currency: string;
    status: string;
    method: PayoutMethod | null;
    reference: string | null;
    paid_at: string | null;
    created_at: string;
}

interface AffiliateStats {
    clicks: number;
    signups: number;
    conversion_rate: number | null;
    earnings: {
        pending: MoneyBucket[];
        payable: MoneyBucket[];
        paid: MoneyBucket[];
    };
}

interface ProgramSettings {
    commission_rate: number;
    cookie_days: number;
    holding_days: number;
    payout_threshold: number;
}

interface AffiliatePageProps {
    affiliate: {
        code: string;
        status: string;
        referral_url: string;
        terms_accepted_at: string | null;
        payout_method: PayoutMethod | null;
    } | null;
    stats: AffiliateStats | null;
    commissions: CommissionRow[];
    payouts: PayoutRow[];
    settings: ProgramSettings;
    terms_url: string;
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString();
}

/** Mixed-currency buckets render as "USD 32.40 + CNY 10.00". */
function formatBuckets(buckets: MoneyBucket[]): string {
    if (buckets.length === 0) {
        return '—';
    }

    return buckets.map((bucket) => `${bucket.currency} ${bucket.amount}`).join(' + ');
}

function payoutMethodLabel(method: PayoutMethod | null): string {
    if (method === null) {
        return '—';
    }

    const rail = method.method === 'wise' ? 'Wise' : 'PayPal';

    return method.account_name ? `${rail} · ${method.email} (${method.account_name})` : `${rail} · ${method.email}`;
}

function statusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'pending':
        case 'processing':
            return 'secondary';
        case 'payable':
        case 'active':
            return 'default';
        case 'paid':
            return 'outline';
        case 'voided':
        case 'failed':
        case 'suspended':
            return 'destructive';
        default:
            return 'outline';
    }
}

/**
 * Affiliate dashboard (SPEC §17.4, CSR): the join flow for non-affiliates
 * (terms acceptance against /affiliate-terms, §17.7); for affiliates the
 * overview stats, referral link card, commissions table, payout history
 * and the payout-method form. Suspended affiliates see their history
 * read-only — new clicks and commissions already stop in the core engine.
 */
export default function AffiliatePage({ affiliate, stats, commissions, payouts, settings, terms_url }: AffiliatePageProps) {
    if (affiliate === null || stats === null) {
        return <JoinView settings={settings} termsUrl={terms_url} />;
    }

    return <DashboardView affiliate={affiliate} stats={stats} commissions={commissions} payouts={payouts} settings={settings} termsUrl={terms_url} />;
}

function JoinView({ settings, termsUrl }: { settings: ProgramSettings; termsUrl: string }) {
    const { data, setData, post, processing, errors } = useForm<{
        terms: boolean;
    }>({
        terms: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('dashboard.affiliate.join'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Affiliate program" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <HeadingSmall
                    title="Affiliate program"
                    description={`Earn ${settings.commission_rate}% commission on every purchase you refer — subscriptions keep earning for their first year.`}
                />

                <div className="border-sidebar-border/70 dark:border-sidebar-border flex max-w-2xl flex-col gap-4 rounded-xl border p-6">
                    <Handshake className="size-8 text-neutral-400" />
                    <div className="flex flex-col gap-2 text-sm text-neutral-600 dark:text-neutral-300">
                        <p>How it works:</p>
                        <ul className="list-disc space-y-1 pl-5">
                            <li>Share your tracked link — clicks are attributed with a {settings.cookie_days}-day cookie, last-click wins.</li>
                            <li>
                                You earn {settings.commission_rate}% of the net amount on every attributed purchase, including subscription renewals
                                in their first year.
                            </li>
                            <li>
                                Commissions become payable after the refund window plus a {settings.holding_days}-day holding period, and are paid
                                monthly once your balance reaches ${settings.payout_threshold}.
                            </li>
                        </ul>
                    </div>

                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="flex items-start space-x-3">
                            <Checkbox id="terms" checked={data.terms} onCheckedChange={(checked) => setData('terms', checked === true)} />
                            <Label htmlFor="terms" className="text-sm leading-snug font-normal">
                                I have read and accept the{' '}
                                <a href={termsUrl} target="_blank" rel="noopener noreferrer" className="underline underline-offset-4">
                                    Affiliate Program Terms
                                </a>{' '}
                                — including the FTC disclosure duty, the no brand-bidding rule and the clawback policy.
                            </Label>
                        </div>
                        <InputError message={errors.terms} />

                        <div>
                            <Button type="submit" disabled={processing || !data.terms}>
                                Join the affiliate program
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}

function DashboardView({
    affiliate,
    stats,
    commissions,
    payouts,
    settings,
    termsUrl,
}: {
    affiliate: NonNullable<AffiliatePageProps['affiliate']>;
    stats: AffiliateStats;
    commissions: CommissionRow[];
    payouts: PayoutRow[];
    settings: ProgramSettings;
    termsUrl: string;
}) {
    const suspended = affiliate.status === 'suspended';
    const [copied, setCopied] = useState(false);

    const copyLink = async () => {
        await navigator.clipboard.writeText(affiliate.referral_url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const { data, setData, put, processing, errors } = useForm<{
        method: string;
        email: string;
        account_name: string;
    }>({
        method: affiliate.payout_method?.method ?? '',
        email: affiliate.payout_method?.email ?? '',
        account_name: affiliate.payout_method?.account_name ?? '',
    });

    const submitPayoutMethod: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('dashboard.affiliate.payout-method.update'));
    };

    const statCards: { label: string; value: string }[] = [
        { label: 'Clicks', value: String(stats.clicks) },
        { label: 'Signups', value: String(stats.signups) },
        { label: 'Conversion', value: stats.conversion_rate === null ? '—' : `${stats.conversion_rate}%` },
        { label: 'Pending', value: formatBuckets(stats.earnings.pending) },
        { label: 'Payable', value: formatBuckets(stats.earnings.payable) },
        { label: 'Paid', value: formatBuckets(stats.earnings.paid) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Affiliate program" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <HeadingSmall
                    title="Affiliate program"
                    description={`You earn ${settings.commission_rate}% of the net amount on attributed purchases. Payouts run monthly once your payable balance reaches $${settings.payout_threshold}.`}
                />

                {suspended && (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex items-start gap-3 rounded-xl border p-4">
                        <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-500" />
                        <div className="text-sm">
                            <p className="font-medium">Your affiliate account is suspended.</p>
                            <p className="text-neutral-500">
                                Your referral link no longer records clicks or earns commissions. Your history below is kept read-only — contact
                                support if you believe this is a mistake.
                            </p>
                        </div>
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    {statCards.map((card) => (
                        <div key={card.label} className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                            <div className="text-xs tracking-wide text-neutral-500 uppercase">{card.label}</div>
                            <div className="mt-1 truncate text-lg font-semibold" title={card.value}>
                                {card.value}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-4">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-medium">Your referral link</h2>
                        <Badge variant={statusBadgeVariant(affiliate.status)}>{affiliate.status}</Badge>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Input readOnly value={affiliate.referral_url} onFocus={(e) => e.target.select()} className="font-mono text-sm" />
                        <Button type="button" variant="outline" onClick={copyLink} className="shrink-0">
                            {copied ? <Check className="size-4" /> : <Copy className="size-4" />}
                            {copied ? 'Copied' : 'Copy link'}
                        </Button>
                    </div>
                    <p className="text-sm text-neutral-500">
                        Code <span className="font-mono">{affiliate.code}</span> · {settings.cookie_days}-day cookie · remember to disclose that you
                        earn a commission wherever you share it (
                        <a href={termsUrl} target="_blank" rel="noopener noreferrer" className="underline underline-offset-4">
                            terms
                        </a>
                        ).
                    </p>
                </div>

                <div className="flex flex-col gap-3">
                    <h2 className="font-medium">Commissions</h2>
                    {commissions.length === 0 ? (
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border border-dashed p-8 text-center text-sm text-neutral-500">
                            No commissions yet — share your link to earn your first one.
                        </div>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {commissions.map((commission) => (
                                <li
                                    key={commission.id}
                                    className="border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3"
                                >
                                    <div className="flex min-w-0 flex-col gap-0.5">
                                        <span className="text-sm font-medium">
                                            {commission.currency} {commission.amount}
                                            {commission.plan && <span className="text-neutral-500"> · {commission.plan} order</span>}
                                        </span>
                                        <span className="text-xs text-neutral-500">
                                            {formatDate(commission.created_at)}
                                            {commission.payable_at && ` · payable since ${formatDate(commission.payable_at)}`}
                                            {commission.voided_reason && ` · ${commission.voided_reason}`}
                                        </span>
                                    </div>
                                    <Badge variant={statusBadgeVariant(commission.status)}>{commission.status}</Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="flex flex-col gap-3">
                        <h2 className="font-medium">Payout history</h2>
                        {payouts.length === 0 ? (
                            <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border border-dashed p-8 text-center text-sm text-neutral-500">
                                No payouts yet — batches run monthly over your payable balance.
                            </div>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {payouts.map((payout) => (
                                    <li
                                        key={payout.id}
                                        className="border-sidebar-border/70 dark:border-sidebar-border flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3"
                                    >
                                        <div className="flex min-w-0 flex-col gap-0.5">
                                            <span className="text-sm font-medium">
                                                {payout.currency} {payout.amount}
                                            </span>
                                            <span className="text-xs text-neutral-500">
                                                {payoutMethodLabel(payout.method)} · {formatDate(payout.created_at)}
                                                {payout.reference && ` · ref ${payout.reference}`}
                                            </span>
                                        </div>
                                        <Badge variant={statusBadgeVariant(payout.status)}>{payout.status}</Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className="flex flex-col gap-3">
                        <h2 className="font-medium">Payout method</h2>
                        <form
                            onSubmit={submitPayoutMethod}
                            className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-4 rounded-xl border p-4"
                        >
                            <div className="grid gap-1.5">
                                <Label htmlFor="method">Rail</Label>
                                <Select value={data.method} onValueChange={(value) => setData('method', value)} disabled={suspended}>
                                    <SelectTrigger id="method" className="w-full">
                                        <SelectValue placeholder="Choose a payout rail" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="paypal">PayPal</SelectItem>
                                        <SelectItem value="wise">Wise</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.method} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="email">Account email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="The email on your PayPal / Wise account"
                                    disabled={suspended}
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="account_name">Account name (optional)</Label>
                                <Input
                                    id="account_name"
                                    value={data.account_name}
                                    onChange={(e) => setData('account_name', e.target.value)}
                                    placeholder="Name on the account"
                                    disabled={suspended}
                                />
                                <InputError message={errors.account_name} />
                            </div>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing || suspended}>
                                    Save payout method
                                </Button>
                                {suspended && <span className="text-xs text-neutral-500">Frozen while suspended</span>}
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
