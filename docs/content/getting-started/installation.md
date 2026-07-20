---
title: Installation & account
description: Do you need an account, how Free, Starter and Pro differ, and the browsing, copying and downloading flow step by step.
---

# Installation & account

## Do you need an account?

Not to start. Browsing the catalog, opening previews and **copying or downloading free components is accountless** — no sign-up wall in front of free code.

A free account unlocks the rest of the workflow:

- downloading **paid components** (with a paid plan),
- saving components into **projects** for later export,
- a download history that doubles as your license record.

Registration takes an email and a password from the [register page](/register).

## Plans at a glance

| Plan | Library access | Projects | Scaffolding & exports |
|---|---|---|---|
| Free | Free components | 1 | — |
| Starter | Full library | 3 | — |
| Pro | Full library | Unlimited | ✓ |

Starter is the full library for one developer; Pro adds project scaffolding (Next.js / Nuxt) and GitHub repo export on top. Current prices are shown on the home page pricing section. Paid components you can preview but not yet download return an upgrade prompt instead of a zip.

## Browsing the catalog

The [catalog](/components) lists every published component. Filters are server-side and combinable:

- **Industry** — the 12 industries components were recreated from.
- **Usage** — the pattern (hero, pricing, navbar, footer…).
- **Level** — element, block, section or page.
- **Access** — free or paid.
- **Framework** — display preference only; every component ships both implementations, so this never hides results.

A usage or industry filter only appears once it has enough published components, so every filter choice leads somewhere useful.

## Copying vs downloading

Each component page has Code, Data and Docs tabs:

- **Copy** — the Code tab shows one file per component in the closure; the copy button puts that file's source on your clipboard. The Data tab does the same for the pretty-printed sample data.
- **Download** — the download button streams a zip of the **entire closure**: the component plus every child it imports, for the framework you chose.

Both actions are rate-limited (downloads more strictly than copies) to keep the library scrape-resistant; normal browsing never hits the limits.

## What the download zip looks like

Zips preserve the composition graph — one file per component, organized by level, with sample data as separate importable modules:

```text
title-showcase-01-react.zip
├── components/
│   ├── elements/SectionTitle01.tsx
│   └── sections/TitleShowcase01.tsx   ← imports SectionTitle01
├── data/
│   └── title-showcase-01.ts           ← sample data module
└── README.md                          ← file map, deps, requirements
```

The README lists every file, the exact `npm install` line for any approved npm dependencies the closure uses (most components are zero-dep), and the Tailwind requirement. Sources are the library files verbatim — preview instrumentation never touches them, and imports between components are already rewritten to the zip layout, so the zip compiles as-is.

Next: [Install for React](/docs/install/react) or [Install for Vue](/docs/install/vue).
