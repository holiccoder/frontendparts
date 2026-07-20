---
title: Install in a Vue project
description: Add FrontendParts components to a Vue 3 + Tailwind CSS 4 project — zip layout, import order, data modules and npm dependencies.
---

# Install in a Vue project

## Requirements

- **Vue 3** with TypeScript — sources are single-file components (`.vue`) using `<script setup lang="ts">`.
- **Tailwind CSS 4** configured in your project (via `@tailwindcss/vite` or PostCSS). Components are styled with Tailwind utilities only; no other CSS ships in the zip.

## 1. Download the zip

On the component page, switch to the **Vue** implementation and hit download. You get `{name}-vue.zip`:

```text
├── components/
│   ├── elements/SectionTitle01.vue
│   └── sections/TitleShowcase01.vue
├── data/
│   └── title-showcase-01.ts
└── README.md
```

The layout is identical to the React zip — same level folders, same data modules, same README — so moving between stacks is mechanical.

## 2. Copy the files into your project

```bash
unzip title-showcase-01-vue.zip -d /tmp/fp
cp -r /tmp/fp/components/* src/components/
cp -r /tmp/fp/data/* src/data/
```

Keep the level folders (`elements/`, `blocks/`, `sections/`, `pages/`) intact. The import order rule is **elements → blocks → sections → pages**: parents import their children, and imports inside the zip are already relative to this layout.

## 3. Import and render

Sample data ships as a typed module (`export default … as const`) that you bind with `v-bind`:

```vue
<script setup lang="ts">
import TitleShowcase01 from './components/sections/TitleShowcase01.vue';
import showcaseData from './data/title-showcase-01';
</script>

<template>
    <TitleShowcase01 v-bind="showcaseData" />
</template>
```

Elements take plain props with the same names and defaults as their React twins:

```vue
<script setup lang="ts">
import SectionTitle01 from './components/elements/SectionTitle01.vue';
</script>

<template>
    <SectionTitle01
        eyebrow="Features"
        heading="Everything you need to ship faster"
        align="center"
    />
</template>
```

Prop defaults are declared with `withDefaults`, so every component renders standalone with sensible fallbacks — see [Params & data](/docs/using-components/params-and-data) for the full resolution model.

## npm dependencies

Most components are **zero-dep** — Vue plus Tailwind only. When a closure uses an approved package (icons are the common case), the zip README contains the exact pinned install line, for example:

```bash
npm install lucide-vue-next
```

The dependency registry only allows packages with an equivalent in both ecosystems (`lucide-react` on the React side), and behavior primitives are hand-rolled rather than pulled from a UI primitives package.

Next: [Params & data](/docs/using-components/params-and-data), or [install in a React project](/docs/install/react) instead.
