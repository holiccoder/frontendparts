<script setup lang="ts">
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
interface Avatar01Props {
    /** Image URL. Falls back to initials when empty. */
    src?: string;
    /** Alt text describing the person. */
    alt?: string;
    /** Initials shown when no image is set. */
    fallback?: string;
    /** Size preset. */
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Avatar01Props>(), {
    src: '',
    alt: 'Team member',
    fallback: 'FP',
    size: 'md',
});

const SIZES: Record<NonNullable<Avatar01Props['size']>, string> = {
    sm: 'size-8 text-xs',
    md: 'size-10 text-sm',
    lg: 'size-14 text-base',
};
</script>

<template>
    <span
        v-if="props.src === ''"
        role="img"
        :aria-label="props.alt"
        :class="`inline-flex shrink-0 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700 ring-2 ring-white dark:bg-indigo-500/20 dark:text-indigo-300 dark:ring-neutral-900 ${SIZES[props.size]}`"
    >
        {{ props.fallback }}
    </span>
    <img
        v-else
        :src="props.src"
        :alt="props.alt"
        loading="lazy"
        :class="`shrink-0 rounded-full bg-neutral-200 object-cover ring-2 ring-white dark:bg-neutral-800 dark:ring-neutral-900 ${SIZES[props.size]}`"
    />
</template>
