import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
        title: 'Support',
        href: '/dashboard/tickets',
    },
    {
        title: 'New ticket',
        href: '/dashboard/tickets/new',
    },
];

interface NewTicketProps {
    categories: Record<string, string>;
}

/**
 * New support ticket (SPEC §13.3, CSR): subject, category and the opening
 * message with optional attachments (private disk, max 3 × 5 MB).
 */
export default function NewTicket({ categories }: NewTicketProps) {
    const { data, setData, post, processing, errors } = useForm<{
        subject: string;
        category: string;
        body: string;
        attachments: File[];
    }>({
        subject: '',
        category: '',
        body: '',
        attachments: [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('dashboard.tickets.store'), { forceFormData: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New ticket" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <HeadingSmall
                    title="Open a support ticket"
                    description="Pick the closest category — use “Takedown” for copyright removal requests. We reply by email and in the thread."
                />

                <form onSubmit={submit} className="flex max-w-xl flex-col gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="subject">Subject</Label>
                        <Input
                            id="subject"
                            value={data.subject}
                            onChange={(e) => setData('subject', e.target.value)}
                            placeholder="What do you need help with?"
                            required
                        />
                        <InputError message={errors.subject} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="category">Category</Label>
                        <Select value={data.category} onValueChange={(value) => setData('category', value)}>
                            <SelectTrigger id="category" className="w-full">
                                <SelectValue placeholder="Choose a category" />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(categories).map(([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.category} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="body">Message</Label>
                        <textarea
                            id="body"
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            placeholder="Describe the issue — steps to reproduce help us answer faster."
                            rows={6}
                            required
                            className="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-base focus-visible:ring-2 focus-visible:outline-hidden md:text-sm"
                        />
                        <InputError message={errors.body} />
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="attachments">Attachments (optional, up to 3 files · 5 MB each)</Label>
                        <Input id="attachments" type="file" multiple onChange={(e) => setData('attachments', Array.from(e.target.files ?? []))} />
                        <InputError message={errors.attachments} />
                    </div>

                    <div>
                        <Button type="submit" disabled={processing}>
                            Submit ticket
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
