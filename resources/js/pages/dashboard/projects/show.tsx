import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, ExternalLink, Loader2, Rocket, Trash2, X } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';

interface ProjectComponent {
    id: number;
    slug: string;
    basename: string;
    name: string;
    level: string;
    access_level: string;
    is_dependency: boolean;
    url: string;
}

interface ProjectExport {
    id: number;
    status: 'pending' | 'ready' | 'failed';
    framework: string;
    download_url: string | null;
}

interface ComponentFork {
    id: number;
    name: string;
    slug: string;
    url: string;
    framework: string;
    status: 'pending' | 'building' | 'ready' | 'failed';
    error: string | null;
    preview_url: string | null;
    created_at: string;
}

interface ProjectShowProps {
    project: {
        id: number;
        name: string;
        created_at: string;
    };
    components: ProjectComponent[];
    export: {
        url: string;
        available: boolean;
        latest: ProjectExport | null;
    };
    scaffold: {
        url: string;
        available: boolean;
        latest: ProjectExport | null;
    };
    forks: ComponentFork[];
}

/**
 * Project detail (SPEC §15.4, CSR): the component set with the dependency
 * view — direct picks are removable, auto-added closure members are marked
 * and follow the removal cascade (SPEC §6.1) — plus the pack-zip export
 * (SPEC §6.2) and the Pro-only Next.js / Nuxt starter scaffold (SPEC §6.3): POST
 * queues the build, and this page polls the `export` / `scaffold` props
 * until the zip is ready to download. Live-edit forks (SPEC §5.6) list
 * below with their rebuild progress — the page polls the `forks` prop while
 * any fork is pending/building, then links the rebuilt preview.
 */
export default function ProjectShow({ project, components, export: exportAction, scaffold: scaffoldAction, forks }: ProjectShowProps) {
    const { flash, errors: pageErrors } = usePage<SharedData & { flash?: { notice?: string | null } }>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Projects',
            href: '/dashboard/projects',
        },
        {
            title: project.name,
            href: `/dashboard/projects/${project.id}`,
        },
    ];

    const renameForm = useForm({ name: project.name });

    const [framework, setFramework] = useState<'react' | 'vue'>('react');

    const building = exportAction.latest?.status === 'pending';
    const scaffolding = scaffoldAction.latest?.status === 'pending';
    const forksBuilding = forks.some((fork) => fork.status === 'pending' || fork.status === 'building');

    useEffect(() => {
        if (!building) {
            return;
        }

        const timer = setInterval(() => router.reload({ only: ['export'] }), 2500);

        return () => clearInterval(timer);
    }, [building]);

    useEffect(() => {
        if (!scaffolding) {
            return;
        }

        const timer = setInterval(() => router.reload({ only: ['scaffold'] }), 2500);

        return () => clearInterval(timer);
    }, [scaffolding]);

    useEffect(() => {
        if (!forksBuilding) {
            return;
        }

        const timer = setInterval(() => router.reload({ only: ['forks'] }), 2500);

        return () => clearInterval(timer);
    }, [forksBuilding]);

    const submitRename: FormEventHandler = (e) => {
        e.preventDefault();

        renameForm.patch(route('dashboard.projects.update', project.id), {
            preserveScroll: true,
        });
    };

    const destroyProject = () => {
        if (window.confirm(`Delete "${project.name}"? This cannot be undone.`)) {
            router.delete(route('dashboard.projects.destroy', project.id));
        }
    };

    const removeComponent = (component: ProjectComponent) => {
        router.delete(
            route('dashboard.projects.components.destroy', {
                project: project.id,
                component: component.id,
            }),
            { preserveScroll: true },
        );
    };

    const exportProject = () => {
        router.post(exportAction.url, { framework }, { preserveScroll: true });
    };

    const scaffoldProject = (starter: 'next' | 'nuxt') => {
        router.post(scaffoldAction.url, { framework: starter }, { preserveScroll: true });
    };

    const direct = components.filter((component) => !component.is_dependency);
    const dependencies = components.filter((component) => component.is_dependency);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <HeadingSmall title={project.name} description="Your picks and the dependencies they pull in." />
                    <div className="flex items-center gap-2">
                        <Select value={framework} onValueChange={(value) => setFramework(value as 'react' | 'vue')}>
                            <SelectTrigger className="w-28" aria-label="Export framework">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="react">React</SelectItem>
                                <SelectItem value="vue">Vue</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button variant="outline" onClick={exportProject} disabled={building}>
                            {building ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />}
                            Export zip
                        </Button>
                        {scaffoldAction.available ? (
                            <>
                                <Button variant="outline" onClick={() => scaffoldProject('next')} disabled={scaffolding}>
                                    {scaffolding ? <Loader2 className="size-4 animate-spin" /> : <Rocket className="size-4" />}
                                    Scaffold Next.js
                                </Button>
                                <Button variant="outline" onClick={() => scaffoldProject('nuxt')} disabled={scaffolding}>
                                    {scaffolding ? <Loader2 className="size-4 animate-spin" /> : <Rocket className="size-4" />}
                                    Scaffold Nuxt
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href="/pricing">
                                        <Rocket className="size-4" />
                                        Scaffold Next.js
                                        <Badge>Pro</Badge>
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/pricing">
                                        <Rocket className="size-4" />
                                        Scaffold Nuxt
                                        <Badge>Pro</Badge>
                                    </Link>
                                </Button>
                            </>
                        )}
                        <Button variant="destructive" onClick={destroyProject}>
                            <Trash2 className="size-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                {building && (
                    <p className="rounded-xl border border-dashed border-neutral-300 px-4 py-3 text-sm text-neutral-500 dark:border-neutral-700">
                        Building your zip — components, sample data, dependencies and setup notes. The download link appears here when it's ready.
                    </p>
                )}

                {exportAction.latest?.status === 'ready' && exportAction.latest.download_url && (
                    <p className="flex flex-wrap items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        Your {exportAction.latest.framework} pack is ready.
                        <Button size="sm" asChild>
                            <a href={exportAction.latest.download_url}>
                                <Download className="size-4" />
                                Download zip
                            </a>
                        </Button>
                    </p>
                )}

                {exportAction.latest?.status === 'failed' && (
                    <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        The export build failed — please try again.
                    </p>
                )}

                {scaffolding && (
                    <p className="rounded-xl border border-dashed border-neutral-300 px-4 py-3 text-sm text-neutral-500 dark:border-neutral-700">
                        Building your {scaffoldAction.latest?.framework === 'nuxt' ? 'Nuxt' : 'Next.js'} starter — routes, index page, configs and
                        merged dependencies. The download link appears here when it's ready.
                    </p>
                )}

                {scaffoldAction.latest?.status === 'ready' && scaffoldAction.latest.download_url && (
                    <p className="flex flex-wrap items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        Your {scaffoldAction.latest.framework === 'nuxt' ? 'Nuxt' : 'Next.js'} starter is ready.
                        <Button size="sm" asChild>
                            <a href={scaffoldAction.latest.download_url}>
                                <Download className="size-4" />
                                Download starter
                            </a>
                        </Button>
                    </p>
                )}

                {scaffoldAction.latest?.status === 'failed' && (
                    <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        The scaffold build failed — please try again.
                    </p>
                )}

                <InputError message={pageErrors.export} />
                <InputError message={pageErrors.scaffold} />

                {flash?.notice && (
                    <p className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                        {flash.notice}
                    </p>
                )}

                <form onSubmit={submitRename} className="flex max-w-md items-start gap-2">
                    <div className="grid flex-1 gap-1">
                        <Input value={renameForm.data.name} onChange={(e) => renameForm.setData('name', e.target.value)} required />
                        <InputError message={renameForm.errors.name} />
                    </div>
                    <Button type="submit" variant="secondary" disabled={renameForm.processing}>
                        Rename
                    </Button>
                </form>

                <section className="grid gap-3">
                    <h2 className="text-sm font-semibold tracking-wide text-neutral-500 uppercase">Direct picks · {direct.length}</h2>
                    {direct.length === 0 ? (
                        <div className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border border-dashed p-8 text-center text-sm text-neutral-500">
                            Nothing here yet — add components from the{' '}
                            <Link href="/components" className="underline hover:text-neutral-900 dark:hover:text-neutral-100">
                                catalog
                            </Link>
                            .
                        </div>
                    ) : (
                        <ul className="border-sidebar-border/70 dark:border-sidebar-border divide-y divide-neutral-200 rounded-xl border dark:divide-neutral-800">
                            {direct.map((component) => (
                                <ComponentRow key={component.id} component={component} onRemove={() => removeComponent(component)} />
                            ))}
                        </ul>
                    )}
                </section>

                {dependencies.length > 0 && (
                    <section className="grid gap-3">
                        <h2 className="text-sm font-semibold tracking-wide text-neutral-500 uppercase">Dependencies · {dependencies.length}</h2>
                        <ul className="border-sidebar-border/70 dark:border-sidebar-border divide-y divide-neutral-200 rounded-xl border dark:divide-neutral-800">
                            {dependencies.map((component) => (
                                <ComponentRow key={component.id} component={component} />
                            ))}
                        </ul>
                        <p className="text-xs text-neutral-500">
                            Dependencies were auto-added with your picks and are pruned when no remaining pick needs them.
                        </p>
                    </section>
                )}

                {forks.length > 0 && (
                    <section className="grid gap-3">
                        <h2 className="text-sm font-semibold tracking-wide text-neutral-500 uppercase">Customized forks · {forks.length}</h2>
                        <ul className="border-sidebar-border/70 dark:border-sidebar-border divide-y divide-neutral-200 rounded-xl border dark:divide-neutral-800">
                            {forks.map((fork) => (
                                <ForkRow key={fork.id} fork={fork} />
                            ))}
                        </ul>
                        <p className="text-xs text-neutral-500">
                            Forks are your live-edit customizations — the original library component is never modified. Each fork's preview rebuilds
                            in the background when it's saved.
                        </p>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

function ForkRow({ fork }: { fork: ComponentFork }) {
    return (
        <li className="flex items-center justify-between gap-4 px-4 py-3">
            <div className="flex min-w-0 flex-col">
                <Link href={fork.url} className="truncate font-medium hover:underline">
                    {fork.name}
                </Link>
                <span className="truncate text-xs text-neutral-500">{fork.slug}</span>
                {fork.status === 'failed' && fork.error && <span className="mt-1 line-clamp-2 text-xs text-red-600">{fork.error}</span>}
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <Badge variant="secondary" className="capitalize">
                    {fork.framework}
                </Badge>
                {fork.status === 'pending' && <Badge variant="outline">Rebuild queued</Badge>}
                {fork.status === 'building' && (
                    <Badge variant="outline" className="gap-1">
                        <Loader2 className="size-3 animate-spin" />
                        Rebuilding preview…
                    </Badge>
                )}
                {fork.status === 'failed' && <Badge variant="destructive">Rebuild failed</Badge>}
                {fork.status === 'ready' && fork.preview_url && (
                    <Button size="sm" variant="outline" asChild>
                        <a href={fork.preview_url} target="_blank" rel="noopener noreferrer">
                            <ExternalLink className="size-4" />
                            View fork preview
                        </a>
                    </Button>
                )}
            </div>
        </li>
    );
}

function ComponentRow({ component, onRemove }: { component: ProjectComponent; onRemove?: () => void }) {
    return (
        <li className="flex items-center justify-between gap-4 px-4 py-3">
            <div className="flex min-w-0 flex-col">
                <Link href={component.url} className="truncate font-medium hover:underline">
                    {component.name}
                </Link>
                <span className="truncate text-xs text-neutral-500">{component.slug}</span>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <Badge variant="secondary" className="capitalize">
                    {component.level}
                </Badge>
                {component.access_level === 'paid' && <Badge>Paid</Badge>}
                {component.is_dependency ? (
                    <Badge variant="outline">Dependency</Badge>
                ) : (
                    onRemove && (
                        <Button variant="ghost" size="sm" onClick={onRemove} aria-label={`Remove ${component.name}`}>
                            <X className="size-4" />
                        </Button>
                    )
                )}
            </div>
        </li>
    );
}
