import { Head, Link } from '@inertiajs/react';
import { LifeBuoy, Plus } from 'lucide-react';

import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Support',
        href: '/dashboard/tickets',
    },
];

interface TicketSummary {
    id: number;
    subject: string;
    category: string;
    status: string;
    messages_count: number;
    created_at: string;
    url: string;
}

interface TicketsIndexProps {
    tickets: TicketSummary[];
}

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    open: 'default',
    pending: 'secondary',
    resolved: 'outline',
    closed: 'outline',
};

/**
 * Support ticket list (SPEC §13.3, §15.4, CSR): the user's tickets
 * newest-first with a create shortcut.
 */
export default function TicketsIndex({ tickets }: TicketsIndexProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Support" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <HeadingSmall
                        title="Support tickets"
                        description="Questions about billing, licenses or the library — we reply by email and in the thread."
                    />
                    <Button asChild size="sm">
                        <Link href="/dashboard/tickets/new">
                            <Plus className="size-4" />
                            New ticket
                        </Link>
                    </Button>
                </div>

                {tickets.length === 0 ? (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <LifeBuoy className="size-8 text-neutral-400" />
                        <p className="text-sm text-neutral-500">No tickets yet — open one if you need a hand.</p>
                    </div>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {tickets.map((ticket) => (
                            <li key={ticket.id}>
                                <Link
                                    href={ticket.url}
                                    className="border-sidebar-border/70 dark:border-sidebar-border hover:bg-sidebar-accent/50 block rounded-xl border p-4 transition-colors"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium">{ticket.subject}</span>
                                        <div className="flex items-center gap-2">
                                            <Badge variant="secondary" className="capitalize">
                                                {ticket.category}
                                            </Badge>
                                            <Badge variant={statusVariant[ticket.status] ?? 'outline'} className="capitalize">
                                                {ticket.status}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div className="mt-1 text-sm text-neutral-500">
                                        {ticket.messages_count} {ticket.messages_count === 1 ? 'message' : 'messages'} · opened{' '}
                                        {new Date(ticket.created_at).toLocaleDateString()}
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
