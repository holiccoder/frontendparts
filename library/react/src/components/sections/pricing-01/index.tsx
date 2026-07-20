/**
 * @component  pricing-01
 * @name       Pricing 01
 * @level      section
 * @usage      pricing
 * @industries saas-software, fintech-finance
 * @tags       minimal, conversion
 * @access     pro
 * @source     https://www.chargebee.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';
import PricingCard01 from '../../blocks/pricing-card-01';
import SectionTitle01 from '../../elements/section-title-01';

interface SectionTitleSlice {
    eyebrow?: string;
    heading?: string;
    description?: string;
    align?: 'left' | 'center';
}

interface PricingCardSlice {
    plan?: string;
    price?: number;
    currency?: string;
    period?: string;
    description?: string;
    features?: string[];
    ctaLabel?: string;
    ctaHref?: string;
    featured?: boolean;
    badgeLabel?: string;
}

interface Pricing01Props extends ComponentPropsWithoutRef<'section'> {
    /** Child slices keyed by child slug (library README `children` convention). */
    children?: {
        'section-title-01'?: SectionTitleSlice;
        'pricing-card-01'?: PricingCardSlice[];
    };
}

export default function Pricing01({ children, className = '', ...rest }: Pricing01Props) {
    const title = children?.['section-title-01'] ?? {};
    const [first = {}, second = {}, third = {}] = children?.['pricing-card-01'] ?? [];

    return (
        <section {...rest} className={`bg-white dark:bg-neutral-950 ${className}`}>
            <div className="mx-auto flex max-w-7xl flex-col gap-12 px-6 py-16 sm:py-20">
                <SectionTitle01 {...title} />
                <div className="mx-auto grid w-full max-w-5xl items-stretch gap-8 lg:grid-cols-3">
                    <PricingCard01 {...first} />
                    <PricingCard01 {...second} />
                    <PricingCard01 {...third} />
                </div>
            </div>
        </section>
    );
}
