import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { CollectionTile, PageMeta } from '@/types/catalog';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

interface CollectionsIndexProps {
    collections: CollectionTile[];
    meta: PageMeta;
}

export default function CollectionsIndex({ collections, meta }: CollectionsIndexProps) {
    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div className="max-w-2xl">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">Collections</h1>
                    <p className="mt-3 text-neutral-500">
                        Curated bundles of components that together ship a complete page — pick a kit and start from patterns that already convert.
                    </p>
                </div>

                {collections.length > 0 ? (
                    <div className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {collections.map((collection) => (
                            <Link
                                key={collection.slug}
                                href={collection.url}
                                className="group flex flex-col rounded-2xl border border-neutral-200 bg-white p-6 transition hover:border-neutral-300 hover:shadow-[0_8px_30px_rgb(0,0,0,0.05)]"
                            >
                                <div className="flex items-center justify-between">
                                    <h2 className="text-base font-semibold">{collection.name}</h2>
                                    <ArrowRight className="h-4 w-4 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-neutral-600" />
                                </div>
                                <span className="mt-1 text-xs font-medium text-neutral-400">
                                    {collection.components_count} {collection.components_count === 1 ? 'component' : 'components'}
                                </span>
                                {collection.description && (
                                    <p className="mt-3 line-clamp-3 text-sm leading-6 text-neutral-500">{collection.description}</p>
                                )}
                            </Link>
                        ))}
                    </div>
                ) : (
                    <div className="mt-10 rounded-2xl border border-dashed border-neutral-300 py-20 text-center">
                        <p className="text-lg font-semibold">No collections yet</p>
                        <p className="mt-2 text-sm text-neutral-500">
                            We're curating our first bundles right now — meanwhile, browse the{' '}
                            <Link href="/components" className="font-semibold text-neutral-900 underline underline-offset-4">
                                full catalog
                            </Link>
                            .
                        </p>
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}
