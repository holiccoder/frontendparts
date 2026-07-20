---
title: Install in a React project
description: Add FrontendParts components to a React + Tailwind CSS 4 project — zip layout, import order, data modules and npm dependencies.
---

# Install in a React project

## Requirements

- **React** with TypeScript — sources are `.tsx` files authored against React 19.
- **Tailwind CSS 4** configured in your project (via `@tailwindcss/vite` or PostCSS). Components are styled with Tailwind utilities only; no other CSS ships in the zip.

If you scaffolded your app with Tailwind 4 already, there is nothing else to set up.

## 1. Download the zip

On the component page, switch to the **React** implementation and hit download. You get `{name}-react.zip`:

```text
├── components/
│   ├── elements/SectionTitle01.tsx
│   └── sections/TitleShowcase01.tsx
├── data/
│   └── title-showcase-01.ts
└── README.md
```

## 2. Copy the files into your project

Drop the folders into your source tree, for example:

```bash
unzip title-showcase-01-react.zip -d /tmp/fp
cp -r /tmp/fp/components/* src/components/
cp -r /tmp/fp/data/* src/data/
```

Keep the level folders (`elements/`, `blocks/`, `sections/`, `pages/`) intact — imports between components are relative to that layout. The import order rule is **elements → blocks → sections → pages**: a level never imports from a higher one, so the closure always compiles.

## 3. Import and render

Every component has a default export. Sample data ships as a typed module (`export default … as const`) that you spread onto the component:

```tsx
import TitleShowcase01 from './components/sections/TitleShowcase01';
import showcaseData from './data/title-showcase-01';

export default function MarketingPage() {
    return <TitleShowcase01 {...showcaseData} />;
}
```

Elements take plain props — pass your own content directly:

```tsx
import SectionTitle01 from './components/elements/SectionTitle01';

<SectionTitle01
    eyebrow="Features"
    heading="Everything you need to ship faster"
    align="center"
/>
```

Once the files are in your repo they are your code: edit classes, split files, rename — nothing links back to FrontendParts at runtime. How props, sample data and defaults resolve is covered in [Params & data](/docs/using-components/params-and-data).

## npm dependencies

Most components are **zero-dep** — React plus Tailwind only — and carry a zero-dep badge on their Docs tab. When a closure does use an approved package (icons are the common case), the zip README contains the exact pinned install line, for example:

```bash
npm install lucide-react
```

The FrontendParts dependency registry only allows packages that exist in both the React and Vue ecosystems, so any dep listed in the README has an equivalent twin on the Vue side. There are no behavior-primitive dependencies (no Radix-style packages) — all interaction is hand-rolled.

Next: [Params & data](/docs/using-components/params-and-data), or [install in a Vue project](/docs/install/vue) instead.
