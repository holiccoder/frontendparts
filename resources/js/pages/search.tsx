import { PostCard } from '@/components/blog/post-card';
import { ComponentGrid } from '@/components/catalog/component-card';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { BlogPostCard } from '@/types/blog';
import type { ComponentCardData, PageMeta } from '@/types/catalog';
import { Link } from '@inertiajs/react';
import { Search, Sparkles } from 'lucide-react';

interface AiSearchState {
    available: boolean;
    requested: boolean;
    active: boolean;
    limited: boolean;
    keywords: string | null;
    filters: string[];
}

interface SearchProps {
    query: string;
    components: ComponentCardData[];
    posts: BlogPostCard[];
    ai: AiSearchState;
    meta: PageMeta;
}

/**
 * `/search?q=` (SPEC §15.1, FR-1.3): SSR site search over the component
 * catalog and the blog, grouped Components / Blog. The form is a plain
 * GET so results stay deep-linkable; empty queries and zero-hit queries
 * both land on a helpful empty state. The page is noindex (search results
 * are excluded from indexing).
 *
 * AI-assisted mode (task 5.4, features.ai_search): when the flag is on the
 * form gains an "AI-assisted" checkbox that resubmits with ai=1. AI results
 * are labeled, show the refined keywords and applied taxonomy filters, and
 * any failure (rate limit, provider error) silently falls back to plain
 * results.
 */
export default function SearchPage({ query, components, posts, ai, meta }: SearchProps) {
    const hasResults = components.length > 0 || posts.length > 0;

    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div className="max-w-2xl">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{query === '' ? 'Search' : `Results for “${query}”`}</h1>
                    <p className="mt-3 text-neutral-500">
                        Search the full component catalog and the blog — sections, blocks and pages for React and Vue.
                    </p>
                </div>

                <form method="GET" action="/search" className="mt-8 max-w-2xl">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                        <input
                            type="search"
                            name="q"
                            defaultValue={query}
                            placeholder="Search components, tags, categories, articles…"
                            aria-label="Search the site"
                            autoFocus
                            className="h-11 w-full rounded-md border border-neutral-300 bg-white pr-3 pl-9 text-sm outline-none placeholder:text-neutral-400 focus:border-neutral-500"
                        />
                    </div>

                    {ai.available && (
                        <label className="mt-3 inline-flex cursor-pointer items-center gap-2 text-sm text-neutral-600 select-none">
                            <input
                                type="checkbox"
                                name="ai"
                                value="1"
                                defaultChecked={ai.requested}
                                className="h-4 w-4 rounded border-neutral-300 accent-neutral-900"
                            />
                            <Sparkles className="h-4 w-4 text-neutral-400" />
                            AI-assisted search — describe what you need in plain words
                        </label>
                    )}
                </form>

                {ai.active && (
                    <div className="mt-8 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm">
                        <span className="inline-flex items-center gap-1.5 font-semibold">
                            <Sparkles className="h-4 w-4" />
                            AI-assisted results
                        </span>
                        {ai.keywords && <span className="text-neutral-500">keywords: “{ai.keywords}”</span>}
                        {ai.filters.map((filter) => (
                            <span
                                key={filter}
                                className="rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-neutral-600 ring-1 ring-neutral-200 ring-inset"
                            >
                                {filter}
                            </span>
                        ))}
                    </div>
                )}

                {ai.limited && (
                    <p className="mt-8 rounded-xl border border-dashed border-neutral-300 px-4 py-3 text-sm text-neutral-500">
                        AI-assisted search is temporarily rate-limited — showing standard results instead.
                    </p>
                )}

                {query !== '' && !hasResults && (
                    <div className="mt-10 rounded-2xl border border-dashed border-neutral-300 py-20 text-center">
                        <p className="text-lg font-semibold">Nothing matches “{query}”</p>
                        <p className="mt-2 text-sm text-neutral-500">
                            Try fewer or different words — or browse the{' '}
                            <Link href="/components" className="font-semibold text-neutral-900 underline underline-offset-4">
                                catalog
                            </Link>{' '}
                            and the{' '}
                            <Link href="/blog" className="font-semibold text-neutral-900 underline underline-offset-4">
                                blog
                            </Link>
                            .
                        </p>
                    </div>
                )}

                {components.length > 0 && (
                    <section className="mt-12">
                        <h2 className="text-lg font-semibold tracking-tight">
                            Components <span className="ml-1 text-sm font-normal text-neutral-400">({components.length})</span>
                        </h2>
                        <div className="mt-5">
                            <ComponentGrid components={components} />
                        </div>
                    </section>
                )}

                {posts.length > 0 && (
                    <section className="mt-12">
                        <h2 className="text-lg font-semibold tracking-tight">
                            Blog <span className="ml-1 text-sm font-normal text-neutral-400">({posts.length})</span>
                        </h2>
                        <div className="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {posts.map((post) => (
                                <PostCard key={post.slug} post={post} />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </PublicLayout>
    );
}
