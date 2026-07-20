/**
 * @component  pricing-card-01
 * @name       Pricing Card 01
 * @level      block
 * @usage      pricing
 * @industries
 * @tags       minimal, conversion
 * @access     pro
 * @source     https://www.paddle.com
 * @deps
 * @version    1.0.0
 */
import type { ComponentPropsWithoutRef } from 'react';
import Badge01 from '../../elements/badge-01';
import Button01 from '../../elements/button-01';

interface PricingCard01Props extends ComponentPropsWithoutRef<'div'> {
    /** Plan name. */
    plan?: string;
    /** Price amount (number only; pair with `currency`). */
    price?: number;
    /** Currency symbol shown before the price. */
    currency?: string;
    /** Billing period label shown after the price. */
    period?: string;
    /** Short plan summary. Hidden when empty. */
    description?: string;
    /** Included features, one per list item. */
    features?: string[];
    /** Call-to-action button label. */
    ctaLabel?: string;
    /** Call-to-action link target. */
    ctaHref?: string;
    /** Highlight this card as the recommended plan. */
    featured?: boolean;
    /** Badge text on the featured card. Hidden when empty. */
    badgeLabel?: string;
}

export default function PricingCard01({
    plan = 'Starter',
    price = 9,
    currency = '$',
    period = '/mo',
    description = '',
    features = [],
    ctaLabel = 'Choose plan',
    ctaHref = '#',
    featured = false,
    badgeLabel = '',
    className = '',
    ...rest
}: PricingCard01Props) {
    return (
        <div
            {...rest}
            className={`relative flex flex-col gap-6 rounded-2xl p-8 ${
                featured
                    ? 'bg-white shadow-xl ring-2 ring-indigo-600 dark:bg-neutral-900 dark:ring-indigo-500'
                    : 'bg-white shadow-sm ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-800'
            } ${className}`}
        >
            {featured && badgeLabel !== '' && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                    <Badge01 label={badgeLabel} />
                </div>
            )}
            <div className="flex flex-col gap-2">
                <h3 className="text-base font-semibold text-neutral-900 dark:text-white">{plan}</h3>
                {description !== '' && <p className="text-sm leading-6 text-neutral-500 dark:text-neutral-400">{description}</p>}
            </div>
            <div className="flex items-baseline gap-1">
                <span className="text-4xl font-bold tracking-tight text-neutral-900 dark:text-white">
                    {currency}
                    {price}
                </span>
                <span className="text-sm font-medium text-neutral-500 dark:text-neutral-400">{period}</span>
            </div>
            <ul className="flex flex-col gap-3">
                {features.map((feature, index) => (
                    <li key={index} className="flex items-start gap-3 text-sm text-neutral-700 dark:text-neutral-300">
                        <svg
                            viewBox="0 0 20 20"
                            fill="none"
                            aria-hidden="true"
                            className="mt-0.5 size-4 shrink-0 text-indigo-600 dark:text-indigo-400"
                        >
                            <path
                                d="M4.5 10.5l3.5 3.5 7.5-8"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                            />
                        </svg>
                        {feature}
                    </li>
                ))}
            </ul>
            <div className="mt-auto pt-2">
                <Button01 label={ctaLabel} href={ctaHref} variant={featured ? 'primary' : 'secondary'} className="w-full" />
            </div>
        </div>
    );
}
