import { Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';

export interface DocsNavPage {
    key: string;
    title: string;
    url: string;
    active: boolean;
}

export interface DocsNavSection {
    key: string;
    title: string;
    url: string;
    active: boolean;
    pages: DocsNavPage[];
}

/**
 * Docs left rail (SPEC §13.2): the search box on top of the nav tree. The
 * box submits an Inertia GET to /docs/search so results stay SPA-fast and
 * deep-linkable; the input remounts per query (key) to reflect the URL.
 */
export default function DocsSidebar({ nav, query = '' }: { nav: DocsNavSection[]; query?: string }) {
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const q = new FormData(event.currentTarget).get('q');

        router.get('/docs/search', { q: typeof q === 'string' ? q : '' });
    };

    return (
        <nav className="sticky top-24 space-y-8" aria-label="Documentation">
            <form onSubmit={submit} role="search">
                <label htmlFor="docs-sidebar-search" className="sr-only">
                    Search the docs
                </label>
                <input
                    key={query}
                    id="docs-sidebar-search"
                    type="search"
                    name="q"
                    defaultValue={query}
                    placeholder="Search the docs…"
                    className="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm text-neutral-900 transition placeholder:text-neutral-400 focus:border-neutral-400 focus:outline-none"
                />
            </form>

            {nav.map((section) => (
                <div key={section.key}>
                    <Link
                        href={section.url}
                        className={`text-sm font-semibold transition ${
                            section.active ? 'text-neutral-900' : 'text-neutral-500 hover:text-neutral-900'
                        }`}
                    >
                        {section.title}
                    </Link>
                    <ul className="mt-3 space-y-1 border-l border-neutral-200">
                        {section.pages.map((page) => (
                            <li key={page.key}>
                                <Link
                                    href={page.url}
                                    aria-current={page.active ? 'page' : undefined}
                                    className={`-ml-px block border-l-2 py-1 pl-4 text-sm transition ${
                                        page.active
                                            ? 'border-neutral-900 font-medium text-neutral-900'
                                            : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-900'
                                    }`}
                                >
                                    {page.title}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </nav>
    );
}
