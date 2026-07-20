<script setup lang="ts">
/**
 * @component  feature-grid-01
 * @name       Feature Grid 01
 * @level      section
 * @usage      feature-grid
 * @industries
 * @tags       minimal, icons
 * @access     free
 * @source     https://cal.com
 * @deps       lucide
 * @version    1.0.0
 */
import { computed } from 'vue';
import FeatureCard01 from '../../blocks/feature-card-01/index.vue';
import SectionTitle01 from '../../elements/section-title-01/index.vue';

interface SectionTitleSlice {
    eyebrow?: string;
    heading?: string;
    description?: string;
    align?: 'left' | 'center';
}

interface FeatureCardSlice {
    icon?: string;
    title?: string;
    description?: string;
}

const props = withDefaults(
    defineProps<{
        /** Child slices keyed by child slug (library README `children` convention). */
        children?: {
            'section-title-01'?: SectionTitleSlice;
            'feature-card-01'?: FeatureCardSlice[];
        };
    }>(),
    {
        children: () => ({}),
    },
);

const title = computed(() => props.children['section-title-01'] ?? {});
const cards = computed(() => props.children['feature-card-01'] ?? []);
const cardAt = (index: number) => cards.value[index] ?? {};
</script>

<template>
    <section class="bg-white dark:bg-neutral-950">
        <div class="mx-auto flex max-w-7xl flex-col gap-12 px-6 py-16 sm:py-20">
            <SectionTitle01 v-bind="title" />
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <FeatureCard01 v-bind="cardAt(0)" />
                <FeatureCard01 v-bind="cardAt(1)" />
                <FeatureCard01 v-bind="cardAt(2)" />
                <FeatureCard01 v-bind="cardAt(3)" />
                <FeatureCard01 v-bind="cardAt(4)" />
                <FeatureCard01 v-bind="cardAt(5)" />
            </div>
        </div>
    </section>
</template>
