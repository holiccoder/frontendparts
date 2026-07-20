<script setup lang="ts">
/**
 * @component  cta-01
 * @name       CTA 01
 * @level      section
 * @usage      cta
 * @industries
 * @tags       minimal, conversion, accent
 * @access     free
 * @source     https://www.twilio.com
 * @deps
 * @version    1.0.0
 */
import Button01 from '../../elements/button-01/index.vue';

interface Cta01Props {
    /** Main heading inside the panel. */
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
}

const props = withDefaults(defineProps<Cta01Props>(), {
    heading: 'Ready to get started?',
    subheading: '',
    primaryLabel: 'Get started',
    primaryHref: '#',
    secondaryLabel: '',
    secondaryHref: '#',
});
</script>

<template>
    <section class="bg-white dark:bg-neutral-950">
        <div class="mx-auto max-w-7xl px-6 py-16 sm:py-20">
            <div class="flex flex-col items-center gap-6 rounded-3xl bg-indigo-600 px-6 py-16 text-center dark:bg-indigo-500">
                <h2 class="max-w-2xl text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ props.heading }}</h2>
                <p v-if="props.subheading !== ''" class="max-w-xl text-lg leading-8 text-indigo-100 dark:text-indigo-50">
                    {{ props.subheading }}
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button01 :label="props.primaryLabel" :href="props.primaryHref" variant="inverted" size="lg" show-arrow />
                    <Button01
                        v-if="props.secondaryLabel !== ''"
                        :label="props.secondaryLabel"
                        :href="props.secondaryHref"
                        variant="invertedGhost"
                        size="lg"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
