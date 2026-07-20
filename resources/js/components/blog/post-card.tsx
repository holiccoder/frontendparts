import type { BlogPostCard } from '@/types/blog';
import { Link } from '@inertiajs/react';

/**
 * Post card shared by the blog index, category pages and the article's
 * related-posts row (SPEC §13.1).
 */
export function PostCard({ post }: { post: BlogPostCard }) {
    return (
        <article className="flex flex-col rounded-xl border border-neutral-200 bg-white p-6 transition hover:border-neutral-400">
            {post.categories.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {post.categories.map((category) => (
                        <Link
                            key={category.slug}
                            href={category.url}
                            className="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-600 transition hover:bg-neutral-200 hover:text-neutral-900"
                        >
                            {category.name}
                        </Link>
                    ))}
                </div>
            )}

            <h3 className="mt-3 text-base font-semibold tracking-tight">
                <Link href={post.url} className="transition hover:text-neutral-600">
                    {post.title}
                </Link>
            </h3>

            {post.excerpt && <p className="mt-2 line-clamp-3 text-sm leading-6 text-neutral-500">{post.excerpt}</p>}

            <div className="mt-auto flex items-center gap-2 pt-4 text-xs font-medium text-neutral-400">
                {post.published_at && <time dateTime={post.published_at}>{post.published_at}</time>}
                <span aria-hidden="true">·</span>
                <span>{post.reading_time} min read</span>
            </div>
        </article>
    );
}
