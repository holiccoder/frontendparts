# FrontendParts — Product Requirements Document

| | |
|---|---|
| **Version** | 1.0 (draft for review) |
| **Date** | 2026-07-19 |
| **Status** | Awaiting owner approval |
| **Source of truth** | Derived from [docs/SPEC.md](./SPEC.md) (decision record); SPEC governs where they differ |

---

## 1. Purpose & Background

### 1.1 Problem

Frontend developers repeatedly rebuild the same UI sections (heroes, pricing tables, footers) from scratch. Existing component libraries are either single-framework, unstyled/headless, generic rather than industry-specific, or locked to one purchase model. Developers who work in **both React and Vue** have no single curated source.

### 1.2 Product

FrontendParts is a curated component library where every component is **recreated from real-world live websites** (with attribution), shipped in **both React and Vue**, styled exclusively with **Tailwind CSS**. Users browse with a Codepen-style preview modal, copy or download components individually or as packs, and can generate a complete **Next.js or Nuxt starter project** — delivered as a zip or pushed directly to a GitHub repository.

### 1.3 Differentiators

1. Dual-framework: every component in React + Vue with visual parity
2. Industry-based curation cited from real live sites (12 industries × 32 usage patterns)
3. Composition-first: sections assembled from independently usable child components
4. Pack download + full project scaffolding (zip or GitHub repo)

### 1.4 Market context

Tailwind Plus (~$299 lifetime), Preline Pro (lifetime), Flowbite (freemium), HyperUI (free), shadcn/ui (free, React-only). Lifetime pricing is the market's anchor model; subscriptions are secondary. FrontendParts offers both.

---

## 2. Goals & Success Metrics

| Goal | Metric | Target (approved 🔒) |
|---|---|---|
| Acquire organic traffic | Indexed component pages; organic sessions/mo | 10k/mo by month 6 |
| Convert visitors to accounts | Signup conversion from catalog | 5% of previewers |
| Convert accounts to paid | Free → paid conversion | 3–5% within 90 days |
| Retain subscribers | Monthly churn | < 5% |
| Catalog velocity | New published components/mo | ≥ 20 |
| Revenue | MRR + lifetime sales | $2k/mo by month 6 🟡 |

Targets approved by owner 2026-07-19; admin-editable as goal tracking in Filament (SPEC §8.7) and revisable after launch baselines are known.

---

## 3. Target Users & Personas

1. **Indie developer / solo founder** — builds MVPs fast, works in React *or* Vue, price-sensitive; buys Starter yearly or lifetime early.
2. **Freelancer / small agency** — builds client sites across industries; values industry categorization, pack download, and scaffolding; buys Pro.
3. **Product team developer** — needs consistent, documented, license-clean components; later served by the v2 team tier.
4. **Owner (admin)** — authors components, reviews QA, manages users/orders/content via Filament.

---

## 4. Scope

### 4.1 In scope (this document)

- Public catalog with industry × usage taxonomy and citation display
- Component preview modal (prebuilt previews; live-edit at P2)
- Composition system (4 levels, derived graph, structure tree with outline)
- User accounts, projects, pack zip export
- Next.js/Nuxt scaffolding + GitHub repo export (P2)
- Free / Starter / Pro plans × monthly/quarterly/yearly/lifetime via Paddle
- Blog, documentation, support ticketing
- File-based authoring pipeline (`library:sync`) + Filament admin & dashboard

### 4.2 Out of scope (explicit non-goals)

- npm-installable component package (components are copy/download, not a dependency)
- Figma/design-tool exports (possible future pro feature)
- Team/organization seats (v2)
- Community component submissions (P3 consideration)
- CLI installer (`npx frontendparts add …`) (P3 consideration)
- Angular/Svelte/plain-HTML component versions
- Live-edit mode for launch (P2)

---

## 5. Core User Journeys

**J1 — Browse & copy (free):** visitor lands on a component page (SEO) → opens preview modal → switches viewports → inspects structure tree → copies code → (optional) signs up.

**J2 — Pack download:** signed-in user creates a project → adds components (children auto-added) → exports zip → drops into an existing project.

**J3 — New project scaffold:** Pro user selects components/page components → chooses Next.js or Nuxt → downloads zip **or** connects GitHub → repo created with full starter.

**J4 — Upgrade:** free user hits a gated component (blurred code/data) → pricing page → Paddle checkout (plan × period) → immediate access.

**J5 — Author & publish (admin):** author writes component + `params.json` + `data.json` in `library/react` and `library/vue` → standalone preview check → `library:sync` → validation + preview build + screenshots → QA at 3 viewports → publish.

**J6 — Support:** customer opens ticket (billing/technical/license/takedown) from dashboard → admin replies in Filament with order context → email notifications both ways.

---

## 6. Functional Requirements

### FR-1 Catalog & Discovery

- FR-1.1 Public SSR pages: home, industry index, usage index, component pages at `/components/{usage}/{slug}`.
- FR-1.2 Filter catalog by industry (multi), usage (single hierarchy), level (element/block/section/page), framework, access (free/pro).
- FR-1.3 Free-text search over name/tags/categories (DB-driven at launch; Meilisearch at P3).
- FR-1.4 Component cards show thumbnail (auto-generated screenshot), name, level badge, access badge.
- FR-1.5 Empty categories hidden until ≥ 3 components (§ SPEC 4.3).
- FR-1.6 Every component page displays its citation: "layout reference: {site}".

### FR-2 Preview Modal

- FR-2.1 Opens from any component card; deep-linkable via share URL.
- FR-2.2 **Header:** name, level badge, usage + industry tags, citation link, access badge, actions (Add to Project, Download, Share, Close).
- FR-2.3 **Toolbar:** viewport presets 375/768/1280/full + live width readout + drag-resize; React|Vue toggle (synced across preview, code, download); dark/light toggle 🟡; structure panel toggle.
- FR-2.4 **Tabs:** Preview | Code | Data | Docs | Edit (Edit ships P2).
- FR-2.5 Code tab: per-file tabs (composites have one file per component), syntax highlighting, per-file copy.
- FR-2.6 Data tab: sample JSON, pretty-printed, copyable.
- FR-2.7 Docs tab: code-logic explanation, usage scenario, npm dependency list, auto-generated props table (from `params.json`), version/changelog.
- FR-2.8 Layout is user-editable: stage/content panes swappable left/right with drag-handle sizing; persisted to `localStorage` (anonymous) and account profile (logged-in). Header/tab bar fixed.
- FR-2.9 Pro gating: locked components show Preview + Docs; Code/Data blurred with upgrade CTA.
- FR-2.10 Keyboard: `1–4` viewports, `r/v` framework, `esc` close; focus-trapped, aria-labelled.
- FR-2.11 Preview is a sandboxed iframe (`allow-scripts`, no `allow-same-origin`) loading a prebuilt static HTML artifact; height auto-reports; lazy-loaded.

### FR-3 Composition & Structure Tree

- FR-3.1 Components compose across 4 levels; the graph is derived from source imports at sync time (never hand-declared); max depth 10; cycles rejected at sync.
- FR-3.2 Structure tree in the Preview tab: foldable/unfoldable nodes, level badges, type grouping with instance chips (`Card ×3 → #1 #2 #3`); default expanded to depth 2.
- FR-3.3 Hover type → all instances outlined (soft); hover instance → that instance outlined (strong). One-way only (panel → iframe via `postMessage`).
- FR-3.4 Click name → navigate to child's component page; click instance chip → preview scrolls to it; click pins highlight 🟡.
- FR-3.5 Primitives show empty state ("self-contained element").
- FR-3.6 Downloads always include the full transitive child closure as separate files (elements/blocks/sections folders + `data/`), with `data-*` instrumentation stripped.

### FR-4 Projects & Pack Export

- FR-4.1 Authenticated users create projects (limits per plan 🔒, admin-configurable via Filament settings: Free 1 · Starter 3 · Pro unlimited).
- FR-4.2 Adding a composite auto-adds its descendant closure (`is_dependency`); shared children deduplicated.
- FR-4.3 Removing a direct component prunes orphaned dependencies with user notice; dependencies in use elsewhere are kept.
- FR-4.4 Projects are framework-agnostic; React/Vue and Next/Nuxt chosen at export time.
- FR-4.5 Pack zip = component files by level + `data/` + merged dependency manifest + Tailwind setup notes + README.
- FR-4.6 Free users add only free components; Starter/Pro add any.

### FR-5 Scaffolding & GitHub Export (P2)

- FR-5.1 Pro feature. Generates a ready-to-run **Next.js** (App Router, TS, React 19, Tailwind 4) or **Nuxt** (TS, Vue 3, Tailwind 4) starter.
- FR-5.2 Starter contains: `components/`, `pages/` or `app/`, `public/`, `data/`, `package.json` (merged deps), `tsconfig`, framework configs, `.gitignore`.
- FR-5.3 Page-level components become routes; loose sections assemble into the index page in selection order.
- FR-5.4 Sample images remain remote URLs.
- FR-5.5 Delivery: queued server-side assembly → zip download, **or** GitHub export via Socialite OAuth (`repo` scope, encrypted token) creating a repo with a single-commit file tree.

### FR-6 Live Edit Mode (P2)

- FR-6.1 "Edit" tab lazy-loads an in-browser compiler (React: esbuild-wasm bundler; Vue: `@vue/repl`) + `@tailwindcss/browser`.
- FR-6.2 Multi-file editing (parent + children), JSON data editor with instant re-render.
- FR-6.3 Client-side instrumentation keeps structure-tree outlines working (fallback documented: v1 without outlines).
- FR-6.4 Download of edits is instant; Save-to-Project creates a fork and queues a background preview rebuild with progress UI.
- FR-6.5 Desktop-gated with notice on small screens.

### FR-7 Accounts, Plans & Checkout

- FR-7.1 Email/password auth (existing starter kit flows) + email verification.
- FR-7.2 Plans: Free / Starter / Pro × Monthly / Quarterly / Yearly / Lifetime (§ SPEC 7).
- FR-7.3 Starter = full library; Pro = library + scaffolding + early access + future pro features.
- FR-7.4 Checkout via Paddle (Cashier Paddle); webhooks maintain `orders` state (active / cancelled-valid-until-ends_at / expired); lifetime orders have `ends_at = null`.
- FR-7.5 `plan_prices` table maps plan × period → Paddle price ID + amount; repricing needs no deploy.
- FR-7.6 Free subset (20–30%) via `components.access_level`.
- FR-7.7 Dashboard (CSR): profile, password, appearance, my projects, my downloads, my license/orders, connected GitHub account, my tickets, email notification preferences (FR-13.4).
- FR-7.8 Refunds: 14-day window 🔒 (admin-configurable), handled via the original payment provider's API.
- FR-7.9 **Domestic payments (P2):** mainland buyers pay in CNY via **Alipay / WeChat Pay** (QR scan on desktop, app wake-up on mobile) through `yansongda/pay`; international buyers stay on Paddle. Region/currency routing at checkout; both backends normalize into the same order state machine (SPEC §7.5).
- FR-7.10 Domestic subscriptions are **one-time payment per period** with renewal-reminder emails — no auto-deduct at launch; lifetime plans fully supported.
- FR-7.11 Domestic payments require a registered **个体工商户** entity and an **ICP-filed domain** (owner-approved; paperwork workstream starts in P0, 4–8 weeks lead time).
- FR-7.12 CNY revenue normalized into dashboard MRR at an admin-configurable FX rate.

### FR-8 Blog

- FR-8.1 SSR index + article pages; categories/tags; cover; reading time; TOC.
- FR-8.2 Related posts **and related components** (cross-linking).
- FR-8.3 Article structured data, RSS, sitemap inclusion, scheduled publishing.
- FR-8.4 Admin: extends existing Blog Filament resource (categories/tags, SEO meta, related-components pivot).

### FR-9 Documentation

- FR-9.1 File-based markdown in `docs/content/`, rendered SSR at `/docs/{section}/{page}`.
- FR-9.2 Sidebar nav tree, per-page TOC, prev/next, copyable code blocks, basic search.
- FR-9.3 Launch sections: Getting Started · Install per framework · Using Components (params/data) · Customizing (Tailwind) · Scaffolding & GitHub Export · License FAQ · Troubleshooting.

### FR-10 Support Ticketing

- FR-10.1 Users create tickets (subject, category: billing/technical/license/takedown/other, body) in dashboard; threaded replies.
- FR-10.2 Admin Filament inbox: status/category filters, reply UI, order context on billing tickets.
- FR-10.3 Email notifications both directions.
- FR-10.4 Status flow: open → pending → resolved → closed. `takedown` category feeds §9 legal process.

### FR-11 Authoring Pipeline (Admin)

- FR-11.1 Components authored as files in `library/react` + `library/vue` (standalone Vite apps in-repo): source + annotation block + `params.json` + `data.json`.
- FR-11.2 Annotation fields: `@component @name @level @usage @industries @tags @access @source @deps @version`.
- FR-11.3 `library:sync` validates: twin exists in both frameworks, taxonomy valid, JSON valid, no cycles, depth ≤ 10, data slices match child schemas, defaults-only render smoke test.
- FR-11.4 Successful sync upserts DB records and queues preview builds (instrumented HTML + 3-viewport screenshots).
- FR-11.5 Standalone preview at `/preview/{slug}` in each library app, used by authors and the build pipeline.
- FR-11.6 Publishing workflow: draft → in_review → published; QA checklist per component (3 viewports, React/Vue parity, data separated, license-clean).

### FR-12 Admin Dashboard & Platform Settings

Per SPEC §8.6: KPI cards, revenue + plan-mix charts, growth charts, action tables (latest orders, drafts queue with inline publish), top components, coverage matrix heatmap, system health (failed builds, last sync, failed jobs); global date filter.

Per SPEC §8.7: **all tunable product values are admin-editable in Filament** — plans & limits (project limits, refund window), pricing (`plan_prices`), feature flags (dark toggle, tree interactions, live-edit mode), and goal targets feeding dashboard tracking. No hardcoded product values.

### FR-13 Email & Notifications

Per SPEC §16:

- FR-13.1 All sends are queued Laravel Notifications (mail + database channels — email and the Filament/user bell share one system). Transactional mail is event-triggered; lifecycle sequences run as daily scheduled commands. Provider: Resend or Postmark 🟡 (`log` driver in dev).
- FR-13.2 **Transactional:** welcome + verify (P0), password/email change confirmations (P0), order-paid → welcome-to-Pro with license summary (P1; Paddle MoR receipts never duplicated), refund processed (P1), ticket created/replied/resolved (P1), domestic payment confirmed (P2), GitHub connected security notice (P2).
- FR-13.3 **Lifecycle sequences:** B1 free onboarding drip (Day 0/2/4/7/12) · B2 upgrade trigger on ≥3 blur-gate hits/week 🟡 · B3 paid onboarding (Day 0/3/7) · B4 new-drops digest (weekly/monthly opt-in; retention-critical) · B5 domestic renewal reminders (T-7/T-3/T-1/expired+1/+7; P2) · B6 dunning (5 touches/15 days, deep-link to update-payment, 25–35% recovery target) · B7 cancel flow (required exit survey → reason-mapped save offer → Day 7 reactivation → Day 30 win-back; 10–15% save target) · B8 re-engagement (30d/60d → suppress).
- FR-13.4 **Preference center** at `/settings/notifications`: transactional mandatory; digest/blog/product-updates individually opt-out; one-click unsubscribe in every non-transactional mail (CAN-SPAM / GDPR / PIPL).
- FR-13.5 Branded markdown templates (logo + single CTA); EN at launch, zh templates with domestic payments (P2). Filament: full notification log + resend action.

### FR-14 Affiliate Program (P2)

Per SPEC §17:

- FR-14.1 Self-serve join with Affiliate Terms acceptance; unique referral code + `/r/{code}` tracked link (301 redirect, click recording, 30-day first-party cookie, last-click attribution).
- FR-14.2 Signup attribution (referral ↔ user); checkout attribution via Paddle `custom_data` / domestic order meta so it survives cookie loss.
- FR-14.3 Commission engine: settings-driven rate (default 30% of net), subscription renewals within 12 months, lifetime one-time; statuses `pending → payable → paid`; payable only after refund window + holding period; void on refund/chargeback; self-referral banned.
- FR-14.4 Monthly payout batch for payable commissions ≥ threshold (default $50, CNY normalized); manual methods (PayPal/Wise), admin marks paid with reference.
- FR-14.5 Affiliate dashboard (CSR): overview stats, link card, commissions, payout history, payout-method form, join flow.
- FR-14.6 Admin (Filament): affiliates (suspend), commissions (void), payout batches, Affiliate settings group — all knobs admin-editable (§ SPEC 8.7).
- FR-14.7 Emails: conversion credited, commission payable, payout sent.
- FR-14.8 Affiliate Program Terms page (`/affiliate-terms`, SSR + indexed) linked from join flow and footer.

---

## 7. Data Model

| Entity | Key fields | Notes |
|---|---|---|
| `users` | auth, plan access, github_token (encrypted) | existing |
| `admins` | Filament auth | existing |
| `components` | slug, name, level, usage_category_id, access_level, version, status, citation fields, deps[] | source of truth = files; DB is index |
| `component_industry` | component_id, industry_id | multi-tag pivot |
| `component_children` | parent_id, child_id, slot, sort_order | derived at sync; cycle-checked |
| `categories` | type (industry/usage), name, slug | seeded per SPEC §4 |
| `tags` | name | free-form |
| `projects` | user_id, name | FR-4 |
| `project_components` | project_id, component_id, is_dependency | closure materialized |
| `plan_prices` | plan, period, amount, currency, paddle_price_id | FR-7.5 |
| `orders` | user_id, plan, status, billing_period, amount, starts_at, ends_at (null = lifetime), cancelled_at | existing, extended |
| `component_events` | component_id, user_id?, type (view/copy/download/scaffold), created_at | analytics + license tracking |
| `blogs` (+ extensions) | categories/tags, seo meta, related components | existing, extended |
| `support_tickets` | user_id, subject, category, status | FR-10 |
| `support_ticket_messages` | ticket_id, author_type, body, attachments | FR-10 |
| `affiliates` | user_id, code, status, payout_method, terms_accepted_at | FR-14 |
| `affiliate_referrals` | affiliate_id, referred_user_id?, clicked_at, ip/ua, converted_at? | FR-14 |
| `affiliate_commissions` | affiliate_id, order_id, amount, status (pending/payable/paid/voided), payable_at? | FR-14 |
| `affiliate_payouts` | affiliate_id, amount, status, method, reference?, paid_at? | FR-14 |

Component source files, `params.json`, `data.json`, preview HTML artifacts, screenshots, and export zips live on disk/storage — not in the DB.

---

## 8. UX Specifications (key surfaces)

- **Sitemap / page inventory:** SPEC §15 (all pages across public SSR, auth, checkout, dashboard, admin, infrastructure zones, with phasing).
- **Preview modal anatomy:** SPEC §5.4 (header / toolbar / tabs / editable panes / gating / keyboard).
- **Structure tree:** SPEC §5.5 (folding, instance chips, hover/pin highlight, navigation).
- **Admin dashboard layout:** SPEC §8.6 (six rows + phasing).
- **Rendering zones:** SPEC §10.1 (SSR public · CSR dashboard/checkout + noindex · Filament admin).
- Full wireframes are out of scope for this document; the modal ASCII layout in SPEC §5.4 is the binding reference.

---

## 9. Non-Functional Requirements

**Performance**
- NFR-1: Component page LCP < 2.5s on 4G (SSR + cached thumbnails).
- NFR-2: Preview iframe interactive < 1s after modal open (prebuilt static artifact).
- NFR-3: Live-edit runtime lazy-loaded; zero cost when unused.
- NFR-4: Preview builds, screenshots, zip/scaffold assembly run via queue; no web-request blocking.

**SEO**
- NFR-5: All public pages SSR with unique title/meta, OG images, sitemap, structured data.
- NFR-6: Clean URLs (`/components/{usage}/{slug}`); canonical URLs; `noindex` on dashboard/checkout.

**Security & privacy**
- NFR-7: Preview iframes sandboxed without `allow-same-origin`.
- NFR-8: GitHub tokens encrypted at rest; Paddle webhooks signature-verified.
- NFR-9: Minimal analytics (own `component_events`; no third-party trackers required at launch).
- NFR-10: Rate limiting on auth, downloads, and ticket creation.

**Accessibility**
- NFR-11: Modal focus-trapped, keyboard operable; tree menu aria-compliant; WCAG 2.1 AA target for public pages.

**Compatibility**
- NFR-12: Modern evergreen browsers; live-edit desktop-gated.

---

## 10. Technical Architecture

| Layer | Choice |
|---|---|
| Backend | Laravel 13, PHP 8.4, MySQL 8.4 |
| Admin | Filament 5 panel (`/admin`) |
| Public + dashboard | Inertia v2 + React 19 + Tailwind 4 (SSR public / CSR app) |
| Component library | `library/react`, `library/vue` standalone Vite apps in-repo |
| Preview pipeline | queue jobs: closure resolution → Vite build → AST instrumentation → headless screenshots |
| Payments | Cashier Paddle (international, webhooks) + `yansongda/pay` (Alipay/WeChat, CNY, P2) |
| OAuth | Socialite (GitHub) |
| Storage | local/S3: previews, screenshots, zips |
| Queue/session/cache | database drivers (existing default) |

Existing code reused: User/Admin models, Blog scaffolding, Order model + enums (extended), Filament panel + resources.

---

## 11. Phasing & Roadmap

| Phase | Ships | Exit criteria |
|---|---|---|
| **P0 — Foundation** | Data model · taxonomy seed · `library:sync` + preview pipeline · catalog browse · preview modal (prebuilt) · structure tree · free copy/download · basic docs · auth/system emails + Day-0 welcome | 20+ components published; sync→preview→publish pipeline proven end-to-end |
| **P1 — Monetization** | Projects · pack zip · Paddle checkout (all plans/periods incl. lifetime) · Blog · full Docs · Ticketing · order/ticket emails + lifecycle sequences (B1–B4, B6, B7) | First paid order end-to-end; gated components enforced |
| **P2 — Power features** | Live-edit mode · Next/Nuxt scaffolding · GitHub export · domestic payments (Alipay/WeChat, CNY) · domestic renewal reminders (B5) + zh email templates · affiliate program | Scaffold generated + repo pushed from UI; domestic QR order end-to-end |
| **P3 — Growth** | Meilisearch · team tier · community submissions · AI features | post-launch planning |

---

## 12. Risks & Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Legal claims from cited sites | Takedown demands, reputational | Recreate-don't-copy rule, attribution, takedown ticket category with prompt removal SLA |
| Lifetime-plan liability | Indefinite support obligation | Revenue partially funds drop cadence; yearly pushed as "best value"; license terms explicit |
| Two live-edit runtimes (React + Vue) | Double maintenance | Ship React runtime first if needed; unified wrapper UI; fallback without outlines documented |
| Wasm compilers on low-end devices | Poor edit UX | Desktop gate + prebuilt preview remains default everywhere |
| Content velocity below plan | SEO + churn suffer | Coverage matrix prioritization; blog compounds; 20/mo target tracked on dashboard |
| Preview build fragility | Publish blocks | Standalone smoke test + failed-build widget + retry; publish requires green builds |
| Lifetime cannibalizing subscriptions | MRR ceiling | Plan-mix donut monitored; reprice via `plan_prices` without deploy |
| Paddle seller approval delayed/rejected | P1 payments blocked | Chinese citizens eligible via passport KYC (payouts via Wise/Payoneer) per 2026 guides, but apply during P0 — approval is discretionary; fallback: Lemon Squeezy / Polar (also MoR) |
| Domestic merchant/ICP approval delays (Alipay/WeChat) | P2 domestic payments blocked | Entity + ICP filing workstream starts in P0 (4–8 wk lead); Paddle revenue unaffected in the meantime (SPEC §7.5) |
| Affiliate fraud (self-referrals, cookie-stuffing, fake signups) | Margin leakage, chargebacks | Self-referral ban, click rate-limiting, holding period + refund clawback before payout, admin suspend, manual payout review (SPEC §17) |

---

## 13. Ruled Questions

All previously open questions were ruled by the owner on 2026-07-19 — **all proposals approved** and made admin-configurable via Filament platform settings (SPEC §8.7). See SPEC §14 for the full ruling record. No open questions remain; future changes go through SPEC.md review and this document is revised accordingly.

---

## 14. Appendix

- **A. Taxonomy lists** — SPEC §4 (12 industries, 32 usage patterns, governance)
- **B. Modal + tree UX reference** — SPEC §5.4–5.5
- **C. Authoring annotation format** — SPEC §8.2
- **D. License summary** — SPEC §7.4
- **E. Decision record** — [docs/SPEC.md](./SPEC.md) incl. change log

---

*End of PRD v1.0 draft. Review notes and change requests go back into SPEC.md, then this document is revised.*
