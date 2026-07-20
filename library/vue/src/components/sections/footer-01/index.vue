<script setup lang="ts">
/**
 * @component  footer-01
 * @name       Footer 01
 * @level      section
 * @usage      footer
 * @industries
 * @tags       minimal, navigation
 * @access     pro
 * @source     https://www.cloudflare.com
 * @deps
 * @version    1.0.0
 */
import { computed } from 'vue';
import NewsletterForm01 from '../../blocks/newsletter-form-01/index.vue';

interface Footer01Link {
    /** Link text. */
    label?: string;
    /** Link target. */
    href?: string;
}

interface Footer01Column {
    /** Column heading. */
    title?: string;
    /** Links inside the column. */
    links?: Footer01Link[];
}

interface NewsletterFormSlice {
    placeholder?: string;
    buttonLabel?: string;
    note?: string;
}

interface Footer01Props {
    /** Brand name shown next to the logo mark. */
    brand?: string;
    /** Short tagline under the brand. */
    tagline?: string;
    /** Heading above the newsletter form. Hidden when empty. */
    newsletterHeading?: string;
    /** Link columns. */
    columns?: Footer01Column[];
    /** Copyright line in the bottom bar. */
    copyright?: string;
    /** Legal links in the bottom bar. */
    legalLinks?: Footer01Link[];
    /** Child slices keyed by child slug (library README `children` convention). */
    children?: {
        'newsletter-form-01'?: NewsletterFormSlice;
    };
}

const props = withDefaults(defineProps<Footer01Props>(), {
    brand: 'Acme',
    tagline: '',
    newsletterHeading: '',
    columns: () => [],
    copyright: '',
    legalLinks: () => [],
    children: () => ({}),
});

const newsletter = computed(() => props.children['newsletter-form-01'] ?? {});
</script>

<template>
    <footer class="border-t border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950">
        <div class="mx-auto flex max-w-7xl flex-col gap-12 px-6 py-16">
            <div class="grid gap-12 lg:grid-cols-12">
                <div class="flex flex-col gap-5 lg:col-span-5">
                    <a href="#" class="flex items-center gap-2 text-base font-bold tracking-tight text-neutral-900 dark:text-white">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-6 text-indigo-600 dark:text-indigo-400">
                            <path d="M12 2l10 10-10 10L2 12 12 2Z" />
                        </svg>
                        {{ props.brand }}
                    </a>
                    <p v-if="props.tagline !== ''" class="max-w-sm text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                        {{ props.tagline }}
                    </p>
                    <h3 v-if="props.newsletterHeading !== ''" class="pt-2 text-sm font-semibold text-neutral-900 dark:text-white">
                        {{ props.newsletterHeading }}
                    </h3>
                    <NewsletterForm01 v-bind="newsletter" />
                </div>
                <div class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-7">
                    <div v-for="(column, index) in props.columns" :key="index" class="flex flex-col gap-4">
                        <h3 class="text-sm font-semibold text-neutral-900 dark:text-white">{{ column.title }}</h3>
                        <ul class="flex flex-col gap-3">
                            <li v-for="(link, linkIndex) in column.links ?? []" :key="linkIndex">
                                <a
                                    :href="link.href"
                                    class="text-sm text-neutral-600 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                                >
                                    {{ link.label }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div
                class="flex flex-col gap-4 border-t border-neutral-200 pt-8 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800"
            >
                <p v-if="props.copyright !== ''" class="text-sm text-neutral-500 dark:text-neutral-400">{{ props.copyright }}</p>
                <div class="flex gap-6">
                    <a
                        v-for="(link, index) in props.legalLinks"
                        :key="index"
                        :href="link.href"
                        class="text-sm text-neutral-500 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                    >
                        {{ link.label }}
                    </a>
                </div>
            </div>
        </div>
    </footer>
</template>
