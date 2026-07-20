<script setup lang="ts">
/**
 * @component  testimonials-01
 * @name       Testimonials 01
 * @level      section
 * @usage      testimonial
 * @industries
 * @tags       minimal, social-proof
 * @access     free
 * @source     https://www.gumroad.com
 * @deps
 * @version    1.0.0
 */
import { computed } from 'vue';
import SectionTitle01 from '../../elements/section-title-01/index.vue';
import TestimonialCard01 from '../../blocks/testimonial-card-01/index.vue';

interface SectionTitleSlice {
    eyebrow?: string;
    heading?: string;
    description?: string;
    align?: 'left' | 'center';
}

interface TestimonialCardSlice {
    quote?: string;
    name?: string;
    role?: string;
    rating?: number;
    avatarSrc?: string;
    avatarAlt?: string;
    avatarFallback?: string;
}

const props = withDefaults(
    defineProps<{
        /** Child slices keyed by child slug (library README `children` convention). */
        children?: {
            'section-title-01'?: SectionTitleSlice;
            'testimonial-card-01'?: TestimonialCardSlice[];
        };
    }>(),
    {
        children: () => ({}),
    },
);

const title = computed(() => props.children['section-title-01'] ?? {});
const cards = computed(() => props.children['testimonial-card-01'] ?? []);
const cardAt = (index: number) => cards.value[index] ?? {};
</script>

<template>
    <section class="bg-neutral-50 dark:bg-neutral-900">
        <div class="mx-auto flex max-w-7xl flex-col gap-12 px-6 py-16 sm:py-20">
            <SectionTitle01 v-bind="title" />
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <TestimonialCard01 v-bind="cardAt(0)" />
                <TestimonialCard01 v-bind="cardAt(1)" />
                <TestimonialCard01 v-bind="cardAt(2)" />
            </div>
        </div>
    </section>
</template>
