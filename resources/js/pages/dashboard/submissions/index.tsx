import { Head, Link } from '@inertiajs/react';
import { Package, PackagePlus } from 'lucide-react';

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
        title: 'Submissions',
        href: '/dashboard/submissions',
    },
];

interface SubmissionSummary {
    id: number;
    name: string;
    level: string;
    framework: string;
    status: string;
    review_note: string | null;
    created_at: string;
}

interface SubmissionsIndexProps {
    submissions: SubmissionSummary[];
}

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'secondary',
    approved: 'default',
    rejected: 'destructive',
};

/**
 * Community submissions list (task 5.3, CSR): the user's submissions
 * newest-first with review status, the admin's review note on rejection and
 * a create shortcut.
 */
export default function SubmissionsIndex({ submissions }: SubmissionsIndexProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Submissions" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <HeadingSmall
                        title="Component submissions"
                        description="Contribute a component to the library — approved submissions are credited to you and go through the normal review pipeline."
                    />
                    <Button asChild size="sm">
                        <Link href="/dashboard/submissions/new">
                            <PackagePlus className="size-4" />
                            New submission
                        </Link>
                    </Button>
                </div>

                {submissions.length === 0 ? (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <Package className="size-8 text-neutral-400" />
                        <p className="text-sm text-neutral-500">No submissions yet — share a component you recreated and we'll review it.</p>
                    </div>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {submissions.map((submission) => (
                            <li key={submission.id} className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <span className="font-medium">{submission.name}</span>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="secondary" className="capitalize">
                                            {submission.level}
                                        </Badge>
                                        <Badge variant="secondary" className="capitalize">
                                            {submission.framework}
                                        </Badge>
                                        <Badge variant={statusVariant[submission.status] ?? 'outline'} className="capitalize">
                                            {submission.status}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="mt-1 text-sm text-neutral-500">submitted {new Date(submission.created_at).toLocaleDateString()}</div>
                                {submission.status === 'rejected' && submission.review_note && (
                                    <p className="mt-2 rounded-lg bg-neutral-100 p-3 text-sm text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                        {submission.review_note}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
