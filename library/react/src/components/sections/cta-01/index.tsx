/**
 * @component  cta-01
 * @name       CTA 01
 * @level      section
 * @usage      cta
 * @industries
 * @tags       minimal, conversion, accent
 * @access     free
 * @source     https://www.twilio.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';
import Button01 from '../../elements/button-01';

interface Cta01Props extends ComponentPropsWithoutRef<'section'> {
    /** Main heading inside the panel. */
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
}

export default function Cta01({
    heading = 'Ready to get started?',
    subheading = '',
    primaryLabel = 'Get started',
    primaryHref = '#',
    secondaryLabel = '',
    secondaryHref = '#',
    className = '',
    ...rest
}: Cta01Props) {
    return (
        <section {...rest} className={`bg-white dark:bg-neutral-950 ${className}`}>
            <div className="mx-auto max-w-7xl px-6 py-16 sm:py-20">
                <div className="flex flex-col items-center gap-6 rounded-3xl bg-indigo-600 px-6 py-16 text-center dark:bg-indigo-500">
                    <h2 className="max-w-2xl text-3xl font-bold tracking-tight text-white sm:text-4xl">{heading}</h2>
                    {subheading !== '' && (
                        <p className="max-w-xl text-lg leading-8 text-indigo-100 dark:text-indigo-50">{subheading}</p>
                    )}
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Button01 label={primaryLabel} href={primaryHref} variant="inverted" size="lg" showArrow />
                        {secondaryLabel !== '' && (
                            <Button01
                                label={secondaryLabel}
                                href={secondaryHref}
                                variant="invertedGhost"
                                size="lg"
                            />
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
