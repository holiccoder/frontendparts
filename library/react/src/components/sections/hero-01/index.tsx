/**
 * @component  hero-01
 * @name       Hero 01
 * @level      section
 * @usage      hero
 * @industries
 * @tags       minimal, centered
 * @access     free
 * @source     https://www.posthog.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';
import Button01 from '../../elements/button-01';

interface Hero01Props extends ComponentPropsWithoutRef<'section'> {
    /** Small label above the heading. Hidden when empty. */
    eyebrow?: string;
    /** Main headline. */
    heading?: string;
    /** Supporting paragraph. Hidden when empty. */
    subheading?: string;
    /** Primary button label. */
    primaryLabel?: string;
    /** Primary button link target. */
    primaryHref?: string;
    /** Secondary button label. Hidden when empty. */
    secondaryLabel?: string;
    /** Secondary button link target. */
    secondaryHref?: string;
    /** Product screenshot URL. Hidden when empty. */
    imageSrc?: string;
    /** Alt text for the screenshot. */
    imageAlt?: string;
}

export default function Hero01({
    eyebrow = '',
    heading = 'Build something great',
    subheading = '',
    primaryLabel = 'Get started',
    primaryHref = '#',
    secondaryLabel = '',
    secondaryHref = '#',
    imageSrc = '',
    imageAlt = 'Product preview',
    className = '',
    ...rest
}: Hero01Props) {
    return (
        <section {...rest} className={`bg-white dark:bg-neutral-950 ${className}`}>
            <div className="mx-auto flex max-w-7xl flex-col items-center gap-12 px-6 py-16 sm:py-20">
                <div className="flex max-w-3xl flex-col items-center gap-6 text-center">
                    {eyebrow !== '' && (
                        <span className="text-sm font-semibold tracking-widest text-indigo-600 uppercase dark:text-indigo-400">
                            {eyebrow}
                        </span>
                    )}
                    <h1 className="text-4xl font-bold tracking-tight text-neutral-900 sm:text-5xl dark:text-white">
                        {heading}
                    </h1>
                    {subheading !== '' && (
                        <p className="text-lg leading-8 text-neutral-600 dark:text-neutral-400">{subheading}</p>
                    )}
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button01 label={primaryLabel} href={primaryHref} size="lg" showArrow />
                        {secondaryLabel !== '' && (
                            <Button01 label={secondaryLabel} href={secondaryHref} variant="secondary" size="lg" />
                        )}
                    </div>
                </div>
                {imageSrc !== '' && (
                    <img
                        src={imageSrc}
                        alt={imageAlt}
                        loading="lazy"
                        className="w-full max-w-5xl rounded-2xl bg-neutral-100 shadow-xl ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-800"
                    />
                )}
            </div>
        </section>
    );
}
