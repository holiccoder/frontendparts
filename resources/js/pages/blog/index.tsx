import { PostCard } from '@/components/blog/post-card';
import { Pagination } from '@/components/catalog/pagination';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { BlogCategoryWithCount, BlogPostCard } from '@/types/blog';
import type { PageMeta, Paginated } from '@/types/catalog';
import { Link } from '@inertiajs/react';

interface BlogIndexProps {
    posts: Paginated<BlogPostCard>;
    categories: BlogCategoryWithCount[];
    meta: PageMeta;
}

/**
 * Blog index `/blog` (SPEC §13.1, §15.1): SSR, SEO-indexed article grid
 * with category navigation.
 */
export default function BlogIndex({ posts, categories, meta }: BlogIndexProps) {
    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <header className="max-w-2xl">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">Blog</h1>
                    <p className="mt-3 leading-7 text-neutral-500">
                        Design teardowns and industry × usage articles — every example recreated as a production-ready component in the catalog.
                    </p>
                </header>

                {categories.length > 0 && (
                    <nav className="mt-8 flex flex-wrap gap-2" aria-label="Blog categories">
                        {categories.map((category) => (
                            <Link
                                key={category.slug}
                                href={category.url}
                                className="rounded-full border border-neutral-300 px-3 py-1.5 text-xs font-semibold text-neutral-600 transition hover:border-neutral-400 hover:text-neutral-900"
                            >
                                {category.name} ({category.posts_count})
                            </Link>
                        ))}
                    </nav>
                )}

                {posts.data.length === 0 ? (
                    <p className="mt-20 text-center text-sm text-neutral-400">No articles yet — check back soon.</p>
                ) : (
                    <div className="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        {posts.data.map((post) => (
                            <PostCard key={post.slug} post={post} />
                        ))}
                    </div>
                )}

                <Pagination meta={posts.meta} />
            </div>
        </PublicLayout>
    );
}
