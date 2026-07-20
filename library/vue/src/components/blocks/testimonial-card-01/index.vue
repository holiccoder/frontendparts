<script setup lang="ts">
/**
 * @component  testimonial-card-01
 * @name       Testimonial Card 01
 * @level      block
 * @usage      testimonial
 * @industries
 * @tags       minimal, social-proof
 * @access     free
 * @source     https://www.supabase.com
 * @deps
 * @version    1.0.0
 */
import { computed } from 'vue';
import Avatar01 from '../../elements/avatar-01/index.vue';
import RatingStars01 from '../../elements/rating-stars-01/index.vue';

interface TestimonialCard01Props {
    /** The quoted testimonial text. */
    quote?: string;
    /** Person's full name. */
    name?: string;
    /** Role and company, e.g. "Head of Product, Driftly". */
    role?: string;
    /** Star rating from 0 to 5. */
    rating?: number;
    /** Portrait image URL. Falls back to initials when empty. */
    avatarSrc?: string;
    /** Alt text for the portrait. */
    avatarAlt?: string;
    /** Initials shown when no portrait is set. */
    avatarFallback?: string;
}

const props = withDefaults(defineProps<TestimonialCard01Props>(), {
    quote: 'This changed how our team ships.',
    name: 'Alex Rivera',
    role: 'Engineering Lead',
    rating: 5,
    avatarSrc: '',
    avatarAlt: '',
    avatarFallback: 'AR',
});

const altText = computed(() => (props.avatarAlt !== '' ? props.avatarAlt : `Portrait of ${props.name}`));
</script>

<template>
    <figure
        class="flex flex-col gap-5 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8 dark:border-neutral-800 dark:bg-neutral-900"
    >
        <RatingStars01 :rating="props.rating" />
        <blockquote class="text-base leading-7 text-neutral-700 dark:text-neutral-300">
            &ldquo;{{ props.quote }}&rdquo;
        </blockquote>
        <figcaption class="mt-auto flex items-center gap-3">
            <Avatar01 :src="props.avatarSrc" :alt="altText" :fallback="props.avatarFallback" />
            <div class="flex flex-col">
                <span class="text-sm font-semibold text-neutral-900 dark:text-white">{{ props.name }}</span>
                <span class="text-sm text-neutral-500 dark:text-neutral-400">{{ props.role }}</span>
            </div>
        </figcaption>
    </figure>
</template>
