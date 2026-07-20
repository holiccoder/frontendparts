/**
 * @component  feature-grid-01
 * @name       Feature Grid 01
 * @level      section
 * @usage      feature-grid
 * @industries
 * @tags       minimal, icons
 * @access     free
 * @source     https://cal.com
 * @deps       lucide
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';
import FeatureCard01 from '../../blocks/feature-card-01';
import SectionTitle01 from '../../elements/section-title-01';

interface SectionTitleSlice {
    eyebrow?: string;
    heading?: string;
    description?: string;
    align?: 'left' | 'center';
}

interface FeatureCardSlice {
    icon?: string;
    title?: string;
    description?: string;
}

interface FeatureGrid01Props extends ComponentPropsWithoutRef<'section'> {
    /** Child slices keyed by child slug (library README `children` convention). */
    children?: {
        'section-title-01'?: SectionTitleSlice;
        'feature-card-01'?: FeatureCardSlice[];
    };
}

export default function FeatureGrid01({ children, className = '', ...rest }: FeatureGrid01Props) {
    const title = children?.['section-title-01'] ?? {};
    const [one = {}, two = {}, three = {}, four = {}, five = {}, six = {}] = children?.['feature-card-01'] ?? [];

    return (
        <section {...rest} className={`bg-white dark:bg-neutral-950 ${className}`}>
            <div className="mx-auto flex max-w-7xl flex-col gap-12 px-6 py-16 sm:py-20">
                <SectionTitle01 {...title} />
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <FeatureCard01 {...one} />
                    <FeatureCard01 {...two} />
                    <FeatureCard01 {...three} />
                    <FeatureCard01 {...four} />
                    <FeatureCard01 {...five} />
                    <FeatureCard01 {...six} />
                </div>
            </div>
        </section>
    );
}
