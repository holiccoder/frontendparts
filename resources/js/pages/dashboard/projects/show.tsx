import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, Trash2, X } from 'lucide-react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    };
}

/**
 * Project detail (SPEC §15.4, CSR): the component set with the dependency
 * view — direct picks are removable, auto-added closure members are marked
 * and follow the removal cascade (SPEC §6.1) — plus the pack-zip export
 * action (stub until 2.5).
 */
export default function ProjectShow({ project, components, export: exportAction }: ProjectShowProps) {
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
        router.post(exportAction.url, {}, { preserveScroll: true });
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
                        <Button variant="outline" onClick={exportProject}>
                            <Download className="size-4" />
                            Export zip
                        </Button>
                        <Button variant="destructive" onClick={destroyProject}>
                            <Trash2 className="size-4" />
                            Delete
                        </Button>
                    </div>
                </div>

                {!exportAction.available && (
                    <p className="rounded-xl border border-dashed border-neutral-300 px-4 py-3 text-sm text-neutral-500 dark:border-neutral-700">
                        Pack zip export is coming soon — the button above is already wired and will build your full component closure.
                    </p>
                )}
                <InputError message={pageErrors.export} />

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
            </div>
        </AppLayout>
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
