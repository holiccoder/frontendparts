<script setup lang="ts">
/**
 * @component  hero-01
 * @name       Hero 01
 * @level      section
 * @usage      hero
 * @industries
 * @tags       minimal, centered
 * @access     free
 * @source     https://www.posthog.com
 * @deps
 * @version    1.0.0
 */
import Button01 from '../../elements/button-01/index.vue';

interface Hero01Props {
    /** Small label above the heading. Hidden when empty. */
    eyebrow?: string;
    /** Main headline. */
    heading?: string;
    /** Supporting paragraph. Hidden when empty. */
    subheading?: string;
    /** Primary button label. */
    primaryLabel?: string;
    /** Primary button link target. */
    primaryHref?: string;
    /** Secondary button label. Hidden when empty. */
    secondaryLabel?: string;
    /** Secondary button link target. */
    secondaryHref?: string;
    /** Product screenshot URL. Hidden when empty. */
    imageSrc?: string;
    /** Alt text for the screenshot. */
    imageAlt?: string;
}

const props = withDefaults(defineProps<Hero01Props>(), {
    eyebrow: '',
    heading: 'Build something great',
    subheading: '',
    primaryLabel: 'Get started',
    primaryHref: '#',
    secondaryLabel: '',
    secondaryHref: '#',
    imageSrc: '',
    imageAlt: 'Product preview',
});
</script>

<template>
    <section class="bg-white dark:bg-neutral-950">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-12 px-6 py-16 sm:py-20">
            <div class="flex max-w-3xl flex-col items-center gap-6 text-center">
                <span
                    v-if="props.eyebrow !== ''"
                    class="text-sm font-semibold tracking-widest text-indigo-600 uppercase dark:text-indigo-400"
                >
                    {{ props.eyebrow }}
                </span>
                <h1 class="text-4xl font-bold tracking-tight text-neutral-900 sm:text-5xl dark:text-white">
                    {{ props.heading }}
                </h1>
                <p v-if="props.subheading !== ''" class="text-lg leading-8 text-neutral-600 dark:text-neutral-400">
                    {{ props.subheading }}
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button01 :label="props.primaryLabel" :href="props.primaryHref" size="lg" show-arrow />
                    <Button01
                        v-if="props.secondaryLabel !== ''"
                        :label="props.secondaryLabel"
                        :href="props.secondaryHref"
                        variant="secondary"
                        size="lg"
                    />
                </div>
            </div>
            <img
                v-if="props.imageSrc !== ''"
                :src="props.imageSrc"
                :alt="props.imageAlt"
                loading="lazy"
                class="w-full max-w-5xl rounded-2xl bg-neutral-100 shadow-xl ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-800"
            />
        </div>
    </section>
</template>
