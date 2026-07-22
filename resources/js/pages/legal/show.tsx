import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { SharedData } from '@/types';
import type { PageMeta } from '@/types/shared';
import { Link, usePage } from '@inertiajs/react';

interface TocEntry {
    level: number;
    id: string;
    text: string;
}

interface LegalPagePayload {
    slug: string;
    title: string;
    description: string;
    updated: string | null;
    url: string;
    html: string;
    toc: TocEntry[];
}

interface LegalShowProps {
    page: LegalPagePayload;
    meta: PageMeta;
}

/**
 * Legal pages (SPEC §15.7): one renderer for all seven pages, fed by
 * markdown from resources/legal/ via LegalPages. Rendered markdown sits in
 * the center column (same docs-prose styling as /docs), with an h2/h3 TOC
 * rail on the right and cross-links to the other legal pages at the
 * bottom. All seven are SSR and indexed.
 */
export default function LegalShow({ page, meta }: LegalShowProps) {
    const { legalNav } = usePage<SharedData>().props;

    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex gap-10 py-10 lg:py-14">
                    <div className="max-w-3xl min-w-0 flex-1">
                        <nav className="text-sm text-neutral-400" aria-label="Breadcrumb">
                            <span className="text-neutral-600">Legal</span>
                            <span className="mx-2">/</span>
                            <span className="text-neutral-900">{page.title}</span>
                        </nav>

                        {page.updated && (
                            <p className="mt-6 text-xs font-medium tracking-wide text-neutral-400 uppercase">Last updated: {page.updated}</p>
                        )}

                        <article className="docs-prose mt-4" dangerouslySetInnerHTML={{ __html: page.html }} />

                        <nav className="mt-14 border-t border-neutral-200 pt-6" aria-label="More legal pages">
                            <p className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">More legal pages</p>
                            <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-2">
                                {legalNav
                                    .filter((link) => link.url !== page.url)
                                    .map((link) => (
                                        <li key={link.url}>
                                            <Link href={link.url} className="text-sm font-medium text-neutral-500 transition hover:text-neutral-900">
                                                {link.title}
                                            </Link>
                                        </li>
                                    ))}
                            </ul>
                        </nav>
                    </div>

                    <aside className="hidden w-52 shrink-0 xl:block">
                        {page.toc.length > 0 && (
                            <nav className="sticky top-24" aria-label="On this page">
                                <p className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">On this page</p>
                                <ul className="mt-3 space-y-2 border-l border-neutral-200">
                                    {page.toc.map((entry) => (
                                        <li key={entry.id}>
                                            <a
                                                href={`#${entry.id}`}
                                                className={`-ml-px block border-l-2 border-transparent py-0.5 text-[13px] text-neutral-500 transition hover:border-neutral-300 hover:text-neutral-900 ${
                                                    entry.level === 3 ? 'pl-7' : 'pl-4'
                                                }`}
                                            >
                                                {entry.text}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </nav>
                        )}
                    </aside>
                </div>
            </div>
        </PublicLayout>
    );
}
