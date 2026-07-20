<script setup lang="ts">
/**
 * @component  pricing-01
 * @name       Pricing 01
 * @level      section
 * @usage      pricing
 * @industries saas-software, fintech-finance
 * @tags       minimal, conversion
 * @access     pro
 * @source     https://www.chargebee.com
 * @deps
 * @version    1.0.0
 */
import { computed } from 'vue';
import PricingCard01 from '../../blocks/pricing-card-01/index.vue';
import SectionTitle01 from '../../elements/section-title-01/index.vue';

interface SectionTitleSlice {
    eyebrow?: string;
    heading?: string;
    description?: string;
    align?: 'left' | 'center';
}

interface PricingCardSlice {
    plan?: string;
    price?: number;
    currency?: string;
    period?: string;
    description?: string;
    features?: string[];
    ctaLabel?: string;
    ctaHref?: string;
    featured?: boolean;
    badgeLabel?: string;
}

const props = withDefaults(
    defineProps<{
        /** Child slices keyed by child slug (library README `children` convention). */
        children?: {
            'section-title-01'?: SectionTitleSlice;
            'pricing-card-01'?: PricingCardSlice[];
        };
    }>(),
    {
        children: () => ({}),
    },
);

const title = computed(() => props.children['section-title-01'] ?? {});
const cards = computed(() => props.children['pricing-card-01'] ?? []);
const cardAt = (index: number) => cards.value[index] ?? {};
</script>

<template>
    <section class="bg-white dark:bg-neutral-950">
        <div class="mx-auto flex max-w-7xl flex-col gap-12 px-6 py-16 sm:py-20">
            <SectionTitle01 v-bind="title" />
            <div class="mx-auto grid w-full max-w-5xl items-stretch gap-8 lg:grid-cols-3">
                <PricingCard01 v-bind="cardAt(0)" />
                <PricingCard01 v-bind="cardAt(1)" />
                <PricingCard01 v-bind="cardAt(2)" />
            </div>
        </div>
    </section>
</template>
