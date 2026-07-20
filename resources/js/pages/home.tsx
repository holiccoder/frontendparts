import { ComponentGrid } from '@/components/catalog/component-card';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { ComponentCardData, IndustryTile, PageMeta } from '@/types/catalog';
import { Link } from '@inertiajs/react';
import { ArrowRight, Copy, Layers, MousePointerClick } from 'lucide-react';

interface PlanTeaser {
    amount: string | null;
    currency: string;
}

interface BlogTeaser {
    title: string;
    slug: string;
    excerpt: string;
    published_at: string | null;
}

interface HomeProps {
    featuredComponents: ComponentCardData[];
    industries: IndustryTile[];
    pricing: {
        starter: PlanTeaser | null;
        pro: PlanTeaser | null;
    };
    latestComponents: ComponentCardData[];
    posts: BlogTeaser[];
    meta: PageMeta;
}

const STEPS = [
    {
        icon: Layers,
        title: 'Browse & preview',
        body: 'Explore sections, blocks and pages recreated from the best sites on the web — with live previews at every breakpoint.',
    },
    {
        icon: MousePointerClick,
        title: 'Copy or download',
        body: 'Grab clean React or Vue source with sample data and params wired in. No lock-in, no wrappers — just your code.',
    },
    {
        icon: Copy,
        title: 'Ship your page',
        body: 'Drop the files into your app, adjust the content and publish. What used to take days now takes minutes.',
    },
];

function Price({ plan }: { plan: PlanTeaser | null }) {
    if (!plan || plan.amount === null) {
        return <span className="text-4xl font-semibold tracking-tight">—</span>;
    }

    return (
        <span className="flex items-baseline gap-1">
            <span className="text-4xl font-semibold tracking-tight">${Number(plan.amount).toFixed(0)}</span>
            <span className="text-sm text-neutral-500">/ month</span>
        </span>
    );
}

export default function Home({ featuredComponents, industries, pricing, latestComponents, posts, meta }: HomeProps) {
    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            {/* Hero */}
            <section className="border-b border-neutral-100">
                <div className="mx-auto max-w-7xl px-4 py-24 text-center sm:px-6 sm:py-32 lg:px-8">
                    <span className="inline-flex items-center rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold tracking-wide text-neutral-600 uppercase">
                        React &amp; Vue · Every component twice implemented
                    </span>
                    <h1 className="mx-auto mt-6 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-6xl">
                        Ship pages that look like the best sites on the web
                    </h1>
                    <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-neutral-500">
                        A catalog of production-ready sections, blocks and pages — recreated from world-class sites, with live previews, sample data
                        and clean code you actually own.
                    </p>
                    <div className="mt-10 flex items-center justify-center gap-3">
                        <Link
                            href="/components"
                            className="inline-flex items-center gap-2 rounded-md bg-neutral-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-neutral-700"
                        >
                            Browse components <ArrowRight className="h-4 w-4" />
                        </Link>
                        <Link
                            href="/industries"
                            className="inline-flex items-center rounded-md border border-neutral-300 px-6 py-3 text-sm font-semibold text-neutral-700 transition hover:border-neutral-400 hover:bg-neutral-50"
                        >
                            Explore industries
                        </Link>
                    </div>
                </div>
            </section>

            {/* Featured components */}
            {featuredComponents.length > 0 && (
                <section className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                    <div className="flex items-end justify-between">
                        <div>
                            <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">Featured components</h2>
                            <p className="mt-2 text-neutral-500">Hand-picked sections and blocks with live previews at 375, 768 and 1280px.</p>
                        </div>
                        <Link href="/components" className="hidden items-center gap-1 text-sm font-semibold text-neutral-900 sm:inline-flex">
                            View all <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                    <div className="mt-8">
                        <ComponentGrid components={featuredComponents} />
                    </div>
                </section>
            )}

            {/* Industries */}
            {industries.length > 0 && (
                <section className="border-y border-neutral-100 bg-neutral-50/60">
                    <div className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                        <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">Made for your industry</h2>
                        <p className="mt-2 max-w-2xl text-neutral-500">
                            Components recreated from the best sites in each vertical — pick yours and start from what already works.
                        </p>
                        <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {industries.map((industry) => (
                                <Link
                                    key={industry.slug}
                                    href={industry.url}
                                    className="flex flex-col rounded-xl border border-neutral-200 bg-white p-5 transition hover:border-neutral-300 hover:shadow-[0_8px_30px_rgb(0,0,0,0.05)]"
                                >
                                    <span className="text-sm font-semibold">{industry.name}</span>
                                    <span className="mt-1 text-xs font-medium text-neutral-400">
                                        {industry.components_count} {industry.components_count === 1 ? 'component' : 'components'}
                                    </span>
                                    {industry.description && (
                                        <span className="mt-3 line-clamp-3 text-sm leading-6 text-neutral-500">{industry.description}</span>
                                    )}
                                </Link>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {/* How it works */}
            <section className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <h2 className="text-center text-2xl font-semibold tracking-tight sm:text-3xl">How it works</h2>
                <div className="mt-12 grid gap-10 sm:grid-cols-3">
                    {STEPS.map((step, index) => (
                        <div key={step.title} className="text-center">
                            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-900 text-white">
                                <step.icon className="h-5 w-5" />
                            </div>
                            <div className="mt-4 text-xs font-semibold tracking-wide text-neutral-400 uppercase">Step {index + 1}</div>
                            <h3 className="mt-1 text-lg font-semibold">{step.title}</h3>
                            <p className="mt-2 text-sm leading-6 text-neutral-500">{step.body}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* Pricing teaser */}
            <section className="border-y border-neutral-100 bg-neutral-50/60">
                <div className="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:px-8">
                    <h2 className="text-center text-2xl font-semibold tracking-tight sm:text-3xl">Simple pricing, full library</h2>
                    <p className="mx-auto mt-3 max-w-xl text-center text-neutral-500">
                        Start free with a rotating selection. Upgrade once for the entire catalog in both frameworks.
                    </p>
                    <div className="mt-10 grid gap-6 sm:grid-cols-2">
                        {(
                            [
                                { name: 'Starter', plan: pricing.starter, blurb: 'The full library for one developer.' },
                                { name: 'Pro', plan: pricing.pro, blurb: 'Library plus project scaffolding and exports.' },
                            ] as const
                        ).map((tier) => (
                            <div key={tier.name} className="rounded-2xl border border-neutral-200 bg-white p-8">
                                <h3 className="text-sm font-semibold tracking-wide uppercase">{tier.name}</h3>
                                <div className="mt-4">
                                    <Price plan={tier.plan} />
                                </div>
                                <p className="mt-3 text-sm leading-6 text-neutral-500">{tier.blurb}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Latest drops */}
            {latestComponents.length > 0 && (
                <section className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                    <div className="flex items-end justify-between">
                        <div>
                            <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">Latest drops</h2>
                            <p className="mt-2 text-neutral-500">Fresh from the authoring pipeline.</p>
                        </div>
                        <Link href="/components" className="hidden items-center gap-1 text-sm font-semibold text-neutral-900 sm:inline-flex">
                            View all <ArrowRight className="h-4 w-4" />
                        </Link>
                    </div>
                    <div className="mt-8">
                        <ComponentGrid components={latestComponents} />
                    </div>
                </section>
            )}

            {/* Blog teaser */}
            {posts.length > 0 && (
                <section className="border-t border-neutral-100 bg-neutral-50/60">
                    <div className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                        <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">From the blog</h2>
                        <div className="mt-8 grid gap-5 md:grid-cols-3">
                            {posts.map((post) => (
                                <article key={post.slug} className="rounded-xl border border-neutral-200 bg-white p-6">
                                    {post.published_at && <time className="text-xs font-medium text-neutral-400">{post.published_at}</time>}
                                    <h3 className="mt-2 text-base font-semibold">{post.title}</h3>
                                    <p className="mt-2 line-clamp-3 text-sm leading-6 text-neutral-500">{post.excerpt}</p>
                                </article>
                            ))}
                        </div>
                    </div>
                </section>
            )}
        </PublicLayout>
    );
}
