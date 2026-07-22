import PublicLayout from '@/layouts/public-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

interface ErrorPageProps {
    status: number;
}

/**
 * Branded 404: rendered by the exception handler in bootstrap/app.php and
 * always links back home.
 */
export default function ErrorPage({ status }: ErrorPageProps) {
    return (
        <PublicLayout>
            <Head title={`${status} — Page not found`} />

            <div className="mx-auto flex max-w-7xl flex-col items-center px-4 py-32 text-center sm:px-6 lg:px-8">
                <span className="text-xs font-semibold tracking-[0.2em] text-neutral-400 uppercase">Error {status}</span>
                <h1 className="mt-4 text-5xl font-semibold tracking-tight sm:text-6xl">Page not found</h1>
                <p className="mt-5 max-w-md leading-7 text-neutral-500">
                    The page you're looking for moved, never existed, or was mistyped. Home is a good place to start again.
                </p>
                <div className="mt-10 flex items-center gap-3">
                    <Link
                        href="/"
                        className="inline-flex items-center gap-2 rounded-md bg-neutral-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-neutral-700"
                    >
                        Back home <ArrowRight className="h-4 w-4" />
                    </Link>
                    <Link
                        href="/pricing"
                        className="inline-flex items-center rounded-md border border-neutral-300 px-6 py-3 text-sm font-semibold text-neutral-700 transition hover:border-neutral-400 hover:bg-neutral-50"
                    >
                        View pricing
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}
