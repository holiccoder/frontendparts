---
title: Troubleshooting
description: Fixes for the common integration problems — unstyled components, broken imports, TypeScript errors, download 403s and rate limits.
---

# Troubleshooting

The failure modes of copied components are few and predictable. Find your symptom below; if none of it helps, open a [support ticket](/dashboard/tickets) (category *technical*) with the component slug and your framework.

## Components render but look unstyled

The classes are there but no CSS is generated — this is almost always Tailwind:

1. **Tailwind CSS 4 is required.** Components use Tailwind 4 utilities only. On Tailwind 3 some classes (and the whole `@theme` workflow) don't exist — upgrade first.
2. **The Tailwind entry is missing.** Vite projects need `@import "tailwindcss";` in the CSS file your app actually loads, with `@tailwindcss/vite` (or `@tailwindcss/postcss`) active. See [Install for React](/docs/install/react) or [Install for Vue](/docs/install/vue).
3. **The copied files are outside Tailwind's scan path.** Tailwind 4 auto-detects sources, but if you excluded directories in your CSS (with `@source not …`) or put components outside the project root, utilities won't be generated. Keep them under your normal `src/` tree.

## Imports don't resolve after unzipping

**Symptom:** `Cannot find module './elements/SectionTitle01'` (React) or the Vue equivalent.

The zip layout is load-bearing: imports between components are relative to the level folders. If you flattened `elements/`, `blocks/` and `sections/` into one directory, restore the structure:

```text
components/
├── elements/
├── blocks/
├── sections/
└── pages/
```

The import order rule is **elements → blocks → sections → pages** — a level never imports upward. Keep that shape and the closure compiles untouched.

## TypeScript errors on a fresh copy

- **React 19 / Vue 3 types** — sources are authored against React 19 and Vue 3 with TypeScript. Older major versions (or missing `@types/react`) produce JSX and prop-type errors.
- **Missing npm dependency** — if the closure uses an approved package, the zip README lists the exact pinned `npm install` line. Most components are zero-dep, so an import error for a package name means this line was skipped.
- **Data module shape** — sample data is `export default … as const`. If you edited it and broke the shape, re-copy the file from the zip and reapply your content on top.

## Next.js: "You're importing a component that needs useState…"

In the App Router, interactive components must render from a **client boundary**. Add `'use client';` to the top of the copied entry component — details in [Install for Next.js](/docs/install/next). Server Components can still *render* the section; the directive belongs on the client file.

## Download or export returns 403 with an upgrade prompt

That response is the plan gate working as designed: the component (or the project, if it contains paid components) isn't covered by your current plan. Check [License FAQ](/docs/license/faq) for what each plan unlocks, or grab the component's free twin content from the Code tab if one is offered. A lapsed plan triggers this even for projects you saved while subscribed — renewing restores export without re-adding anything.

## Downloads or copies return 429

Copy and download actions are **rate-limited** to keep the library scrape-resistant (downloads more strictly than copies). Normal browsing never hits the limits; if you scripted a batch export, slow down and retry after a minute. Limits reset on their own — no ticket needed.

## Dark mode variants don't apply

Components ship light-first; `dark:` utilities only respond if your project defines the dark variant. In Tailwind 4, add to your CSS entry:

```css
@custom-variant dark (&:is(.dark *));
```

Then toggle a `.dark` class on `html` or `body`. See [Customizing](/docs/using-components/customizing) for the full theming model.

## Preview looks different from my project

The catalog preview uses the component's `data.json` showcase content and the library's default font stack. In your project the component inherits **your** fonts and **your** content — differences in type metrics and text length are expected. Feed it your real copy through props or the data module before judging spacing; [Params & data](/docs/using-components/params-and-data) explains the resolution order.

## Still stuck?

Open a ticket from your [dashboard](/dashboard/tickets): choose *technical* for integration problems, *billing* for plan/order issues, *license* for usage-rights questions. Include the component slug, your framework (React/Vue + Next/Nuxt) and the exact error — that trio resolves most tickets in one round.
