<script setup lang="ts">
/**
 * @component  section-title-01
 * @name       Section Title 01
 * @level      element
 * @usage      feature-grid
 * @industries
 * @tags       minimal, typography
 * @access     free
 * @source     https://tailwindcss.com
 * @deps
 * @version    1.0.0
 */
interface SectionTitle01Props {
    /** Small label displayed above the heading. Hidden when empty. */
    eyebrow?: string;
    /** Main heading text. */
    heading?: string;
    /** Supporting paragraph below the heading. Hidden when empty. */
    description?: string;
    /** Horizontal alignment of the whole block. */
    align?: 'left' | 'center';
}

const props = withDefaults(defineProps<SectionTitle01Props>(), {
    eyebrow: '',
    heading: 'Section heading',
    description: '',
    align: 'center',
});

const alignment = () =>
    props.align === 'center' ? 'mx-auto text-center items-center' : 'text-left items-start';
</script>

<template>
    <div :class="`flex max-w-2xl flex-col gap-4 px-6 py-12 ${alignment()}`">
        <span v-if="eyebrow !== ''" class="text-sm font-semibold tracking-widest text-indigo-600 uppercase">
            {{ eyebrow }}
        </span>
        <h2 class="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">{{ heading }}</h2>
        <p v-if="description !== ''" class="text-lg leading-8 text-neutral-600">{{ description }}</p>
    </div>
</template>
