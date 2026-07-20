<script setup lang="ts">
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
import { computed } from 'vue';

interface RatingStars01Props {
    /** Rating value from 0 to 5; fractional values fill stars partially. */
    rating?: number;
    /** Show the numeric value next to the stars. */
    showValue?: boolean;
}

const props = withDefaults(defineProps<RatingStars01Props>(), {
    rating: 5,
    showValue: false,
});

const clamped = computed(() => Math.max(0, Math.min(5, props.rating)));

const fills = computed(() => [0, 1, 2, 3, 4].map((index) => Math.max(0, Math.min(1, clamped.value - index)) * 100));
</script>

<template>
    <div role="img" :aria-label="`Rated ${clamped} out of 5 stars`" class="inline-flex items-center gap-2">
        <span class="inline-flex items-center gap-0.5">
            <span v-for="(fill, index) in fills" :key="index" class="relative inline-flex size-5">
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5 text-neutral-300 dark:text-neutral-700">
                    <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5z" />
                </svg>
                <span class="absolute inset-0 overflow-hidden" :style="{ width: `${fill}%` }">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5 text-amber-400">
                        <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5z" />
                    </svg>
                </span>
            </span>
        </span>
        <span v-if="props.showValue" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">
            {{ clamped.toFixed(1) }}
        </span>
    </div>
</template>
