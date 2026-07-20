<script setup lang="ts">
/**
 * @component  title-showcase-01
 * @name       Title Showcase 01
 * @level      section
 * @usage      feature-grid
 * @industries
 * @tags       minimal, typography
 * @access     free
 * @source     https://tailwindcss.com
 * @deps
 * @version    1.0.0
 */
import { computed } from 'vue';
import SectionTitle01 from '../../elements/section-title-01/index.vue';

interface SectionTitleSlice {
    eyebrow?: string;
    heading?: string;
    description?: string;
    align?: 'left' | 'center';
}

const props = withDefaults(
    defineProps<{
        /** Child slices keyed by child slug (library README `children` convention). */
        children?: {
            'section-title-01'?: SectionTitleSlice[];
        };
    }>(),
    {
        children: () => ({}),
    },
);

const titles = computed(() => props.children['section-title-01'] ?? []);
const first = computed(() => titles.value[0] ?? {});
const second = computed(() => titles.value[1] ?? {});
</script>

<template>
    <div class="flex flex-col gap-8 px-6 py-16 sm:gap-12">
        <SectionTitle01 v-bind="first" />
        <SectionTitle01 v-bind="second" />
    </div>
</template>
