import { PostCard } from '@/components/blog/post-card';
import { Pagination } from '@/components/catalog/pagination';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { BlogCategoryWithCount, BlogPostCard } from '@/types/blog';
import type { PageMeta, Paginated } from '@/types/catalog';
import { Link } from '@inertiajs/react';

interface BlogCategoryProps {
    category: {
        name: string;
        slug: string;
        description: string | null;
    };
    posts: Paginated<BlogPostCard>;
    categories: BlogCategoryWithCount[];
    meta: PageMeta;
}

/**
 * Blog category page `/blog/category/{slug}` (SPEC §13.1, §15.1): SSR
 * keyword landing page listing the category's live articles.
 */
export default function BlogCategory({ category, posts, categories, meta }: BlogCategoryProps) {
    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <nav className="text-sm text-neutral-400" aria-label="Breadcrumb">
                    <Link href="/blog" className="transition hover:text-neutral-900">
                        Blog
                    </Link>
                    <span className="mx-2">/</span>
                    <span className="text-neutral-600">{category.name}</span>
                </nav>

                <header className="mt-6 max-w-2xl">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{category.name}</h1>
                    {category.description && <p className="mt-3 leading-7 text-neutral-500">{category.description}</p>}
                </header>

                {categories.length > 0 && (
                    <nav className="mt-8 flex flex-wrap gap-2" aria-label="Blog categories">
                        {categories.map((item) => (
                            <Link
                                key={item.slug}
                                href={item.url}
                                className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition ${
                                    item.slug === category.slug
                                        ? 'border-neutral-900 bg-neutral-900 text-white'
                                        : 'border-neutral-300 text-neutral-600 hover:border-neutral-400 hover:text-neutral-900'
                                }`}
                            >
                                {item.name} ({item.posts_count})
                            </Link>
                        ))}
                    </nav>
                )}

                {posts.data.length === 0 ? (
                    <p className="mt-20 text-center text-sm text-neutral-400">No articles in this category yet.</p>
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
