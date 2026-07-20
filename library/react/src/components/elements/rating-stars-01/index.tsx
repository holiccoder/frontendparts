/**
 * @component  rating-stars-01
 * @name       Rating Stars 01
 * @level      element
 * @usage      reviews-ratings
 * @industries
 * @tags       minimal, social-proof
 * @access     free
 * @source     https://www.trustpilot.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';

interface RatingStars01Props extends ComponentPropsWithoutRef<'div'> {
    /** Rating value from 0 to 5; fractional values fill stars partially. */
    rating?: number;
    /** Show the numeric value next to the stars. */
    showValue?: boolean;
}

function Star({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" className={className}>
            <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5z" />
        </svg>
    );
}

export default function RatingStars01({ rating = 5, showValue = false, className = '', ...rest }: RatingStars01Props) {
    const clamped = Math.max(0, Math.min(5, rating));

    return (
        <div
            {...rest}
            role="img"
            aria-label={`Rated ${clamped} out of 5 stars`}
            className={`inline-flex items-center gap-2 ${className}`}
        >
            <span className="inline-flex items-center gap-0.5">
                {[0, 1, 2, 3, 4].map((index) => {
                    const fill = Math.max(0, Math.min(1, clamped - index)) * 100;

                    return (
                        <span key={index} className="relative inline-flex size-5">
                            <Star className="size-5 text-neutral-300 dark:text-neutral-700" />
                            <span className="absolute inset-0 overflow-hidden" style={{ width: `${fill}%` }}>
                                <Star className="size-5 text-amber-400" />
                            </span>
                        </span>
                    );
                })}
            </span>
            {showValue && (
                <span className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{clamped.toFixed(1)}</span>
            )}
        </div>
    );
}
