<script setup lang="ts">
/**
 * @component  navbar-01
 * @name       Navbar 01
 * @level      section
 * @usage      navbar
 * @industries
 * @tags       interactive, navigation, a11y
 * @access     pro
 * @source     https://www.digitalocean.com
 * @deps
 * @version    1.0.0
 */
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';
import Button01 from '../../elements/button-01/index.vue';

interface Navbar01Link {
    /** Link text. */
    label?: string;
    /** Link target. */
    href?: string;
}

interface Navbar01Props {
    /** Brand name shown next to the logo mark. */
    brand?: string;
    /** Navigation links (desktop row + mobile panel). */
    links?: Navbar01Link[];
    /** Call-to-action button label. */
    ctaLabel?: string;
    /** Call-to-action link target. */
    ctaHref?: string;
}

const props = withDefaults(defineProps<Navbar01Props>(), {
    brand: 'Acme',
    links: () => [],
    ctaLabel: 'Get started',
    ctaHref: '#',
});

const open = ref(false);
const menuId = useId();

const onKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => window.addEventListener('keydown', onKeyDown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeyDown));
</script>

<template>
    <header
        class="sticky top-0 z-10 border-b border-neutral-200 bg-white/80 backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/80"
    >
        <nav aria-label="Main" class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
            <a href="#" class="flex items-center gap-2 text-base font-bold tracking-tight text-neutral-900 dark:text-white">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="size-6 text-indigo-600 dark:text-indigo-400">
                    <path d="M12 2l10 10-10 10L2 12 12 2Z" />
                </svg>
                {{ props.brand }}
            </a>
            <div class="hidden items-center gap-8 md:flex">
                <a
                    v-for="(link, index) in props.links"
                    :key="index"
                    :href="link.href"
                    class="text-sm font-medium text-neutral-600 transition-colors hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white"
                >
                    {{ link.label }}
                </a>
            </div>
            <div class="hidden md:block">
                <Button01 :label="props.ctaLabel" :href="props.ctaHref" size="sm" />
            </div>
            <button
                type="button"
                :aria-expanded="open"
                :aria-controls="menuId"
                aria-label="Toggle navigation menu"
                class="inline-flex size-10 items-center justify-center rounded-lg text-neutral-700 transition-colors hover:bg-neutral-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 md:hidden dark:text-neutral-300 dark:hover:bg-neutral-800"
                @click="open = !open"
            >
                <svg v-if="open" viewBox="0 0 20 20" fill="none" aria-hidden="true" class="size-5">
                    <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
                <svg v-else viewBox="0 0 20 20" fill="none" aria-hidden="true" class="size-5">
                    <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </button>
        </nav>
        <div v-if="open" :id="menuId" class="border-t border-neutral-200 px-6 py-4 md:hidden dark:border-neutral-800">
            <div class="flex flex-col gap-1">
                <a
                    v-for="(link, index) in props.links"
                    :key="index"
                    :href="link.href"
                    class="rounded-lg px-3 py-2.5 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                    @click="open = false"
                >
                    {{ link.label }}
                </a>
                <div class="pt-3">
                    <Button01 :label="props.ctaLabel" :href="props.ctaHref" class="w-full" />
                </div>
            </div>
        </div>
    </header>
</template>
