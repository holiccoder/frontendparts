/**
 * @component  testimonial-card-01
 * @name       Testimonial Card 01
 * @level      block
 * @usage      testimonial
 * @industries
 * @tags       minimal, social-proof
 * @access     free
 * @source     https://www.supabase.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';
import Avatar01 from '../../elements/avatar-01';
import RatingStars01 from '../../elements/rating-stars-01';

interface TestimonialCard01Props extends ComponentPropsWithoutRef<'figure'> {
    /** The quoted testimonial text. */
    quote?: string;
    /** Person's full name. */
    name?: string;
    /** Role and company, e.g. "Head of Product, Driftly". */
    role?: string;
    /** Star rating from 0 to 5. */
    rating?: number;
    /** Portrait image URL. Falls back to initials when empty. */
    avatarSrc?: string;
    /** Alt text for the portrait. */
    avatarAlt?: string;
    /** Initials shown when no portrait is set. */
    avatarFallback?: string;
}

export default function TestimonialCard01({
    quote = 'This changed how our team ships.',
    name = 'Alex Rivera',
    role = 'Engineering Lead',
    rating = 5,
    avatarSrc = '',
    avatarAlt = '',
    avatarFallback = 'AR',
    className = '',
    ...rest
}: TestimonialCard01Props) {
    return (
        <figure
            {...rest}
            className={`flex flex-col gap-5 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8 dark:border-neutral-800 dark:bg-neutral-900 ${className}`}
        >
            <RatingStars01 rating={rating} />
            <blockquote className="text-base leading-7 text-neutral-700 dark:text-neutral-300">
                &ldquo;{quote}&rdquo;
            </blockquote>
            <figcaption className="mt-auto flex items-center gap-3">
                <Avatar01 src={avatarSrc} alt={avatarAlt !== '' ? avatarAlt : `Portrait of ${name}`} fallback={avatarFallback} />
                <div className="flex flex-col">
                    <span className="text-sm font-semibold text-neutral-900 dark:text-white">{name}</span>
                    <span className="text-sm text-neutral-500 dark:text-neutral-400">{role}</span>
                </div>
            </figcaption>
        </figure>
    );
}
