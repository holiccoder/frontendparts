import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

const NAV_LINKS = [
    { label: 'Components', href: '/components' },
    { label: 'Industries', href: '/industries' },
    { label: 'Pricing', href: '#' },
    { label: 'Docs', href: '#' },
    { label: 'Blog', href: '#' },
];

const FOOTER_COLUMNS = [
    {
        title: 'Catalog',
        links: [
            { label: 'All components', href: '/components' },
            { label: 'Industries', href: '/industries' },
        ],
    },
    {
        title: 'Resources',
        links: [
            { label: 'Documentation', href: '#' },
            { label: 'Blog', href: '#' },
            { label: 'Pricing', href: '#' },
        ],
    },
    {
        title: 'Legal',
        links: [
            { label: 'Terms', href: '#' },
            { label: 'Privacy', href: '#' },
            { label: 'License', href: '#' },
            { label: 'Refund policy', href: '#' },
        ],
    },
];

/**
 * Marketing shell for the public SSR zone: light, generous whitespace,
 * explicit neutral palette so the theme toggle never affects it.
 */
export default function PublicLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<SharedData>().props;

    return (
        <div className="flex min-h-screen flex-col bg-white text-neutral-900 antialiased">
            <header className="sticky top-0 z-40 border-b border-neutral-200/70 bg-white/85 backdrop-blur">
                <div className="mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
                    <Link href="/" className="flex items-center gap-2.5" aria-label="FrontendParts home">
                        <img src="/brand/logo.png" alt="" className="h-8 w-8 rounded-md object-cover" />
                        <span className="text-[17px] font-semibold tracking-tight">FrontendParts</span>
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex" aria-label="Primary">
                        {NAV_LINKS.map((link) => (
                            <Link
                                key={link.label}
                                href={link.href}
                                className="rounded-md px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900"
                            >
                                {link.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="flex items-center gap-2">
                        {auth.user ? (
                            <Link
                                href="/dashboard"
                                className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-700"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href="/login"
                                    className="rounded-md px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href="/register"
                                    className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-700"
                                >
                                    Get started
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-neutral-200 bg-neutral-50">
                <div className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                    <div className="grid gap-10 md:grid-cols-[1.4fr_1fr_1fr_1fr]">
                        <div>
                            <div className="flex items-center gap-2.5">
                                <img src="/brand/logo.png" alt="" className="h-8 w-8 rounded-md object-cover" />
                                <span className="text-[17px] font-semibold tracking-tight">FrontendParts</span>
                            </div>
                            <p className="mt-4 max-w-xs text-sm leading-6 text-neutral-500">
                                Production-ready website sections for React and Vue, recreated from the best sites on the web.
                            </p>
                        </div>

                        {FOOTER_COLUMNS.map((column) => (
                            <div key={column.title}>
                                <h3 className="text-sm font-semibold">{column.title}</h3>
                                <ul className="mt-4 space-y-2.5">
                                    {column.links.map((link) => (
                                        <li key={link.label}>
                                            <Link href={link.href} className="text-sm text-neutral-500 transition hover:text-neutral-900">
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>

                    <div className="mt-12 border-t border-neutral-200 pt-6 text-sm text-neutral-400">
                        © {new Date().getFullYear()} FrontendParts. All rights reserved.
                    </div>
                </div>
            </footer>
        </div>
    );
}
