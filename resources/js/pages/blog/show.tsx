import { PostCard } from '@/components/blog/post-card';
import { ComponentGrid } from '@/components/catalog/component-card';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { BlogArticle, BlogPostCard } from '@/types/blog';
import type { ComponentCardData, PageMeta } from '@/types/catalog';
import { Head, Link } from '@inertiajs/react';

interface BlogShowProps {
    post: BlogArticle;
    relatedPosts: BlogPostCard[];
    relatedComponents: ComponentCardData[];
    jsonLd: Record<string, unknown>;
    meta: PageMeta;
}

/**
 * Blog article `/blog/{slug}` (SPEC §13.1): SSR long-form page with a
 * heading-derived TOC, Article structured data, related posts and the
 * catalog cross-linking mechanic (related components).
 */
export default function BlogShow({ post, relatedPosts, relatedComponents, jsonLd, meta }: BlogShowProps) {
    const primaryCategory = post.categories[0] ?? null;

    return (
        <PublicLayout>
            <SeoHead meta={meta} />
            <Head>
                <script type="application/ld+json">{JSON.stringify(jsonLd)}</script>
            </Head>

            <div className="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
                <nav className="text-sm text-neutral-400" aria-label="Breadcrumb">
                    <Link href="/blog" className="transition hover:text-neutral-900">
                        Blog
                    </Link>
                    {primaryCategory && (
                        <>
                            <span className="mx-2">/</span>
                            <Link href={primaryCategory.url} className="transition hover:text-neutral-900">
                                {primaryCategory.name}
                            </Link>
                        </>
                    )}
                    <span className="mx-2">/</span>
                    <span className="text-neutral-600">{post.title}</span>
                </nav>

                <div className="mt-8 flex gap-10">
                    <article className="max-w-3xl min-w-0 flex-1">
                        <header>
                            <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{post.title}</h1>
                            <div className="mt-4 flex flex-wrap items-center gap-2 text-sm text-neutral-400">
                                {post.published_at && <time dateTime={post.published_at_iso ?? undefined}>{post.published_at}</time>}
                                <span aria-hidden="true">·</span>
                                <span>{post.reading_time} min read</span>
                                {post.author && (
                                    <>
                                        <span aria-hidden="true">·</span>
                                        <span>by {post.author}</span>
                                    </>
                                )}
                            </div>
                        </header>

                        {post.featured_image && (
                            <img
                                src={post.featured_image}
                                alt=""
                                className="mt-8 aspect-[2/1] w-full rounded-2xl border border-neutral-200 object-cover"
                            />
                        )}

                        <div className="docs-prose mt-8" dangerouslySetInnerHTML={{ __html: post.body_html }} />

                        {post.tags.length > 0 && (
                            <div className="mt-10 flex flex-wrap gap-2 border-t border-neutral-100 pt-6">
                                {post.tags.map((tag) => (
                                    <span key={tag.slug} className="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-500">
                                        #{tag.name}
                                    </span>
                                ))}
                            </div>
                        )}
                    </article>

                    <aside className="hidden w-52 shrink-0 xl:block">
                        {post.toc.length > 0 && (
                            <nav className="sticky top-24" aria-label="On this page">
                                <p className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">On this page</p>
                                <ul className="mt-3 space-y-2 border-l border-neutral-200">
                                    {post.toc.map((entry) => (
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

                {relatedComponents.length > 0 && (
                    <section className="mt-20 border-t border-neutral-100 pt-12">
                        <h2 className="text-2xl font-semibold tracking-tight">Components featured in this article</h2>
                        <p className="mt-2 text-sm text-neutral-500">Every example is live in the catalog — preview it, then copy the code.</p>
                        <div className="mt-8">
                            <ComponentGrid components={relatedComponents} />
                        </div>
                    </section>
                )}

                {relatedPosts.length > 0 && (
                    <section className="mt-20 border-t border-neutral-100 pt-12">
                        <h2 className="text-2xl font-semibold tracking-tight">Keep reading</h2>
                        <div className="mt-8 grid gap-5 md:grid-cols-3">
                            {relatedPosts.map((related) => (
                                <PostCard key={related.slug} post={related} />
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </PublicLayout>
    );
}
