---
title: Install in a Nuxt project
description: Add FrontendParts components to a Nuxt project — the Vue zip, Tailwind CSS 4 via the Vite plugin, auto-imports and where the files go.
---

# Install in a Nuxt project

FrontendParts components ship as Vue 3 single-file components, so they drop straight into a Nuxt app. The current path is the **Vue zip**: download it, copy the files in, and wire up Tailwind CSS 4. A one-click Nuxt scaffold export (full project with pages assembled, zipped) is on the Pro roadmap — see [Scaffolding & GitHub Export](/docs/exports/scaffolding-and-github).

## Requirements

- **Nuxt** (current releases, Vue 3 + TypeScript) — sources are `.vue` files using `<script setup lang="ts">`.
- **Tailwind CSS 4** in the project. Components are styled with Tailwind utilities only; no other CSS ships in the zip.

With Nuxt's Vite builder, Tailwind 4 is two lines — add the Vite plugin and one CSS entry:

```ts
// nuxt.config.ts
import tailwindcss from '@tailwindcss/vite';

export default defineNuxtConfig({
    css: ['~/assets/css/main.css'],
    vite: {
        plugins: [tailwindcss()],
    },
});
```

```css
/* assets/css/main.css */
@import 'tailwindcss';
```

Utilities are detected automatically from your `components/` and `pages/` folders — no content configuration to maintain.

## 1. Download the Vue zip

On the component page, switch to the **Vue** implementation and hit download. You get `{name}-vue.zip`:

```text
├── components/
│   ├── elements/SectionTitle01.vue
│   └── sections/TitleShowcase01.vue
├── data/
│   └── title-showcase-01.ts
└── README.md
```

## 2. Copy the files into your project

```bash
unzip title-showcase-01-vue.zip -d /tmp/fp
cp -r /tmp/fp/components/* components/
cp -r /tmp/fp/data/* data/
```

Keep the level folders (`elements/`, `blocks/`, `sections/`, `pages/`) intact. Imports between components are explicit relative imports (not Nuxt auto-imports), so they resolve exactly as copied — the import order rule is **elements → blocks → sections → pages** and the closure always compiles.

## 3. Render it

Sample data ships as a typed module (`export default … as const`) that you bind with `v-bind`:

```vue
<!-- pages/index.vue -->
<script setup lang="ts">
import TitleShowcase01 from '../components/sections/TitleShowcase01.vue';
import showcaseData from '../data/title-showcase-01';
</script>

<template>
    <TitleShowcase01 v-bind="showcaseData" />
</template>
```

Elements take plain props with the same names and defaults as their React twins, declared with `withDefaults` — every component renders standalone with sensible fallbacks. The full model is in [Params & data](/docs/using-components/params-and-data).

## npm dependencies

Most components are **zero-dep** — Vue plus Tailwind only. When a closure uses an approved package (icons are the common case), the zip README contains the exact pinned install line, for example:

```bash
npm install lucide-vue-next
```

The dependency registry only allows packages with an equivalent in both ecosystems (`lucide-react` on the React side), and behavior primitives are hand-rolled rather than pulled from a UI primitives package.

## Notes for Nuxt specifically

- **Images** — sample data points at remote image URLs so previews look finished. Swap in your own assets from `public/` when you go to production.
- **Fonts** — components inherit your font stack. Set your brand font once (e.g. via `@nuxt/fonts`) on the body and every copied section follows it.
- **SSR** — components render fine on the server; their interactions hydrate on the client like any other Vue component. No `<ClientOnly>` wrapper needed.

Next: [Params & data](/docs/using-components/params-and-data) · [Customizing](/docs/using-components/customizing) · [Install in a Next.js project](/docs/install/next).
