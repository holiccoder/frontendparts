/**
 * @component  logo-cloud-row-01
 * @name       Logo Cloud Row 01
 * @level      block
 * @usage      logo-cloud
 * @industries
 * @tags       minimal, social-proof
 * @access     free
 * @source     https://www.resend.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef, ReactNode } from 'react';

interface LogoCloudRow01Logo {
    /** Wordmark text. */
    name?: string;
}

interface LogoCloudRow01Props extends ComponentPropsWithoutRef<'div'> {
    /** Small caption above the row. Hidden when empty. */
    caption?: string;
    /** Wordmarks to display. */
    logos?: LogoCloudRow01Logo[];
}

/** Abstract placeholder glyphs cycled by index — swap for real brand marks in your project. */
const GLYPHS: ReactNode[] = [
    <path key="0" fillRule="evenodd" d="M10 17a7 7 0 1 0 0-14 7 7 0 0 0 0 14Zm0-4a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />,
    <path key="1" d="M10 3l7 14H3L10 3Z" />,
    <rect key="2" x="3" y="3" width="14" height="14" rx="3" />,
    <path key="3" d="M10 2l8 8-8 8-8-8 8-8Z" />,
    <path key="4" d="M3 17a7 7 0 0 1 14 0H3Z" />,
    <path key="5" d="M11 2 4 11h5l-1 7 7-9h-5l1-7Z" />,
];

const WORDMARK_STYLES = [
    'text-lg font-bold tracking-tight',
    'font-serif text-lg italic',
    'text-sm font-semibold tracking-widest uppercase',
    'text-lg font-black tracking-tight',
    'text-sm font-light tracking-[0.25em] uppercase',
    'text-lg font-bold italic',
];

export default function LogoCloudRow01({
    caption = '',
    logos = [],
    className = '',
    ...rest
}: LogoCloudRow01Props) {
    return (
        <div {...rest} className={`flex flex-col gap-6 ${className}`}>
            {caption !== '' && (
                <p className="text-center text-sm font-medium text-neutral-500 dark:text-neutral-400">{caption}</p>
            )}
            <div className="flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
                {logos.map((logo, index) => (
                    <span
                        key={index}
                        className="flex items-center gap-2 text-neutral-400 transition-colors hover:text-neutral-600 dark:text-neutral-500 dark:hover:text-neutral-300"
                    >
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" className="size-5 shrink-0">
                            {GLYPHS[index % GLYPHS.length]}
                        </svg>
                        <span className={WORDMARK_STYLES[index % WORDMARK_STYLES.length]}>{logo.name}</span>
                    </span>
                ))}
            </div>
        </div>
    );
}
