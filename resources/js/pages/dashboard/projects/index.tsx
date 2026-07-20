import { Head, Link, useForm } from '@inertiajs/react';
import { FolderKanban, Plus } from 'lucide-react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Projects',
        href: '/dashboard/projects',
    },
];

interface ProjectSummary {
    id: number;
    name: string;
    components_count: number;
    created_at: string;
    url: string;
}

interface ProjectsIndexProps {
    projects: ProjectSummary[];
    limits: {
        plan: string;
        limit: number | null;
        used: number;
    };
}

/**
 * Project list (SPEC §15.4, CSR): create projects up to the settings-driven
 * plan limit (SPEC §6.1, §7.1) and jump into each component set.
 */
export default function ProjectsIndex({ projects, limits }: ProjectsIndexProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
    });

    const atLimit = limits.limit !== null && limits.used >= limits.limit;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('dashboard.projects.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <HeadingSmall
                        title="Projects"
                        description="Component sets you can export as a pack zip — dependencies are added automatically."
                    />
                    <Badge variant="secondary" className="capitalize">
                        {limits.plan} plan ·{' '}
                        {limits.limit === null ? `${limits.used} projects · unlimited` : `${limits.used} of ${limits.limit} projects used`}
                    </Badge>
                </div>

                <form onSubmit={submit} className="flex max-w-md items-start gap-2">
                    <div className="grid flex-1 gap-1">
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder={atLimit ? 'Project limit reached — upgrade to add more' : 'New project name'}
                            disabled={atLimit}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>
                    <Button type="submit" disabled={processing || atLimit}>
                        <Plus className="size-4" />
                        Create
                    </Button>
                </form>

                {projects.length === 0 ? (
                    <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col items-center gap-2 rounded-xl border border-dashed p-12 text-center">
                        <FolderKanban className="size-8 text-neutral-400" />
                        <p className="text-sm text-neutral-500">No projects yet — create one to start collecting components.</p>
                    </div>
                ) : (
                    <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
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
            </div>
        </AppLayout>
    );
}
