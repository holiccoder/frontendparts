<script setup lang="ts">
/**
 * @component  faq-item-01
 * @name       FAQ Item 01
 * @level      block
 * @usage      faq
 * @industries
 * @tags       interactive, accordion, a11y
 * @access     pro
 * @source     https://www.notion.so
 * @deps
 * @version    1.0.0
 */
import { ref, useId } from 'vue';

interface FaqItem01Props {
    /** Question shown on the accordion trigger. */
    question?: string;
    /** Answer revealed when open. */
    answer?: string;
    /** Render open on first paint. */
    defaultOpen?: boolean;
}

const props = withDefaults(defineProps<FaqItem01Props>(), {
    question: 'How does this work?',
    answer: '',
    defaultOpen: false,
});

const open = ref(props.defaultOpen);
const id = useId();
const buttonId = `${id}-trigger`;
const panelId = `${id}-panel`;
</script>

<template>
    <div class="border-b border-neutral-200 dark:border-neutral-800">
        <button
            type="button"
            :id="buttonId"
            :aria-expanded="open"
            :aria-controls="panelId"
            class="flex w-full items-center justify-between gap-4 py-5 text-left text-base font-medium text-neutral-900 transition-colors hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-white dark:hover:text-indigo-400 dark:focus-visible:outline-indigo-400"
            @click="open = !open"
        >
            {{ props.question }}
            <svg
                viewBox="0 0 20 20"
                fill="none"
                aria-hidden="true"
                :class="`size-5 shrink-0 text-neutral-400 transition-transform duration-200 ${open ? 'rotate-180' : ''}`"
            >
                <path d="M5 7.5l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
        <div
            :class="`grid transition-all duration-200 ease-out ${open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'}`"
        >
            <div class="overflow-hidden">
                <div
                    :id="panelId"
                    role="region"
                    :aria-labelledby="buttonId"
                    :aria-hidden="!open"
                    class="pb-5 text-sm leading-6 text-neutral-600 dark:text-neutral-400"
                >
                    {{ props.answer }}
                </div>
            </div>
        </div>
    </div>
</template>
