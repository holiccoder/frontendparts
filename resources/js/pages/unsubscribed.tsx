import PublicLayout from '@/layouts/public-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

interface UnsubscribedProps {
    email: string;
}

/**
 * Confirmation shown after the signed one-click unsubscribe link
 * (SPEC §16.3) opts the address out of all marketing email.
 */
export default function Unsubscribed({ email }: UnsubscribedProps) {
    return (
        <PublicLayout>
            <Head title="Unsubscribed" />

            <div className="mx-auto flex max-w-7xl flex-col items-center px-4 py-32 text-center sm:px-6 lg:px-8">
                <span className="text-xs font-semibold tracking-[0.2em] text-neutral-400 uppercase">Preferences saved</span>
                <h1 className="mt-4 text-5xl font-semibold tracking-tight sm:text-6xl">You're unsubscribed</h1>
                <p className="mt-5 max-w-md leading-7 text-neutral-500">
                    {email} will no longer receive marketing emails — no digest, blog highlights, or product updates. You'll still get essential
                    transactional email (orders, license, security, support tickets).
                </p>
                <div className="mt-10 flex items-center gap-3">
                    <Link
                        href="/settings/notifications"
                        className="inline-flex items-center gap-2 rounded-md bg-neutral-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-neutral-700"
                    >
                        Manage preferences <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}
