import type { PageMeta } from '@/types/catalog';
import { Head } from '@inertiajs/react';

/**
 * Shared SEO head for the public zone (SPEC §10.2): unique title + meta
 * description, canonical link and OG/Twitter tags per page.
 */
export default function SeoHead({ meta }: { meta: PageMeta }) {
    return (
        <Head title={meta.title}>
            <meta name="description" content={meta.description} />
            {meta.robots && <meta name="robots" content={meta.robots} />}
            <link rel="canonical" href={meta.canonical} />
            <meta property="og:site_name" content="FrontendParts" />
            <meta property="og:title" content={meta.title} />
            <meta property="og:description" content={meta.description} />
            <meta property="og:url" content={meta.canonical} />
            <meta property="og:type" content={meta.og_type ?? 'website'} />
            <meta property="og:image" content={meta.og_image} />
            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content={meta.title} />
            <meta name="twitter:description" content={meta.description} />
            <meta name="twitter:image" content={meta.og_image} />
        </Head>
    );
}
