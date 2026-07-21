import { ComponentGrid } from '@/components/catalog/component-card';
import { Pagination } from '@/components/catalog/pagination';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { ComponentCardData, PageMeta, Paginated } from '@/types/catalog';
import { Link } from '@inertiajs/react';

interface CollectionShowProps {
    collection: {
        name: string;
        slug: string;
        description: string;
    };
    components: Paginated<ComponentCardData>;
    meta: PageMeta;
}

export default function CollectionShow({ collection, components, meta }: CollectionShowProps) {
    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <nav className="text-sm text-neutral-400" aria-label="Breadcrumb">
                    <Link href="/collections" className="transition hover:text-neutral-900">
                        Collections
                    </Link>
                    <span className="mx-2">/</span>
                    <span className="text-neutral-600">{collection.name}</span>
                </nav>

                <div className="mt-4 max-w-2xl">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{collection.name}</h1>
                    <p className="mt-3 leading-7 text-neutral-500">{collection.description}</p>
                </div>

                <div className="mt-10">
                    {components.data.length > 0 ? (
                        <ComponentGrid components={components.data} />
                    ) : (
                        <div className="rounded-2xl border border-dashed border-neutral-300 py-20 text-center">
                            <p className="text-lg font-semibold">Nothing here yet</p>
                            <p className="mt-2 text-sm text-neutral-500">
                                We're assembling this bundle right now — check the{' '}
                                <Link href="/components" className="font-semibold text-neutral-900 underline underline-offset-4">
                                    full catalog
                                </Link>
                                .
                            </p>
                        </div>
                    )}
                </div>

                <Pagination meta={components.meta} />
            </div>
        </PublicLayout>
    );
}
