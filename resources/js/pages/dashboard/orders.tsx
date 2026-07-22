import { Head, Link } from '@inertiajs/react';
import { ExternalLink, ReceiptText } from 'lucide-react';

import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { licenseStateBadgeVariant, licenseStateLabel } from '@/lib/license';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Orders',
        href: '/dashboard/orders',
    },
];

interface OrderRow {
    id: number;
    plan: string;
    status: string;
    license_state: string;
    billing_period: string;
    is_lifetime: boolean;
    amount: string;
    currency: string;
    starts_at: string | null;
    ends_at: string | null;
    cancelled_at: string | null;
    created_at: string;
    receipt_url: string | null;
}

interface OrdersPageProps {
    orders: OrderRow[];
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString();
}

/**
 * Renewal/expiry line per license state (SPEC §15.4): lifetime never expires,
 * a cancelled license inside the paid term runs until ends_at, active and
 * past-due orders renew at ends_at, and expired orders show when access ended.
 */
function termLabel(order: OrderRow): string | null {
    if (order.is_lifetime) {
        return 'Never expires';
    }

    if (order.ends_at === null) {
        return null;
    }

    switch (order.license_state) {
        case 'cancelled_valid_until':
            return `Access until ${formatDate(order.ends_at)}`;
        case 'active':
        case 'past_due':
            return `Renews ${formatDate(order.ends_at)}`;
        case 'expired':
            return `Ended ${formatDate(order.ends_at)}`;
        default:
            return null;
    }
}

/**
 * Orders page (SPEC §15.4, CSR): order history with Paddle receipt/invoice
 * links, license state badges and renewal dates.
 */
export default function OrdersPage({ orders }: OrdersPageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Orders" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <HeadingSmall
                    title="Orders & license"
                    description="Your purchases, license state and renewal dates. Receipts and invoices are issued by Paddle, our merchant of record, and are also emailed after every payment."
                />

                {orders.length === 0 ? (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <ReceiptText className="size-8 text-neutral-400" />
                        <p className="text-sm text-neutral-500">No orders yet — upgrade to unlock everything a paid plan includes.</p>
                        <Button asChild size="sm">
                            <Link href="/pricing">View pricing</Link>
                        </Button>
                    </div>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {orders.map((order) => (
                            <li key={order.id} className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="flex min-w-0 flex-col gap-1">
                                        <span className="font-medium capitalize">
                                            {order.plan} · {order.billing_period}
                                        </span>
                                        <span className="text-sm text-neutral-500">
                                            {order.currency} {order.amount} · ordered {formatDate(order.created_at)}
                                        </span>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <Badge variant={licenseStateBadgeVariant(order.license_state)}>
                                            {licenseStateLabel(order.license_state)}
                                        </Badge>
                                        {order.receipt_url && (
                                            <Button variant="outline" size="sm" asChild>
                                                <a href={order.receipt_url} target="_blank" rel="noopener noreferrer">
                                                    <ExternalLink className="size-4" />
                                                    Receipt / invoice
                                                </a>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div className="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-neutral-500">
                                    {order.starts_at && <span>Started {formatDate(order.starts_at)}</span>}
                                    {termLabel(order) && <span>{termLabel(order)}</span>}
                                    {order.cancelled_at && <span>Cancelled {formatDate(order.cancelled_at)}</span>}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
