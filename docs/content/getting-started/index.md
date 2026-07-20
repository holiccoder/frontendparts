---
title: Getting started with FrontendParts
description: What FrontendParts is, how the component library is organized, and the fastest path from browsing to production code.
---

# Getting started with FrontendParts

FrontendParts is a library of production-ready website sections recreated from the best sites on the web. Every component ships as clean, hand-written code in **two frameworks — React (TSX) and Vue 3 (SFC)** — styled with Tailwind CSS 4 utilities only. You copy the sources into your project; there is no runtime dependency on FrontendParts itself.

## How the library is organized

Components come in four levels, each building on the one below:

| Level | What it is | Examples |
|---|---|---|
| Element | The smallest reusable unit | Buttons, badges, section titles |
| Block | A small composition of elements | Pricing cards, testimonial cards |
| Section | A full page section | Heroes, pricing sections, FAQ |
| Page | A complete page layout | Landing pages, pricing pages |

The import order is always **elements → blocks → sections → pages**: parents import their children, never the other way around. When you download a section you get its full dependency closure — every block and element it uses — already wired in the right order.

## Every component, twice

The same slug exists once in the React library and once in the Vue library: same markup structure, same Tailwind classes, same params and sample data. Pick whichever stack your project uses — you never port a twin by hand. On every component page you can switch between the React and Vue implementations before you copy or download.

## The fastest path

1. **Browse the [catalog](/components)** by usage pattern (hero, pricing, navbar…) or by [industry](/industries).
2. **Open a component** for the live preview at phone, tablet and desktop widths, the structure tree of its composition, and the Code / Data / Docs tabs.
3. **Copy or download.** Free components need no account — copy a file straight from the Code tab, or download a zip of the whole closure with sample-data modules.
4. **Paste into your project** and feed it your own content through props. See [Install for React](/docs/install/react) or [Install for Vue](/docs/install/vue).

## Params and data in one minute

Every component is driven by two files: a `params.json` contract (name, type, default and description per prop) and a `data.json` showcase file with realistic sample content. Values resolve in a strict order — **props you pass → `data.json` → param defaults** — so a component always renders on its own and you can always override anything. The full model is documented in [Params & data](/docs/using-components/params-and-data).

## What to read next

- [Installation & account](/docs/getting-started/installation) — plans, and the browsing/downloading flow in detail
- [Install for React](/docs/install/react) · [Install for Vue](/docs/install/vue) · [Next.js](/docs/install/next) · [Nuxt](/docs/install/nuxt)
- [Params & data](/docs/using-components/params-and-data) · [Customizing](/docs/using-components/customizing)
- [Scaffolding & GitHub Export](/docs/exports/scaffolding-and-github) · [License FAQ](/docs/license/faq) · [Troubleshooting](/docs/troubleshooting/index)
