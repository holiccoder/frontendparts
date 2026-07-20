/**
 * @component  button-01
 * @name       Button 01
 * @level      element
 * @usage      cta
 * @industries
 * @tags       minimal, interactive
 * @access     free
 * @source     https://stripe.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';

interface Button01Props extends ComponentPropsWithoutRef<'a'> {
    /** Visible button text. */
    label?: string;
    /** Link target. */
    href?: string;
    /** Visual style (`inverted*` variants are for accent/dark backgrounds). */
    variant?: 'primary' | 'secondary' | 'ghost' | 'inverted' | 'invertedGhost';
    /** Size preset. */
    size?: 'sm' | 'md' | 'lg';
    /** Show a trailing arrow icon. */
    showArrow?: boolean;
}

const VARIANTS: Record<NonNullable<Button01Props['variant']>, string> = {
    primary: 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400',
    secondary:
        'bg-white text-neutral-900 shadow-sm ring-1 ring-inset ring-neutral-300 hover:bg-neutral-50 dark:bg-neutral-900 dark:text-white dark:ring-neutral-700 dark:hover:bg-neutral-800',
    ghost: 'text-neutral-700 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800',
    inverted: 'bg-white text-indigo-600 shadow-sm hover:bg-indigo-50 dark:bg-white dark:text-indigo-600 dark:hover:bg-indigo-50',
    invertedGhost: 'text-white hover:bg-white/10 dark:text-white dark:hover:bg-white/10',
};

const SIZES: Record<NonNullable<Button01Props['size']>, string> = {
    sm: 'gap-1.5 px-3 py-1.5 text-sm',
    md: 'gap-2 px-4 py-2 text-sm',
    lg: 'gap-2 px-6 py-3 text-base',
};

export default function Button01({
    label = 'Get started',
    href = '#',
    variant = 'primary',
    size = 'md',
    showArrow = false,
    className = '',
    ...rest
}: Button01Props) {
    return (
        <a
            {...rest}
            href={href}
            className={`inline-flex items-center justify-center rounded-lg font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:focus-visible:outline-indigo-400 ${VARIANTS[variant]} ${SIZES[size]} ${className}`}
        >
            {label}
            {showArrow && (
                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true" className="size-4">
                    <path
                        d="M4 10h12m0 0-4.5-4.5M16 10l-4.5 4.5"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            )}
        </a>
    );
}
