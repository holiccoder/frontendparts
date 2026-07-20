import { ComponentGrid } from '@/components/catalog/component-card';
import { PreviewModal } from '@/components/preview-modal';
import SeoHead from '@/components/seo-head';
import PublicLayout from '@/layouts/public-layout';
import type { ComponentDetailData, Framework, PageMeta } from '@/types/catalog';
import { Head, Link } from '@inertiajs/react';

interface ComponentPageProps {
    component: ComponentDetailData;
    framework: Framework;
    meta: PageMeta;
}

export default function ComponentPage({ component, framework, meta }: ComponentPageProps) {
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

                {/* The modal IS the detail page's main section (SPEC §5.4). */}
                <PreviewModal
                    component={component}
                    initialFramework={framework}
                    variant="inline"
                    className="mt-6 rounded-2xl border border-neutral-200"
                />

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
