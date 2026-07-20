import { AccessBadge, LevelBadge } from '@/components/catalog/badges';
import { ComponentGrid } from '@/components/catalog/component-card';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import { cn } from '@/lib/utils';
import type { ComponentDetailData, Framework, PageMeta, TreeNode } from '@/types/catalog';
import { Head, Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';

interface ComponentPageProps {
    component: ComponentDetailData;
    framework: Framework;
    meta: PageMeta;
}

const WIDTH_PRESETS = [
    { label: '375', value: 375 },
    { label: '768', value: 768 },
    { label: '1280', value: 1280 },
    { label: 'Full', value: 0 },
] as const;

const TABS = ['Preview', 'Code', 'Data', 'Docs'] as const;
type Tab = (typeof TABS)[number];

function TreeNodeRow({ node, depth }: { node: TreeNode; depth: number }) {
    return (
        <>
            <div className="flex items-center gap-2 py-1" style={{ paddingLeft: `${depth * 16}px` }}>
                <span className="text-sm font-medium text-neutral-800">{node.name}</span>
                <span className="rounded-full bg-neutral-100 px-1.5 text-[10px] font-semibold tracking-wide text-neutral-500 uppercase">
                    {node.level}
                </span>
                {node.instances > 1 && (
                    <span className="text-xs font-medium text-neutral-400">
                        ×{node.instances}
                        <span className="ml-1.5 inline-flex gap-1">
                            {Array.from({ length: node.instances }, (_, index) => (
                                <span key={index} className="rounded border border-neutral-300 px-1 text-[10px] text-neutral-500">
                                    #{index + 1}
                                </span>
                            ))}
                        </span>
                    </span>
                )}
            </div>
            {node.children.map((child) => (
                <TreeNodeRow key={child.slug} node={child} depth={depth + 1} />
            ))}
        </>
    );
}

function CodeTab({ component, framework }: { component: ComponentDetailData; framework: Framework }) {
    const files = component.files[framework];
    const fallback = component.files.react.length > 0 ? component.files.react : component.files.vue;

    const shown = files.length > 0 ? files : fallback;

    if (shown.length === 0) {
        return <p className="py-10 text-center text-sm text-neutral-400">Source files are not available in this environment.</p>;
    }

    return (
        <div className="space-y-6">
            {shown.map((file) => (
                <div key={file.path}>
                    <div className="rounded-t-lg border border-b-0 border-neutral-200 bg-neutral-100 px-4 py-2 font-mono text-xs text-neutral-600">
                        {file.path}
                    </div>
                    <pre className="max-h-[560px] overflow-auto rounded-b-lg border border-neutral-200 bg-neutral-950 p-4 text-xs leading-5 text-neutral-100">
                        <code>{file.code}</code>
                    </pre>
                </div>
            ))}
        </div>
    );
}

function DataTab({ component }: { component: ComponentDetailData }) {
    if (Object.keys(component.data).length === 0) {
        return <p className="py-10 text-center text-sm text-neutral-400">No sample data for this component.</p>;
    }

    return (
        <pre className="max-h-[560px] overflow-auto rounded-lg border border-neutral-200 bg-neutral-950 p-4 text-xs leading-5 text-neutral-100">
            <code>{JSON.stringify(component.data, null, 2)}</code>
        </pre>
    );
}

function DocsTab({ component }: { component: ComponentDetailData }) {
    const params = Object.entries(component.params);

    return (
        <div className="space-y-10">
            <section>
                <h3 className="text-sm font-semibold tracking-wide text-neutral-400 uppercase">Props</h3>
                {params.length > 0 ? (
                    <div className="mt-3 overflow-x-auto rounded-lg border border-neutral-200">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-neutral-200 bg-neutral-50 text-xs tracking-wide text-neutral-500 uppercase">
                                    <th className="px-4 py-2.5 font-semibold">Prop</th>
                                    <th className="px-4 py-2.5 font-semibold">Type</th>
                                    <th className="px-4 py-2.5 font-semibold">Default</th>
                                    <th className="px-4 py-2.5 font-semibold">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                {params.map(([name, definition]) => (
                                    <tr key={name} className="border-b border-neutral-100 last:border-0">
                                        <td className="px-4 py-2.5 font-mono text-xs font-semibold">{name}</td>
                                        <td className="px-4 py-2.5 font-mono text-xs text-neutral-600">
                                            {definition.type}
                                            {definition.type === 'enum' && definition.options ? ` (${definition.options.join(' | ')})` : ''}
                                        </td>
                                        <td className="px-4 py-2.5 font-mono text-xs text-neutral-600">
                                            {JSON.stringify(definition.default ?? null)}
                                        </td>
                                        <td className="px-4 py-2.5 text-neutral-600">{definition.description}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <p className="mt-3 text-sm text-neutral-400">This component takes no params.</p>
                )}
            </section>

            <section>
                <h3 className="text-sm font-semibold tracking-wide text-neutral-400 uppercase">Dependencies</h3>
                {component.deps.length > 0 ? (
                    <ul className="mt-3 flex flex-wrap gap-2">
                        {component.deps.map((dep) => (
                            <li
                                key={dep}
                                className="rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 font-mono text-xs text-neutral-700"
                            >
                                {dep}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="mt-3 inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 ring-inset">
                        Zero npm dependencies
                    </p>
                )}
            </section>

            <section>
                <h3 className="text-sm font-semibold tracking-wide text-neutral-400 uppercase">Version</h3>
                <p className="mt-3 font-mono text-sm text-neutral-700">v{component.version}</p>
            </section>
        </div>
    );
}

export default function ComponentPage({ component, framework, meta }: ComponentPageProps) {
    const [activeFramework, setActiveFramework] = useState<Framework>(framework);
    const [activeTab, setActiveTab] = useState<Tab>('Preview');
    const [width, setWidth] = useState<number>(0);

    const previewUrl = component.previews[activeFramework] ?? component.previews.react;

    const jsonLd = {
        '@context': 'https://schema.org',
        '@type': 'SoftwareSourceCode',
        additionalType: 'CreativeWork',
        name: component.name,
        url: meta.canonical,
        ...(component.og_image ? { image: component.og_image } : {}),
        programmingLanguage: ['TypeScript', 'Vue'],
        codeRepository: meta.canonical,
        version: component.version,
        author: {
            '@type': 'Organization',
            name: 'FrontendParts',
        },
    };

    return (
        <PublicLayout>
            <SeoHead meta={meta} />
            <Head>
                <script type="application/ld+json">{JSON.stringify(jsonLd)}</script>
            </Head>

            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                {/* Header */}
                <nav className="text-sm text-neutral-400" aria-label="Breadcrumb">
                    <Link href="/components" className="transition hover:text-neutral-900">
                        Components
                    </Link>
                    <span className="mx-2">/</span>
                    <Link href={`/components/${component.usage.slug}`} className="transition hover:text-neutral-900">
                        {component.usage.name}
                    </Link>
                    <span className="mx-2">/</span>
                    <span className="text-neutral-600">{component.name}</span>
                </nav>

                <div className="mt-4 flex flex-wrap items-start justify-between gap-6">
                    <div className="max-w-2xl">
                        <div className="flex flex-wrap items-center gap-2">
                            <LevelBadge level={component.level} />
                            <AccessBadge access={component.access} />
                            <span className="text-xs font-medium text-neutral-400">v{component.version}</span>
                        </div>
                        <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{component.name}</h1>

                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <Link
                                href={`/components/${component.usage.slug}`}
                                className="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-700 transition hover:bg-neutral-200"
                            >
                                {component.usage.name}
                            </Link>
                            {component.industries.map((industry) => (
                                <Link
                                    key={industry.slug}
                                    href={`/industries/${industry.slug}`}
                                    className="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-700 transition hover:bg-neutral-200"
                                >
                                    {industry.name}
                                </Link>
                            ))}
                            {component.tags.map((tag) => (
                                <span
                                    key={tag.slug}
                                    className="inline-flex items-center rounded-full border border-neutral-200 px-2.5 py-1 text-xs font-medium text-neutral-500"
                                >
                                    #{tag.name}
                                </span>
                            ))}
                        </div>

                        {component.citation.source_name && component.citation.source_url && (
                            <p className="mt-4 text-sm text-neutral-500">
                                Layout reference:{' '}
                                <a
                                    href={component.citation.source_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 font-medium text-neutral-900 underline underline-offset-4"
                                >
                                    {component.citation.source_name}
                                    <ExternalLink className="h-3.5 w-3.5" />
                                </a>
                            </p>
                        )}
                    </div>

                    {/* Framework toggle */}
                    <div className="inline-flex rounded-lg border border-neutral-200 bg-neutral-50 p-1">
                        {(['react', 'vue'] as const).map((option) => (
                            <button
                                key={option}
                                type="button"
                                onClick={() => setActiveFramework(option)}
                                className={cn(
                                    'rounded-md px-4 py-1.5 text-sm font-semibold capitalize transition',
                                    activeFramework === option ? 'bg-white text-neutral-900 shadow-sm' : 'text-neutral-500 hover:text-neutral-900',
                                )}
                            >
                                {option}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Tabs */}
                <div className="mt-10 border-b border-neutral-200">
                    <div className="flex gap-1" role="tablist">
                        {TABS.map((tab) => (
                            <button
                                key={tab}
                                type="button"
                                role="tab"
                                aria-selected={activeTab === tab}
                                onClick={() => setActiveTab(tab)}
                                className={cn(
                                    '-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition',
                                    activeTab === tab
                                        ? 'border-neutral-900 text-neutral-900'
                                        : 'border-transparent text-neutral-400 hover:text-neutral-700',
                                )}
                            >
                                {tab}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mt-8">
                    {activeTab === 'Preview' && (
                        <div className="space-y-6">
                            {/* Width switcher (client enhancement; SSR renders full width) */}
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Viewport</span>
                                {WIDTH_PRESETS.map((preset) => (
                                    <button
                                        key={preset.label}
                                        type="button"
                                        onClick={() => setWidth(preset.value)}
                                        className={cn(
                                            'rounded-full border px-3 py-1 text-xs font-semibold transition',
                                            width === preset.value
                                                ? 'border-neutral-900 bg-neutral-900 text-white'
                                                : 'border-neutral-300 text-neutral-600 hover:border-neutral-400',
                                        )}
                                    >
                                        {preset.label}
                                    </button>
                                ))}
                            </div>

                            <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
                                {/* Structure tree */}
                                <aside className="rounded-xl border border-neutral-200 bg-neutral-50/60 p-4">
                                    <h2 className="text-xs font-semibold tracking-wide text-neutral-400 uppercase">Structure</h2>
                                    <div className="mt-3">
                                        {component.tree.children.length > 0 || component.tree ? (
                                            <TreeNodeRow node={component.tree} depth={0} />
                                        ) : (
                                            <p className="text-sm text-neutral-400">Primitive component — no children.</p>
                                        )}
                                    </div>
                                </aside>

                                {/* Stage */}
                                <div className="overflow-x-auto rounded-xl border border-neutral-200 bg-neutral-100 p-4">
                                    {previewUrl ? (
                                        <iframe
                                            key={`${activeFramework}-${width}`}
                                            src={previewUrl}
                                            title={`${component.name} preview`}
                                            sandbox="allow-scripts"
                                            style={{ width: width === 0 ? '100%' : `${width}px` }}
                                            className="mx-auto block h-[640px] max-w-full rounded-lg border border-neutral-200 bg-white"
                                        />
                                    ) : (
                                        <div className="flex h-[320px] items-center justify-center rounded-lg border border-dashed border-neutral-300 bg-white">
                                            <p className="text-sm text-neutral-400">Preview is being built for this component.</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {activeTab === 'Code' && <CodeTab component={component} framework={activeFramework} />}
                    {activeTab === 'Data' && <DataTab component={component} />}
                    {activeTab === 'Docs' && <DocsTab component={component} />}
                </div>

                {/* Related */}
                {component.related.length > 0 && (
                    <section className="mt-20 border-t border-neutral-100 pt-12">
                        <h2 className="text-2xl font-semibold tracking-tight">More {component.usage.name} components</h2>
                        <div className="mt-8">
                            <ComponentGrid components={component.related} />
                        </div>
                    </section>
                )}
            </div>
        </PublicLayout>
    );
}
