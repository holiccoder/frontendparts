/**
 * @component  avatar-01
 * @name       Avatar 01
 * @level      element
 * @usage      team
 * @industries
 * @tags       minimal, media
 * @access     free
 * @source     https://github.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';

interface Avatar01Props extends ComponentPropsWithoutRef<'img'> {
    /** Image URL. Falls back to initials when empty. */
    src?: string;
    /** Alt text describing the person. */
    alt?: string;
    /** Initials shown when no image is set. */
    fallback?: string;
    /** Size preset. */
    size?: 'sm' | 'md' | 'lg';
}

const SIZES: Record<NonNullable<Avatar01Props['size']>, string> = {
    sm: 'size-8 text-xs',
    md: 'size-10 text-sm',
    lg: 'size-14 text-base',
};

export default function Avatar01({
    src = '',
    alt = 'Team member',
    fallback = 'FP',
    size = 'md',
    className = '',
    ...rest
}: Avatar01Props) {
    if (src === '') {
        return (
            <span
                {...(rest as ComponentPropsWithoutRef<'span'>)}
                role="img"
                aria-label={alt}
                className={`inline-flex shrink-0 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700 ring-2 ring-white dark:bg-indigo-500/20 dark:text-indigo-300 dark:ring-neutral-900 ${SIZES[size]} ${className}`}
            >
                {fallback}
            </span>
        );
    }

    return (
        <img
            {...rest}
            src={src}
            alt={alt}
            loading="lazy"
            className={`shrink-0 rounded-full bg-neutral-200 object-cover ring-2 ring-white dark:bg-neutral-800 dark:ring-neutral-900 ${SIZES[size]} ${className}`}
        />
    );
}
