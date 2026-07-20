/**
 * @component  footer-01
 * @name       Footer 01
 * @level      section
 * @usage      footer
 * @industries
 * @tags       minimal, navigation
 * @access     pro
 * @source     https://www.cloudflare.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';
import NewsletterForm01 from '../../blocks/newsletter-form-01';

interface Footer01Link {
    /** Link text. */
    label?: string;
    /** Link target. */
    href?: string;
}

interface Footer01Column {
    /** Column heading. */
    title?: string;
    /** Links inside the column. */
    links?: Footer01Link[];
}

interface NewsletterFormSlice {
    placeholder?: string;
    buttonLabel?: string;
    note?: string;
}

interface Footer01Props extends ComponentPropsWithoutRef<'footer'> {
    /** Brand name shown next to the logo mark. */
    brand?: string;
    /** Short tagline under the brand. */
    tagline?: string;
    /** Heading above the newsletter form. Hidden when empty. */
    newsletterHeading?: string;
    /** Link columns. */
    columns?: Footer01Column[];
    /** Copyright line in the bottom bar. */
    copyright?: string;
    /** Legal links in the bottom bar. */
    legalLinks?: Footer01Link[];
    /** Child slices keyed by child slug (library README `children` convention). */
    children?: {
        'newsletter-form-01'?: NewsletterFormSlice;
    };
}

export default function Footer01({
    brand = 'Acme',
    tagline = '',
    newsletterHeading = '',
    columns = [],
    copyright = '',
    legalLinks = [],
    children,
    className = '',
    ...rest
}: Footer01Props) {
    const newsletter = children?.['newsletter-form-01'] ?? {};

    return (
        <footer
            {...rest}
            className={`border-t border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950 ${className}`}
        >
            <div className="mx-auto flex max-w-7xl flex-col gap-12 px-6 py-16">
                <div className="grid gap-12 lg:grid-cols-12">
                    <div className="flex flex-col gap-5 lg:col-span-5">
                        <a
                            href="#"
                            className="flex items-center gap-2 text-base font-bold tracking-tight text-neutral-900 dark:text-white"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                aria-hidden="true"
                                className="size-6 text-indigo-600 dark:text-indigo-400"
                            >
                                <path d="M12 2l10 10-10 10L2 12 12 2Z" />
                            </svg>
                            {brand}
                        </a>
                        {tagline !== '' && (
                            <p className="max-w-sm text-sm leading-6 text-neutral-600 dark:text-neutral-400">{tagline}</p>
                        )}
                        {newsletterHeading !== '' && (
                            <h3 className="pt-2 text-sm font-semibold text-neutral-900 dark:text-white">{newsletterHeading}</h3>
                        )}
                        <NewsletterForm01 {...newsletter} />
                    </div>
                    <div className="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-7">
                        {columns.map((column, index) => (
                            <div key={index} className="flex flex-col gap-4">
                                <h3 className="text-sm font-semibold text-neutral-900 dark:text-white">{column.title}</h3>
                                <ul className="flex flex-col gap-3">
                                    {(column.links ?? []).map((link, linkIndex) => (
                                        <li key={linkIndex}>
                                            <a
                                                href={link.href}
                                                className="text-sm text-neutral-600 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                                            >
                                                {link.label}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="flex flex-col gap-4 border-t border-neutral-200 pt-8 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800">
                    {copyright !== '' && <p className="text-sm text-neutral-500 dark:text-neutral-400">{copyright}</p>}
                    <div className="flex gap-6">
                        {legalLinks.map((link, index) => (
                            <a
                                key={index}
                                href={link.href}
                                className="text-sm text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                            >
                                {link.label}
                            </a>
                        ))}
                    </div>
                </div>
            </div>
        </footer>
    );
}
