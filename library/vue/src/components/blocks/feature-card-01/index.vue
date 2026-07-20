<script setup lang="ts">
/**
 * @component  feature-card-01
 * @name       Feature Card 01
 * @level      block
 * @usage      feature-grid
 * @industries
 * @tags       minimal, icons
 * @access     free
 * @source     https://www.raycast.com
 * @deps       lucide
 * @version    1.0.0
 */
import { Blocks, ChartBar, Cloud, Gauge, Globe, Layers, Lock, Rocket, ShieldCheck, Sparkles, Users, Zap } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Component } from 'vue';

interface FeatureCard01Props {
    /** Lucide icon name: Rocket, Zap, ShieldCheck, ChartBar, Globe, Users, Gauge, Layers, Lock, Cloud, Blocks or Sparkles. */
    icon?: string;
    /** Feature title. */
    title?: string;
    /** Supporting description. Hidden when empty. */
    description?: string;
}

const props = withDefaults(defineProps<FeatureCard01Props>(), {
    icon: 'Rocket',
    title: 'Feature title',
    description: '',
});

const ICONS: Record<string, Component> = {
    Blocks,
    ChartBar,
    Cloud,
    Gauge,
    Globe,
    Layers,
    Lock,
    Rocket,
    ShieldCheck,
    Sparkles,
    Users,
    Zap,
};

const iconComponent = computed(() => ICONS[props.icon] ?? Sparkles);
</script>

<template>
    <div
        class="flex flex-col gap-4 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md dark:border-neutral-800 dark:bg-neutral-900"
    >
        <div
            class="flex size-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400"
        >
            <component :is="iconComponent" class="size-5" />
        </div>
        <div class="flex flex-col gap-2">
            <h3 class="text-base font-semibold text-neutral-900 dark:text-white">{{ props.title }}</h3>
            <p v-if="props.description !== ''" class="text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                {{ props.description }}
            </p>
        </div>
    </div>
</template>
