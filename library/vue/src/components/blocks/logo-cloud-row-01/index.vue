<script setup lang="ts">
/**
 * @component  logo-cloud-row-01
 * @name       Logo Cloud Row 01
 * @level      block
 * @usage      logo-cloud
 * @industries
 * @tags       minimal, social-proof
 * @access     free
 * @source     https://www.resend.com
 * @deps
 * @version    1.0.0
 */
interface LogoCloudRow01Logo {
    /** Wordmark text. */
    name?: string;
}

interface LogoCloudRow01Props {
    /** Small caption above the row. Hidden when empty. */
    caption?: string;
    /** Wordmarks to display. */
    logos?: LogoCloudRow01Logo[];
}

const props = withDefaults(defineProps<LogoCloudRow01Props>(), {
    caption: '',
    logos: () => [],
});

const WORDMARK_STYLES = [
    'text-lg font-bold tracking-tight',
    'font-serif text-lg italic',
    'text-sm font-semibold tracking-widest uppercase',
    'text-lg font-black tracking-tight',
    'text-sm font-light tracking-[0.25em] uppercase',
    'text-lg font-bold italic',
];
</script>

<template>
    <div class="flex flex-col gap-6">
        <p v-if="props.caption !== ''" class="text-center text-sm font-medium text-neutral-500 dark:text-neutral-400">
            {{ props.caption }}
        </p>
        <div class="flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
            <span
                v-for="(logo, index) in props.logos"
                :key="index"
                class="flex items-center gap-2 text-neutral-400 transition-colors hover:text-neutral-600 dark:text-neutral-500 dark:hover:text-neutral-300"
            >
                <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="size-5 shrink-0">
                    <path
                        v-if="index % 6 === 0"
                        fill-rule="evenodd"
                        d="M10 17a7 7 0 1 0 0-14 7 7 0 0 0 0 14Zm0-4a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                    />
                    <path v-else-if="index % 6 === 1" d="M10 3l7 14H3L10 3Z" />
                    <rect v-else-if="index % 6 === 2" x="3" y="3" width="14" height="14" rx="3" />
                    <path v-else-if="index % 6 === 3" d="M10 2l8 8-8 8-8-8 8-8Z" />
                    <path v-else-if="index % 6 === 4" d="M3 17a7 7 0 0 1 14 0H3Z" />
                    <path v-else d="M11 2 4 11h5l-1 7 7-9h-5l1-7Z" />
                </svg>
                <span :class="WORDMARK_STYLES[index % WORDMARK_STYLES.length]">{{ logo.name }}</span>
            </span>
        </div>
    </div>
</template>
