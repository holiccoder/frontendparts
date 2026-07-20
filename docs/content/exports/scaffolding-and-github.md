---
title: Scaffolding & GitHub Export
description: From saved project to runnable code — the pack zip export that ships today, and the Next.js / Nuxt scaffolding and GitHub repo export coming to Pro.
---

# Scaffolding & GitHub Export

A **project** is a saved set of components you assemble while browsing — the pack cart. Export turns that set into code you can run. One export path ships today; two more are on the Pro roadmap.

## Today: pack zip export

From any project in your [dashboard](/dashboard/projects), choose **React** or **Vue** and hit export. The zip builds in the background and the download link appears on the project page when it's ready. It contains the project's full component closure, organized exactly like a single-component download but merged:

```text
├── components/
│   ├── elements/…
│   ├── blocks/…
│   └── sections/…
├── data/                  ← one sample-data module per component
├── package.json           ← merged dependency snippet (pinned versions)
├── TAILWIND.md            ← Tailwind CSS 4 setup notes
└── README.md              ← file map + install command
```

Details that matter:

- **The closure is always complete.** Adding a composite to a project auto-adds every element and block it imports, deduplicated — so the zip compiles as-is, with imports already rewritten to the zip layout and ordered elements → blocks → sections → pages.
- **One framework per export.** React/Vue is chosen at export time and only that framework's sources are included. Export the same project twice to get both stacks.
- **Dependencies are merged.** `package.json` carries the closure's npm deps as one pinned, deduped `dependencies` map — merge it into your app or run the single install command from the README. Most packs are zero-dep.
- **Plan gating applies at export time.** A project containing paid components needs a plan that covers the full library when you export — if your plan lapses after you saved the project, the export asks you to upgrade instead of silently dropping files.

Free plans include one project, Starter three, Pro unlimited — so you can keep a pack per site you're building.

## Coming to Pro: Next.js / Nuxt scaffolding

The next export target is a **runnable project scaffold**, not just a components folder:

- **Next.js** — App Router, TypeScript, React 19 + Tailwind 4.
- **Nuxt** — TypeScript, Vue 3 + Tailwind 4.

Page-level components become routes; loose sections you selected are assembled into the index page in selection order. The scaffold ships with `package.json`, `tsconfig`, configs and `.gitignore`, built server-side and delivered as a zip — clone, `npm install`, run. Scaffolding is a **Pro** feature; pack zips stay available on every plan.

## Coming to Pro: GitHub repo export

The same scaffold, pushed straight to your GitHub account instead of a zip: connect GitHub (OAuth), pick a project, name the repo (public or private), and FrontendParts creates it with all files in a single commit. Your token is stored encrypted and only ever used to create the repos you ask for. Also **Pro**.

Until those land, the pack zip above plus [Install for Next.js](/docs/install/next) or [Install for Nuxt](/docs/install/nuxt) gets you the same result in a couple of minutes.

Next: [License FAQ](/docs/license/faq) — what you're allowed to do with exported code.
