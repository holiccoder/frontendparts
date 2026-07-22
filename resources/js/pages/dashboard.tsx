import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

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
];

interface PlanProps {
    name: string;
    is_paid: boolean;
    license: {
        state: string;
        status: string;
        billing_period: string;
        ends_at: string | null;
    } | null;
    cta: {
        kind: string;
        label: string;
        url: string;
    };
}

interface OrderSummary {
    id: number;
    plan: string;
    status: string;
    amount: string;
    currency: string;
    created_at: string;
}

interface OrdersProps {
    items: OrderSummary[];
    total: number;
    index_url: string;
}

interface DashboardProps {
    plan: PlanProps;
    orders: OrdersProps;
}

/**
 * Dashboard overview: plan status with the state-mapped primary action, an
 * orders summary, and a welcome placeholder where a new product mounts its
 * own widgets.
 */
export default function Dashboard({ plan, orders }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <div className="flex items-start justify-between gap-4">
                            <HeadingSmall title="Your plan" description="Current plan and license state" />
                            <Badge variant={plan.is_paid ? 'default' : 'secondary'} className="capitalize">
                                {plan.name}
                            </Badge>
                        </div>

                        {plan.license && (
                            <dl className="mt-4 space-y-2 text-sm">
                                <div className="flex items-center justify-between gap-4">
                                    <dt className="text-muted-foreground">License</dt>
                                    <dd>
                                        <Badge variant={licenseStateBadgeVariant(plan.license.state)}>{licenseStateLabel(plan.license.state)}</Badge>
                                    </dd>
                                </div>
                                <div className="flex items-center justify-between gap-4">
                                    <dt className="text-muted-foreground">Billing</dt>
                                    <dd className="capitalize">{plan.license.billing_period}</dd>
                                </div>
                                {plan.license.ends_at && (
                                    <div className="flex items-center justify-between gap-4">
                                        <dt className="text-muted-foreground">{plan.license.state === 'active' ? 'Renews' : 'Access until'}</dt>
                                        <dd>{new Date(plan.license.ends_at).toLocaleDateString()}</dd>
                                    </div>
                                )}
                            </dl>
                        )}

                        <div className="mt-6">
                            <Button asChild size="sm">
                                <Link href={plan.cta.url}>
                                    {plan.cta.label}
                                    <ArrowRight className="ml-1 h-4 w-4" />
                                </Link>
                            </Button>
                        </div>
                    </section>

                    <section className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                        <div className="flex items-start justify-between gap-4">
                            <HeadingSmall title="Orders" description={`${orders.total} order${orders.total === 1 ? '' : 's'} in total`} />
                            <Button asChild variant="ghost" size="sm">
                                <Link href={orders.index_url}>View all</Link>
                            </Button>
                        </div>

                        {orders.items.length === 0 ? (
                            <p className="mt-4 text-sm text-muted-foreground">No orders yet — your purchases will show up here.</p>
                        ) : (
                            <ul className="mt-4 divide-y divide-sidebar-border/70 text-sm dark:divide-sidebar-border">
                                {orders.items.map((order) => (
                                    <li key={order.id} className="flex items-center justify-between gap-4 py-2.5">
                                        <span className="capitalize">{order.plan}</span>
                                        <span className="text-muted-foreground">
                                            {order.amount} {order.currency.toUpperCase()}
                                        </span>
                                        <span className="text-muted-foreground capitalize">{order.status}</span>
                                        <time className="text-muted-foreground">{new Date(order.created_at).toLocaleDateString()}</time>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                <section className="rounded-xl border border-dashed border-sidebar-border/70 p-10 text-center dark:border-sidebar-border">
                    <h2 className="text-lg font-semibold tracking-tight">Welcome aboard</h2>
                    <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                        This is your dashboard home. Product widgets land here — plan status and orders are already wired up.
                    </p>
                </section>
            </div>
        </AppLayout>
    );
}
