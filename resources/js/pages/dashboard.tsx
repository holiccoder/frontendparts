import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Download, FolderKanban } from 'lucide-react';

import { ComponentGrid } from '@/components/catalog/component-card';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { licenseStateBadgeVariant, licenseStateLabel } from '@/lib/license';
import { type BreadcrumbItem } from '@/types';
import type { ComponentCardData } from '@/types/catalog';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface PlanProps {
    name: string;
    is_paid: boolean;
    has_full_library: boolean;
    can_scaffold: boolean;
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

interface ProjectsProps {
    items: {
        id: number;
        name: string;
        components_count: number;
        url: string;
    }[];
    total: number;
    limit: number | null;
    index_url: string;
}

interface RecentDownload {
    id: number;
    downloaded_at: string;
    component: {
        name: string;
        url: string;
    };
}

interface DashboardProps {
    plan: PlanProps;
    projects: ProjectsProps;
    recentDownloads: RecentDownload[];
    newDrops: ComponentCardData[];
}

function planDescription(plan: PlanProps): string {
    if (!plan.is_paid) {
        return "You're on the free plan — upgrade to unlock every component in the catalog.";
    }

    return plan.can_scaffold ? 'Full library access, plus Next.js & Nuxt scaffolding.' : 'Full library access — every section, block and page.';
}

/**
 * Dashboard overview (SPEC §15.4, CSR): plan status with the effective plan's
 * CTA, projects with plan-limit usage, recent downloads and new drops.
 */
export default function Dashboard({ plan, projects, recentDownloads, newDrops }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <section className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-3 rounded-xl border p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="flex flex-col gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h2 className="text-lg font-semibold capitalize">{plan.name} plan</h2>
                                {plan.license && (
                                    <Badge variant={licenseStateBadgeVariant(plan.license.state)}>{licenseStateLabel(plan.license.state)}</Badge>
                                )}
                            </div>
                            <p className="text-sm text-neutral-500">{planDescription(plan)}</p>
                            {plan.license?.ends_at && (
                                <p className="text-sm text-neutral-500">
                                    {plan.license.state === 'cancelled_valid_until' ? 'Access until ' : 'Renews '}
                                    {new Date(plan.license.ends_at).toLocaleDateString()}
                                </p>
                            )}
                        </div>
                        <Button asChild>
                            <Link href={plan.cta.url}>{plan.cta.label}</Link>
                        </Button>
                    </div>
                    <ul className="flex flex-wrap gap-x-6 gap-y-1 text-sm text-neutral-500">
                        <li>{plan.has_full_library ? 'Full library access' : 'Free subset of the catalog'}</li>
                        <li>{plan.can_scaffold ? 'Next.js & Nuxt scaffolding' : 'Pack zip export'}</li>
                    </ul>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="flex flex-col gap-3">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <HeadingSmall title="Projects" description="Component sets you can export as a pack zip." />
                            <Badge variant="secondary">
                                {projects.limit === null ? `${projects.total} projects` : `${projects.total} of ${projects.limit} projects`}
                            </Badge>
                        </div>

                        {projects.items.length === 0 ? (
                            <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center">
                                <FolderKanban className="size-8 text-neutral-400" />
                                <p className="text-sm text-neutral-500">No projects yet — create one to start collecting components.</p>
                            </div>
                        ) : (
                            <ul className="grid gap-3 sm:grid-cols-2">
                                {projects.items.map((project) => (
                                    <li key={project.id}>
                                        <Link
                                            href={project.url}
                                            className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col gap-1 rounded-xl border p-4 transition-colors hover:border-neutral-400 dark:hover:border-neutral-500"
                                        >
                                            <span className="font-medium">{project.name}</span>
                                            <span className="text-sm text-neutral-500">
                                                {project.components_count} {project.components_count === 1 ? 'component' : 'components'}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <Link
                            href={projects.index_url}
                            className="inline-flex w-fit items-center gap-1 text-sm font-medium text-neutral-700 hover:underline dark:text-neutral-300"
                        >
                            View all projects
                            <ArrowRight className="size-4" />
                        </Link>
                    </section>

                    <section className="flex flex-col gap-3">
                        <HeadingSmall title="Recent downloads" description="Your latest component downloads." />

                        {recentDownloads.length === 0 ? (
                            <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col items-center gap-2 rounded-xl border border-dashed p-8 text-center">
                                <Download className="size-8 text-neutral-400" />
                                <p className="text-sm text-neutral-500">
                                    Nothing downloaded yet — browse the{' '}
                                    <Link href="/components" className="underline hover:text-neutral-900 dark:hover:text-neutral-100">
                                        catalog
                                    </Link>
                                    .
                                </p>
                            </div>
                        ) : (
                            <ul className="border-sidebar-border/70 dark:border-sidebar-border divide-y divide-neutral-200 rounded-xl border dark:divide-neutral-800">
                                {recentDownloads.map((download) => (
                                    <li key={download.id} className="flex items-center justify-between gap-4 px-4 py-3">
                                        <Link href={download.component.url} className="truncate font-medium hover:underline">
                                            {download.component.name}
                                        </Link>
                                        <span className="shrink-0 text-xs text-neutral-500">
                                            {new Date(download.downloaded_at).toLocaleDateString()}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                <section className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <HeadingSmall title="New drops" description="Freshly published components from the catalog." />
                        <Link href="/components" className="inline-flex items-center gap-1 text-sm font-medium hover:underline">
                            Browse catalog
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>

                    {newDrops.length === 0 ? (
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border border-dashed p-8 text-center text-sm text-neutral-500">
                            New components land here as they're published.
                        </div>
                    ) : (
                        <ComponentGrid components={newDrops} />
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
