import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Paperclip } from 'lucide-react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';

interface TicketMessage {
    id: number;
    author_type: string;
    body: string;
    attachments: { name: string; size: number }[];
    created_at: string;
}

interface TicketShowProps {
    ticket: {
        id: number;
        subject: string;
        category: string;
        status: string;
        created_at: string;
    };
    messages: TicketMessage[];
}

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    open: 'default',
    pending: 'secondary',
    resolved: 'outline',
    closed: 'outline',
};

/**
 * Ticket thread (SPEC §13.3, CSR): the full conversation with a reply box.
 * Replying to a pending/resolved ticket re-opens it; closed tickets reject
 * replies. The user can close an active ticket from here.
 */
export default function TicketShow({ ticket, messages }: TicketShowProps) {
    const { flash } = usePage<SharedData & { flash?: { notice?: string | null } }>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Support',
            href: '/dashboard/tickets',
        },
        {
            title: ticket.subject,
            href: `/dashboard/tickets/${ticket.id}`,
        },
    ];

    const replyForm = useForm<{ body: string; attachments: File[] }>({
        body: '',
        attachments: [],
    });

    const closed = ticket.status === 'closed';

    const reply: FormEventHandler = (e) => {
        e.preventDefault();

        replyForm.post(route('dashboard.tickets.messages.store', ticket.id), {
            forceFormData: true,
            onSuccess: () => replyForm.reset(),
        });
    };

    const closeTicket = () => {
        router.patch(route('dashboard.tickets.update', ticket.id), { status: 'closed' });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={ticket.subject} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <HeadingSmall title={ticket.subject} description={`Opened ${new Date(ticket.created_at).toLocaleDateString()}`} />
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary" className="capitalize">
                            {ticket.category}
                        </Badge>
                        <Badge variant={statusVariant[ticket.status] ?? 'outline'} className="capitalize">
                            {ticket.status}
                        </Badge>
                        {!closed && (
                            <Button variant="outline" size="sm" onClick={closeTicket}>
                                Close ticket
                            </Button>
                        )}
                    </div>
                </div>

                {flash?.notice && <p className="text-sm text-green-600 dark:text-green-400">{flash.notice}</p>}

                <ol className="flex flex-col gap-3">
                    {messages.map((message) => (
                        <li
                            key={message.id}
                            className={
                                message.author_type === 'admin'
                                    ? 'border-sidebar-border/70 dark:border-sidebar-border bg-sidebar-accent/30 rounded-xl border p-4'
                                    : 'border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4'
                            }
                        >
                            <div className="mb-1 flex items-center justify-between gap-2 text-sm">
                                <span className="font-medium">{message.author_type === 'admin' ? 'Support' : 'You'}</span>
                                <span className="text-neutral-500">{new Date(message.created_at).toLocaleString()}</span>
                            </div>
                            <p className="text-sm whitespace-pre-wrap">{message.body}</p>
                            {message.attachments.length > 0 && (
                                <ul className="mt-2 flex flex-wrap gap-2 text-sm text-neutral-500">
                                    {message.attachments.map((attachment, index) => (
                                        <li key={index} className="flex items-center gap-1">
                                            <Paperclip className="size-3.5" />
                                            {attachment.name}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    ))}
                </ol>

                {closed ? (
                    <p className="text-sm text-neutral-500">This ticket is closed — open a new ticket if you need more help.</p>
                ) : (
                    <form onSubmit={reply} className="flex max-w-xl flex-col gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="body">Reply</Label>
                            <textarea
                                id="body"
                                value={replyForm.data.body}
                                onChange={(e) => replyForm.setData('body', e.target.value)}
                                rows={4}
                                required
                                className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-base focus-visible:ring-2 focus-visible:outline-hidden md:text-sm"
                            />
                            <InputError message={replyForm.errors.body} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="reply-attachments">Attachments (optional)</Label>
                            <Input
                                id="reply-attachments"
                                type="file"
                                multiple
                                onChange={(e) => replyForm.setData('attachments', Array.from(e.target.files ?? []))}
                            />
                            <InputError message={replyForm.errors.attachments} />
                        </div>
                        <div>
                            <Button type="submit" disabled={replyForm.processing}>
                                Send reply
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
