import { ComponentGrid } from '@/components/catalog/component-card';
import { Pagination } from '@/components/catalog/pagination';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import { cn } from '@/lib/utils';
import type { ComponentCardData, Framework, IndustryFilter, PageMeta, Paginated, UsageFilter } from '@/types/catalog';
import { Link } from '@inertiajs/react';
import { ChevronDown, Search } from 'lucide-react';

interface CatalogIndexProps {
    components: Paginated<ComponentCardData>;
    filters: {
        industries: IndustryFilter[];
        usages: UsageFilter[];
        levels: string[];
        access: string[];
    };
    active: {
        industry: string[];
        usage: string | null;
        level: string | null;
        access: string | null;
        q: string | null;
    };
    framework: Framework;
    meta: PageMeta;
}

function PillRadio({ name, value, label, checked }: { name: string; value: string; label: string; checked: boolean }) {
    return (
        <label
            className={cn(
                'cursor-pointer rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                checked ? 'border-neutral-900 bg-neutral-900 text-white' : 'border-neutral-300 bg-white text-neutral-600 hover:border-neutral-400',
            )}
        >
            <input
                type="radio"
                name={name}
                value={value}
                defaultChecked={checked}
                className="sr-only"
                onChange={(event) => event.currentTarget.form?.requestSubmit()}
            />
            {label}
        </label>
    );
}

export default function CatalogIndex({ components, filters, active, framework, meta }: CatalogIndexProps) {
    return (
        <PublicLayout>
            <SeoHead meta={meta} />

            <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div className="max-w-2xl">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">Component catalog</h1>
                    <p className="mt-3 text-neutral-500">
                        Every section, block and page ships with live previews and clean React + Vue source. Filter by industry, usage, level or
                        access — or search by name and tags.
                    </p>
                </div>

                <form method="GET" action="/components" className="mt-10 space-y-5 rounded-2xl border border-neutral-200 bg-neutral-50/60 p-5">
                    <input type="hidden" name="framework" value={framework} />

                    <div className="flex flex-col gap-3 lg:flex-row">
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                            <input
                                type="search"
                                name="q"
                                defaultValue={active.q ?? ''}
                                placeholder="Search by name or tag…"
                                className="h-10 w-full rounded-md border border-neutral-300 bg-white pr-3 pl-9 text-sm outline-none placeholder:text-neutral-400 focus:border-neutral-500"
                            />
                        </div>

                        <select
                            name="usage"
                            defaultValue={active.usage ?? ''}
                            onChange={(event) => event.currentTarget.form?.requestSubmit()}
                            className="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-700 outline-none focus:border-neutral-500"
                            aria-label="Filter by usage"
                        >
                            <option value="">All usages</option>
                            {filters.usages.map((usage) => (
                                <option key={usage.slug} value={usage.slug}>
                                    {usage.name} ({usage.components_count})
                                </option>
                            ))}
                        </select>

                        <details className="group relative">
                            <summary className="flex h-10 cursor-pointer list-none items-center gap-2 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-700 select-none [&::-webkit-details-marker]:hidden">
                                Industries
                                {active.industry.length > 0 && (
                                    <span className="rounded-full bg-neutral-900 px-1.5 text-[11px] font-semibold text-white">
                                        {active.industry.length}
                                    </span>
                                )}
                                <ChevronDown className="h-4 w-4 text-neutral-400 transition group-open:rotate-180" />
                            </summary>
                            <div className="absolute z-20 mt-2 max-h-72 w-72 overflow-auto rounded-xl border border-neutral-200 bg-white p-3 shadow-lg">
                                {filters.industries.length === 0 && <p className="px-1 py-2 text-sm text-neutral-400">No industries yet.</p>}
                                {filters.industries.map((industry) => (
                                    <label
                                        key={industry.slug}
                                        className="flex cursor-pointer items-center justify-between gap-3 rounded-md px-2 py-1.5 text-sm text-neutral-700 hover:bg-neutral-50"
                                    >
                                        <span className="flex items-center gap-2.5">
                                            <input
                                                type="checkbox"
                                                name="industry[]"
                                                value={industry.slug}
                                                defaultChecked={active.industry.includes(industry.slug)}
                                                className="h-4 w-4 rounded border-neutral-300 accent-neutral-900"
                                                onChange={(event) => event.currentTarget.form?.requestSubmit()}
                                            />
                                            {industry.name}
                                        </span>
                                        <span className="text-xs text-neutral-400">{industry.components_count}</span>
                                    </label>
                                ))}
                            </div>
                        </details>

                        <button
                            type="submit"
                            className="h-10 rounded-md bg-neutral-900 px-5 text-sm font-semibold text-white transition hover:bg-neutral-700"
                        >
                            Apply
                        </button>
                    </div>

                    <div className="flex flex-wrap items-center gap-x-8 gap-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Level</span>
                            <PillRadio name="level" value="" label="All" checked={active.level === null} />
                            {filters.levels.map((level) => (
                                <PillRadio key={level} name="level" value={level} label={level} checked={active.level === level} />
                            ))}
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Access</span>
                            <PillRadio name="access" value="" label="All" checked={active.access === null} />
                            {filters.access.map((access) => (
                                <PillRadio
                                    key={access}
                                    name="access"
                                    value={access}
                                    label={access === 'paid' ? 'Pro' : 'Free'}
                                    checked={active.access === access}
                                />
                            ))}
                        </div>
                    </div>
                </form>

                <div className="mt-8 flex items-center justify-between">
                    <p className="text-sm text-neutral-500">
                        {components.meta.total} {components.meta.total === 1 ? 'component' : 'components'}
                    </p>
                    {(active.q || active.usage || active.level || active.access || active.industry.length > 0) && (
                        <Link href="/components" className="text-sm font-semibold text-neutral-900 underline underline-offset-4">
                            Clear filters
                        </Link>
                    )}
                </div>

                <div className="mt-5">
                    {components.data.length > 0 ? (
                        <ComponentGrid components={components.data} />
                    ) : (
                        <div className="rounded-2xl border border-dashed border-neutral-300 py-20 text-center">
                            <p className="text-lg font-semibold">No components match your filters</p>
                            <p className="mt-2 text-sm text-neutral-500">Try widening the selection or clearing the search.</p>
                        </div>
                    )}
                </div>

                <Pagination meta={components.meta} />
            </div>
        </PublicLayout>
    );
}
