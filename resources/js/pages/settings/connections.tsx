import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Github } from 'lucide-react';

import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type SharedData } from '@/types';

interface GithubConnectionState {
    connected: boolean;
    login: string | null;
    connected_at: string | null;
    urls: {
        connect: string;
        disconnect: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Connections',
        href: '/settings/connections',
    },
];

export default function Connections({ github }: { github: GithubConnectionState }) {
    const { flash } = usePage<SharedData & { flash?: { notice?: string | null } }>().props;

    const disconnect = () => {
        if (window.confirm(`Disconnect GitHub "${github.login}"? Repository exports will stop working until you reconnect.`)) {
            router.delete(github.urls.disconnect, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Connections" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Connected accounts" description="Link external accounts to your profile" />

                    {flash?.notice && (
                        <p className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                            {flash.notice}
                        </p>
                    )}

                    <div className="flex items-center justify-between gap-4 rounded-md border border-neutral-200 p-4 dark:border-neutral-800">
                        <div className="flex items-center gap-3">
                            <Github className="size-6" />
                            <div className="flex flex-col">
                                <span className="font-medium">GitHub</span>
                                <span className="text-sm text-neutral-500">
                                    {github.connected
                                        ? `Connected as ${github.login} — project exports can be pushed to your repositories.`
                                        : 'Connect to export project starters straight into a new repository.'}
                                </span>
                            </div>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            {github.connected ? (
                                <>
                                    <Badge variant="secondary">Connected</Badge>
                                    <Button variant="destructive" size="sm" onClick={disconnect}>
                                        Disconnect
                                    </Button>
                                </>
                            ) : (
                                <Button size="sm" asChild>
                                    <a href={github.urls.connect}>
                                        <Github className="size-4" />
                                        Connect GitHub
                                    </a>
                                </Button>
                            )}
                        </div>
                    </div>

                    <p className="text-sm text-neutral-500">
                        Connecting grants the <code>repo</code> scope so exports can create a repository and push your starter in a single commit. You
                        can disconnect at any time — this clears the stored access token immediately. Repository exports are a{' '}
                        <Link href="/pricing" className="underline hover:text-neutral-900 dark:hover:text-neutral-100">
                            Pro
                        </Link>{' '}
                        feature.
                    </p>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
