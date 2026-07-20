---
title: Install in a Next.js project
description: Add FrontendParts components to a Next.js App Router project — the React zip, Tailwind CSS 4 setup, client components and where the files go.
---

# Install in a Next.js project

FrontendParts components are plain React (TSX) sources, so they drop straight into a Next.js app. The current path is the **React zip**: download it, copy the files in, and wire up Tailwind CSS 4. A one-click Next.js scaffold export (full `app/` project, zipped) is on the Pro roadmap — see [Scaffolding & GitHub Export](/docs/exports/scaffolding-and-github).

## Requirements

- **Next.js** with the App Router — components are authored against React 19, which current Next.js releases ship.
- **Tailwind CSS 4** in the project. Components are styled with Tailwind utilities only; no other CSS ships in the zip.

If you created the app with `create-next-app` and said yes to Tailwind, you already have Tailwind 4: your `app/globals.css` starts with `@import "tailwindcss";` and `@tailwindcss/postcss` is wired in `postcss.config.mjs`. Nothing else to configure — utilities are detected automatically from your source files.

## 1. Download the React zip

On the component page, keep the **React** implementation selected and hit download. You get `{name}-react.zip`:

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

Keep the level folders (`elements/`, `blocks/`, `sections/`, `pages/`) intact — imports between components are relative to that layout, and the import order rule is **elements → blocks → sections → pages**, so the closure always compiles.

## 3. Render from a client boundary

Components use React state and effects for their interactions (carousels, toggles, sticky navs), so in the App Router they render as **client components**. Mark the entry file with the `use client` directive — child imports follow automatically:

```tsx
// src/components/sections/TitleShowcase01.tsx
'use client';

// …rest of the file unchanged
```

Then use it from any server or client component:

```tsx
// app/page.tsx
import TitleShowcase01 from '@/components/sections/TitleShowcase01';
import showcaseData from '@/data/title-showcase-01';

export default function HomePage() {
    return <TitleShowcase01 {...showcaseData} />;
}
```

Sample data ships as a typed module (`export default … as const`) that you spread onto the component — replace it field by field with your own content. How props, sample data and defaults resolve is covered in [Params & data](/docs/using-components/params-and-data).

## npm dependencies

Most components are **zero-dep** — React plus Tailwind only. When a closure uses an approved package (icons are the common case), the zip README contains the exact pinned install line, for example:

```bash
npm install lucide-react
```

The dependency registry only allows packages that exist in both the React and Vue ecosystems, and interaction primitives are hand-rolled — no Radix-style behavior dependencies.

## Notes for Next.js specifically

- **Images** — sample data points at remote image URLs so previews look finished. Swap in your own assets, or configure `images.remotePatterns` in `next.config.ts` if you wrap elements in `next/image`.
- **Fonts** — components inherit your app's font stack. Set your brand font once with `next/font` on the root layout and every copied section follows it.
- **The `pages/` level** — page-level components are complete screen layouts; in the App Router you typically lift their markup into a route's `page.tsx` rather than nesting them under another layout.

Next: [Params & data](/docs/using-components/params-and-data) · [Customizing](/docs/using-components/customizing) · [Install in a Nuxt project](/docs/install/nuxt).
