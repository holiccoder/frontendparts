<script setup lang="ts">
/**
 * @component  badge-01
 * @name       Badge 01
 * @level      element
 * @usage      announcement-banner
 * @industries
 * @tags       minimal, pill
 * @access     free
 * @source     https://vercel.com
 * @deps
 * @version    1.0.0
 */
interface Badge01Props {
    /** Visible badge text. */
    label?: string;
    /** Color tone. */
    tone?: 'indigo' | 'neutral' | 'success';
}

const props = withDefaults(defineProps<Badge01Props>(), {
    label: 'New',
    tone: 'indigo',
});

const TONES: Record<NonNullable<Badge01Props['tone']>, string> = {
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-400/30',
    neutral:
        'bg-neutral-100 text-neutral-600 ring-neutral-500/20 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-600/40',
    success:
        'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
};
</script>

<template>
    <span
        :class="`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${TONES[props.tone]}`"
    >
        {{ props.label }}
    </span>
</template>
