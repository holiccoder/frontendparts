import DocsSidebar, { type DocsNavSection } from '@/components/docs/docs-sidebar';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { PageMeta } from '@/types/shared';
import { Link } from '@inertiajs/react';

interface DocsSearchResult {
    section: string;
    page: string;
    title: string;
    url: string;
    snippet: string;
}

interface DocsSearchProps {
    query: string;
    results: DocsSearchResult[];
    nav: DocsNavSection[];
    meta: PageMeta;
}

/**
 * Docs search results (SPEC §13.2 — basic search at launch): SSR page fed
 * by DocsRepository::search(). Title matches rank above body matches; the
 * empty state points back at the main sections.
 */
export default function DocsSearch({ query, results, nav, meta }: DocsSearchProps) {
    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex gap-10 py-10 lg:py-14">
                    <aside className="hidden w-56 shrink-0 lg:block">
                        <DocsSidebar nav={nav} query={query} />
                    </aside>

                    <div className="min-w-0 flex-1">
                        <nav className="text-sm text-neutral-400" aria-label="Breadcrumb">
                            <Link href="/docs" className="transition hover:text-neutral-900">
                                Docs
                            </Link>
                            <span className="mx-2">/</span>
                            <span className="text-neutral-600">Search</span>
                        </nav>

                        <h1 className="mt-6 text-3xl font-bold tracking-tight text-neutral-900">
                            {query === '' ? 'Search the docs' : `Results for “${query}”`}
                        </h1>
                        <p className="mt-2 text-sm text-neutral-500">
                            {query === ''
                                ? 'Search across every guide — install, params & data, exports, license and troubleshooting.'
                                : `${results.length} ${results.length === 1 ? 'page' : 'pages'} matched.`}
                        </p>

                        {query !== '' && results.length === 0 && (
                            <div className="mt-10 rounded-xl border border-neutral-200 px-6 py-10 text-center">
                                <p className="text-sm font-medium text-neutral-900">Nothing in the docs matches “{query}”.</p>
                                <p className="mt-2 text-sm text-neutral-500">
                                    Try fewer or different words — or start from{' '}
                                    <Link href="/docs/getting-started/index" className="font-medium text-neutral-900 underline">
                                        Getting started
                                    </Link>{' '}
                                    or{' '}
                                    <Link href="/docs/troubleshooting/index" className="font-medium text-neutral-900 underline">
                                        Troubleshooting
                                    </Link>
                                    .
                                </p>
                            </div>
                        )}

                        {results.length > 0 && (
                            <ul className="mt-8 space-y-4">
                                {results.map((result) => (
                                    <li key={`${result.section}/${result.page}`}>
                                        <Link
                                            href={result.url}
                                            className="block rounded-xl border border-neutral-200 px-5 py-4 transition hover:border-neutral-400"
                                        >
                                            <span className="text-sm font-semibold text-neutral-900">{result.title}</span>
                                            {result.snippet !== '' && <span className="mt-1 block text-sm text-neutral-500">{result.snippet}</span>}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <aside className="hidden w-52 shrink-0 xl:block" />
                </div>
            </div>
        </PublicLayout>
    );
}
