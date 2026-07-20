<script setup lang="ts">
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
import Badge01 from '../../elements/badge-01/index.vue';
import Button01 from '../../elements/button-01/index.vue';

interface PricingCard01Props {
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

const props = withDefaults(defineProps<PricingCard01Props>(), {
    plan: 'Starter',
    price: 9,
    currency: '$',
    period: '/mo',
    description: '',
    features: () => [],
    ctaLabel: 'Choose plan',
    ctaHref: '#',
    featured: false,
    badgeLabel: '',
});
</script>

<template>
    <div
        :class="`relative flex flex-col gap-6 rounded-2xl p-8 ${
            props.featured
                ? 'bg-white shadow-xl ring-2 ring-indigo-600 dark:bg-neutral-900 dark:ring-indigo-500'
                : 'bg-white shadow-sm ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-800'
        }`"
    >
        <div v-if="props.featured && props.badgeLabel !== ''" class="absolute -top-3 left-1/2 -translate-x-1/2">
            <Badge01 :label="props.badgeLabel" />
        </div>
        <div class="flex flex-col gap-2">
            <h3 class="text-base font-semibold text-neutral-900 dark:text-white">{{ props.plan }}</h3>
            <p v-if="props.description !== ''" class="text-sm leading-6 text-neutral-500 dark:text-neutral-400">
                {{ props.description }}
            </p>
        </div>
        <div class="flex items-baseline gap-1">
            <span class="text-4xl font-bold tracking-tight text-neutral-900 dark:text-white">
                {{ props.currency }}{{ props.price }}
            </span>
            <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400">{{ props.period }}</span>
        </div>
        <ul class="flex flex-col gap-3">
            <li
                v-for="(feature, index) in props.features"
                :key="index"
                class="flex items-start gap-3 text-sm text-neutral-700 dark:text-neutral-300"
            >
                <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    aria-hidden="true"
                    class="mt-0.5 size-4 shrink-0 text-indigo-600 dark:text-indigo-400"
                >
                    <path
                        d="M4.5 10.5l3.5 3.5 7.5-8"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                {{ feature }}
            </li>
        </ul>
        <div class="mt-auto pt-2">
            <Button01
                :label="props.ctaLabel"
                :href="props.ctaHref"
                :variant="props.featured ? 'primary' : 'secondary'"
                class="w-full"
            />
        </div>
    </div>
</template>
