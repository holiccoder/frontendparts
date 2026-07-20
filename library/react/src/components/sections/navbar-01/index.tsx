/**
 * @component  navbar-01
 * @name       Navbar 01
 * @level      section
 * @usage      navbar
 * @industries
 * @tags       interactive, navigation, a11y
 * @access     pro
 * @source     https://www.digitalocean.com
 * @deps
 * @version    1.0.0
 */
import { useEffect, useId, useState } from 'react';
import type { ComponentPropsWithoutRef } from 'react';
import Button01 from '../../elements/button-01';

interface Navbar01Link {
    /** Link text. */
    label?: string;
    /** Link target. */
    href?: string;
}

interface Navbar01Props extends ComponentPropsWithoutRef<'header'> {
    /** Brand name shown next to the logo mark. */
    brand?: string;
    /** Navigation links (desktop row + mobile panel). */
    links?: Navbar01Link[];
    /** Call-to-action button label. */
    ctaLabel?: string;
    /** Call-to-action link target. */
    ctaHref?: string;
}

export default function Navbar01({
    brand = 'Acme',
    links = [],
    ctaLabel = 'Get started',
    ctaHref = '#',
    className = '',
    ...rest
}: Navbar01Props) {
    const [open, setOpen] = useState(false);
    const menuId = useId();

    useEffect(() => {
        if (! open) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open]);

    return (
        <header
            {...rest}
            className={`sticky top-0 z-10 border-b border-neutral-200 bg-white/80 backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80 ${className}`}
        >
            <nav aria-label="Main" className="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
                <a
                    href="#"
                    className="flex items-center gap-2 text-base font-bold tracking-tight text-neutral-900 dark:text-white"
                >
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" className="size-6 text-indigo-600 dark:text-indigo-400">
                        <path d="M12 2l10 10-10 10L2 12 12 2Z" />
                    </svg>
                    {brand}
                </a>
                <div className="hidden items-center gap-8 md:flex">
                    {links.map((link, index) => (
                        <a
                            key={index}
                            href={link.href}
                            className="text-sm font-medium text-neutral-600 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                        >
                            {link.label}
                        </a>
                    ))}
                </div>
                <div className="hidden md:block">
                    <Button01 label={ctaLabel} href={ctaHref} size="sm" />
                </div>
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls={menuId}
                    aria-label="Toggle navigation menu"
                    onClick={() => setOpen((value) => !value)}
                    className="inline-flex size-10 items-center justify-center rounded-lg text-neutral-700 transition-colors hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 md:hidden dark:text-neutral-300 dark:hover:bg-neutral-800"
                >
                    {open ? (
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true" className="size-5">
                            <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                        </svg>
                    ) : (
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true" className="size-5">
                            <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
                        </svg>
                    )}
                </button>
            </nav>
            {open && (
                <div id={menuId} className="border-t border-neutral-200 px-6 py-4 md:hidden dark:border-neutral-800">
                    <div className="flex flex-col gap-1">
                        {links.map((link, index) => (
                            <a
                                key={index}
                                href={link.href}
                                onClick={() => setOpen(false)}
                                className="rounded-lg px-3 py-2.5 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            >
                                {link.label}
                            </a>
                        ))}
                        <div className="pt-3">
                            <Button01 label={ctaLabel} href={ctaHref} className="w-full" />
                        </div>
                    </div>
                </div>
            )}
        </header>
    );
}
