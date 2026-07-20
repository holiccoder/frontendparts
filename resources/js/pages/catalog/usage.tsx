import { ComponentGrid } from '@/components/catalog/component-card';
import { Pagination } from '@/components/catalog/pagination';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { ComponentCardData, PageMeta, Paginated } from '@/types/catalog';
import { Link } from '@inertiajs/react';

interface RelatedUsage {
    name: string;
    slug: string;
    url: string;
}

interface UsagePageProps {
    usage: {
        name: string;
        slug: string;
        zone: string | null;
        description: string;
    };
    components: Paginated<ComponentCardData>;
    relatedUsages: RelatedUsage[];
    meta: PageMeta;
}

export default function UsagePage({ usage, components, relatedUsages, meta }: UsagePageProps) {
    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <nav className="text-sm text-neutral-400" aria-label="Breadcrumb">
                    <Link href="/components" className="transition hover:text-neutral-900">
                        Components
                    </Link>
                    <span className="mx-2">/</span>
                    <span className="text-neutral-600">{usage.name}</span>
                </nav>

                <div className="mt-4 max-w-2xl">
                    {usage.zone && <span className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">{usage.zone}</span>}
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight sm:text-4xl">{usage.name} components</h1>
                    <p className="mt-3 leading-7 text-neutral-500">{usage.description}</p>
                </div>

                <div className="mt-10">
                    {components.data.length > 0 ? (
                        <ComponentGrid components={components.data} />
                    ) : (
                        <div className="rounded-2xl border border-dashed border-neutral-300 py-20 text-center">
                            <p className="text-lg font-semibold">Nothing here yet</p>
                            <p className="mt-2 text-sm text-neutral-500">We're authoring components for this category right now.</p>
                        </div>
                    )}
                </div>

                <Pagination meta={components.meta} />

                {relatedUsages.length > 0 && (
                    <div className="mt-14 border-t border-neutral-100 pt-8">
                        <h2 className="text-sm font-semibold tracking-wide text-neutral-400 uppercase">Explore more usages</h2>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {relatedUsages.map((related) => (
                                <Link
                                    key={related.slug}
                                    href={related.url}
                                    className="rounded-full border border-neutral-300 px-4 py-1.5 text-sm font-medium text-neutral-600 transition hover:border-neutral-400 hover:text-neutral-900"
                                >
                                    {related.name}
                                </Link>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}
