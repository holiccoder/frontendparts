import { type SharedData } from '@/types';
import type { PageMeta } from '@/types/shared';
import { Head, usePage } from '@inertiajs/react';

/**
 * Shared SEO head for the public zone: unique title + meta description,
 * canonical link and OG/Twitter tags per page.
 */
export default function SeoHead({ meta }: { meta: PageMeta }) {
    const { name } = usePage<SharedData>().props;

    return (
        <Head title={meta.title}>
            <meta name="description" content={meta.description} />
            {meta.robots && <meta name="robots" content={meta.robots} />}
            <link rel="canonical" href={meta.canonical} />
            <meta property="og:site_name" content={name} />
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
