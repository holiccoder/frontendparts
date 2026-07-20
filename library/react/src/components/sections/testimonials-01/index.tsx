/**
 * @component  testimonials-01
 * @name       Testimonials 01
 * @level      section
 * @usage      testimonial
 * @industries
 * @tags       minimal, social-proof
 * @access     free
 * @source     https://www.gumroad.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';
import TestimonialCard01 from '../../blocks/testimonial-card-01';
import SectionTitle01 from '../../elements/section-title-01';

interface SectionTitleSlice {
    eyebrow?: string;
    heading?: string;
    description?: string;
    align?: 'left' | 'center';
}

interface TestimonialCardSlice {
    quote?: string;
    name?: string;
    role?: string;
    rating?: number;
    avatarSrc?: string;
    avatarAlt?: string;
    avatarFallback?: string;
}

interface Testimonials01Props extends ComponentPropsWithoutRef<'section'> {
    /** Child slices keyed by child slug (library README `children` convention). */
    children?: {
        'section-title-01'?: SectionTitleSlice;
        'testimonial-card-01'?: TestimonialCardSlice[];
    };
}

export default function Testimonials01({ children, className = '', ...rest }: Testimonials01Props) {
    const title = children?.['section-title-01'] ?? {};
    const [first = {}, second = {}, third = {}] = children?.['testimonial-card-01'] ?? [];

    return (
        <section {...rest} className={`bg-neutral-50 dark:bg-neutral-900 ${className}`}>
            <div className="mx-auto flex max-w-7xl flex-col gap-12 px-6 py-16 sm:py-20">
                <SectionTitle01 {...title} />
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <TestimonialCard01 {...first} />
                    <TestimonialCard01 {...second} />
                    <TestimonialCard01 {...third} />
                </div>
            </div>
        </section>
    );
}
