import DocsSidebar, { type DocsNavSection } from '@/components/docs/docs-sidebar';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { PageMeta } from '@/types/shared';
import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface TocEntry {
    level: number;
    id: string;
    text: string;
}

interface DocsLink {
    title: string;
    url: string;
}

interface DocPayload {
    section: string;
    page: string;
    title: string;
    description: string;
    html: string;
    toc: TocEntry[];
}

interface DocsShowProps {
    doc: DocPayload;
    nav: DocsNavSection[];
    pagination: {
        prev: DocsLink | null;
        next: DocsLink | null;
    };
    meta: PageMeta;
}

/**
 * Docs page (SPEC §13.2): left sidebar nav tree, rendered markdown in the
 * center, per-page h2/h3 TOC on the right, prev/next footer. The markdown
 * HTML is server-rendered; copy buttons and the TOC scrollspy are client
 * enhancements layered on top.
 */
export default function DocsShow({ doc, nav, pagination, meta }: DocsShowProps) {
    const articleRef = useRef<HTMLElement>(null);
    const [activeHeading, setActiveHeading] = useState<string | null>(null);

    const activeSection = nav.find((section) => section.active);

    // Client enhancement: a copy button on every fenced code block.
    useEffect(() => {
        const root = articleRef.current;

        if (!root) {
            return;
        }

        const buttons: HTMLButtonElement[] = [];

        root.querySelectorAll('pre').forEach((pre) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'docs-copy-btn';
            button.textContent = 'Copy';

            button.addEventListener('click', () => {
                const code = pre.querySelector('code')?.innerText ?? pre.innerText;

                navigator.clipboard.writeText(code).then(() => {
                    button.textContent = 'Copied';
                    window.setTimeout(() => {
                        button.textContent = 'Copy';
                    }, 1500);
                });
            });

            pre.appendChild(button);
            buttons.push(button);
        });

        return () => {
            buttons.forEach((button) => button.remove());
        };
    }, [doc.html]);

    // Client enhancement: scrollspy for the right-hand TOC.
    useEffect(() => {
        const root = articleRef.current;

        if (!root || doc.toc.length === 0) {
            return;
        }

        const headings = doc.toc
            .map((entry) => root.querySelector(`#${CSS.escape(entry.id)}`))
            .filter((element): element is Element => element !== null);

        if (headings.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setActiveHeading(entry.target.id);
                    }
                });
            },
            { rootMargin: '-90px 0px -70% 0px' },
        );

        headings.forEach((heading) => observer.observe(heading));

        return () => observer.disconnect();
    }, [doc.html, doc.toc]);

    const scrollToHeading = (id: string) => {
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        window.history.replaceState(null, '', `#${id}`);
        setActiveHeading(id);
    };

    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex gap-10 py-10 lg:py-14">
                    <aside className="hidden w-56 shrink-0 lg:block">
                        <DocsSidebar nav={nav} />
                    </aside>

                    <div className="min-w-0 flex-1">
                        <nav className="text-sm text-neutral-400" aria-label="Breadcrumb">
                            <Link href="/docs" className="transition hover:text-neutral-900">
                                Docs
                            </Link>
                            {activeSection && (
                                <>
                                    <span className="mx-2">/</span>
                                    <span className="text-neutral-600">{activeSection.title}</span>
                                </>
                            )}
                        </nav>

                        <article ref={articleRef} className="docs-prose mt-6" dangerouslySetInnerHTML={{ __html: doc.html }} />

                        <div className="mt-14 flex items-center justify-between gap-4 border-t border-neutral-200 pt-6">
                            {pagination.prev ? (
                                <Link
                                    href={pagination.prev.url}
                                    className="group flex min-w-0 flex-col rounded-xl border border-neutral-200 px-4 py-3 transition hover:border-neutral-400"
                                >
                                    <span className="text-xs font-medium text-neutral-400">← Previous</span>
                                    <span className="mt-1 truncate text-sm font-semibold text-neutral-900">{pagination.prev.title}</span>
                                </Link>
                            ) : (
                                <span />
                            )}

                            {pagination.next && (
                                <Link
                                    href={pagination.next.url}
                                    className="group flex min-w-0 flex-col items-end rounded-xl border border-neutral-200 px-4 py-3 text-right transition hover:border-neutral-400"
                                >
                                    <span className="text-xs font-medium text-neutral-400">Next →</span>
                                    <span className="mt-1 truncate text-sm font-semibold text-neutral-900">{pagination.next.title}</span>
                                </Link>
                            )}
                        </div>
                    </div>

                    <aside className="hidden w-52 shrink-0 xl:block">
                        {doc.toc.length > 0 && (
                            <nav className="sticky top-24" aria-label="On this page">
                                <p className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">On this page</p>
                                <ul className="mt-3 space-y-2 border-l border-neutral-200">
                                    {doc.toc.map((entry) => (
                                        <li key={entry.id}>
                                            <a
                                                href={`#${entry.id}`}
                                                onClick={(event) => {
                                                    event.preventDefault();
                                                    scrollToHeading(entry.id);
                                                }}
                                                className={`-ml-px block border-l-2 py-0.5 text-[13px] transition ${
                                                    entry.level === 3 ? 'pl-7' : 'pl-4'
                                                } ${
                                                    activeHeading === entry.id
                                                        ? 'border-neutral-900 font-medium text-neutral-900'
                                                        : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-900'
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
