import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { IndustryTile, PageMeta } from '@/types/catalog';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

interface IndustriesIndexProps {
    industries: IndustryTile[];
    meta: PageMeta;
}

export default function IndustriesIndex({ industries, meta }: IndustriesIndexProps) {
    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div className="max-w-2xl">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">Components by industry</h1>
                    <p className="mt-3 text-neutral-500">
                        Twelve curated collections, each recreated from the best sites in the vertical — so your page starts from patterns that
                        already convert.
                    </p>
                </div>

                <div className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {industries.map((industry) => (
                        <Link
                            key={industry.slug}
                            href={industry.url}
                            className="group flex flex-col rounded-2xl border border-neutral-200 bg-white p-6 transition hover:border-neutral-300 hover:shadow-[0_8px_30px_rgb(0,0,0,0.05)]"
                        >
                            <div className="flex items-center justify-between">
                                <h2 className="text-base font-semibold">{industry.name}</h2>
                                <ArrowRight className="h-4 w-4 text-neutral-300 transition group-hover:translate-x-0.5 group-hover:text-neutral-600" />
                            </div>
                            <span className="mt-1 text-xs font-medium text-neutral-400">
                                {industry.components_count} {industry.components_count === 1 ? 'component' : 'components'}
                            </span>
                            {industry.description && <p className="mt-3 line-clamp-3 text-sm leading-6 text-neutral-500">{industry.description}</p>}
                        </Link>
                    ))}
                </div>
            </div>
        </PublicLayout>
    );
}
