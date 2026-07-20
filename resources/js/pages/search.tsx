import { PostCard } from '@/components/blog/post-card';
import { ComponentGrid } from '@/components/catalog/component-card';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { BlogPostCard } from '@/types/blog';
import type { ComponentCardData, PageMeta } from '@/types/catalog';
import { Link } from '@inertiajs/react';
import { Search } from 'lucide-react';

interface SearchProps {
    query: string;
    components: ComponentCardData[];
    posts: BlogPostCard[];
    meta: PageMeta;
}

/**
 * `/search?q=` (SPEC §15.1, FR-1.3): SSR site search over the component
 * catalog and the blog, grouped Components / Blog. The form is a plain
 * GET so results stay deep-linkable; empty queries and zero-hit queries
 * both land on a helpful empty state. The page is noindex (search results
 * are excluded from indexing).
 */
export default function SearchPage({ query, components, posts, meta }: SearchProps) {
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
                </form>

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
