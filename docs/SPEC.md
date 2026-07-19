# FrontendParts — Product & Technical Specification

**Status:** Living document — decision record prior to the full PRD
**Last updated:** 2026-07-19
**Legend:** 🔒 locked decision · 🟡 proposed, pending final approval

---

## 1. Product Overview

FrontendParts is a curated frontend component library where every component is **recreated from real-world live websites** (with citation), offered in both **React and Vue**, styled exclusively with **Tailwind CSS**.

Users can:

- Browse and preview components in a Codepen-style modal (viewport switching, structure inspection)
- Copy or download individual components, or collect them into a **pack** (zip)
- Start a **completely new project** — a downloadable Next.js or Nuxt starter containing the selected components
- Push a generated project directly to a **GitHub repository**

**Differentiators:** dual-framework (React + Vue), industry-based curation cited from live sites, pack-based download, full project scaffolding.

**Reference products:** Tailwind Plus, HyperUI, Flowbite, Preline, shadcn/ui.

---

## 2. Component Model 🔒

### 2.1 Granularity levels

Every component is exactly one of four levels:

| Level | Definition | Examples |
|---|---|---|
| `element` | Smallest self-contained unit | button, badge, section title, input |
| `block` | Meaningful group of elements | pricing card, testimonial, nav cluster |
| `section` | Full page section (main catalog unit) | hero, pricing, footer |
| `page` | Complete page composed of sections | landing page |

Level **classifies granularity only** — it is not a containment rule. Same-level nesting is allowed (a section may contain a section).

### 2.2 Composition graph 🔒

- Components compose: a composite references other components as **children**.
- The graph is **derived from code** (Option A): the sync pipeline statically parses each composite's imports; any import of another library component registers as a child edge. Never declared by hand.
- **Max nesting depth: 10.**
- **Cycle detection at sync time** — `A → B → A` fails the import with a precise error.
- Shared children are deduplicated (two sections using the same card reference one component).
- The Filament admin shows a **read-only visualization** of the parsed tree.

### 2.3 Dual build artifacts 🔒

| Artifact | Purpose | Instrumentation |
|---|---|---|
| **Preview build** | Modal iframe | `data-fp-c="{slug}"` + `data-fp-i="{n}"` injected by AST transform at build time |
| **Export source** | Downloads / scaffolds | Completely clean — authored source never contains instrumentation |

React children forward props to their root DOM node (authoring convention, shadcn-style); Vue uses native attribute fall-through. Children rendering fragments/multiple roots cannot be outlined — accepted limitation ("as much as possible").

### 2.4 Exported file structure 🔒

Zips preserve the composition graph — one file per component, organized by level, parents importing children, sample data as a separate imported file:

```
components/
├── elements/SectionTitle.tsx
├── elements/Button.tsx
├── blocks/PricingCard.tsx        ← imports Button
├── sections/PricingSection.tsx   ← imports SectionTitle + PricingCard
└── data/pricing-section.ts       ← sample data, imported by the section
```

Deterministic ordering: elements → blocks → sections → pages.

### 2.5 External npm dependencies 🔒

**Three-tier policy:**

| Tier | What | Rule |
|---|---|---|
| 0 — Zero-dep (default) | React/Vue + Tailwind only | Target for most components; earns a "zero-dep" badge + catalog filter |
| 1 — Approved utilities | Curated allowlist (icons: `lucide-react`/`lucide-vue-next` · animation: `motion`/`motion-v` · carousel: `embla-carousel-react`/`embla-carousel-vue` · …) | Needs justification; must exist in **both** ecosystems |
| 2 — Behavior primitives ❌ | ~~Radix UI (React) / Reka UI (Vue)~~ | **Rejected by owner** — all behavior is hand-rolled; components stay dependency-light |

**Hard rules:** a dep with no equivalent in the other ecosystem → component cannot publish · off-allowlist deps are rejected by `library:sync` unless explicitly approved.

**Central registry — `library/deps.registry.json`:** logical name → per-framework package + pinned version (`"lucide": { "react": "lucide-react@^x", "vue": "lucide-vue-next@^x" }`). Annotations (`@deps`) name packages only, never versions. Both library apps install every approved package; sync fails components whose `@deps` are not in the registry.

**Consumption per surface:** preview builds bundle deps inline (self-contained HTML, nothing to solve) · Docs tab lists deps + zero-dep badge · pack zip merges/dedupes the closure's deps into a `package.json` snippet + install instructions · scaffolds merge into starter `package.json` · live edit resolves pinned versions from CDN (esm.sh) so it renders identically to prebuilt previews.

---

## 3. Params & Sample Data 🔒

### 3.1 Two-file model per component

| File | Role | Contents |
|---|---|---|
| `params.json` | Schema + defaults (contract) | name, type, default, description, constraints per param |
| `data.json` | Sample content (showcase) | Realistic demo values (texts, image URLs) overriding defaults |

**Resolution order:** props passed in → `data.json` → param defaults.
Every param must have a default → **every component renders independently** at any level.

### 3.2 Type vocabulary

`string · text · number · boolean · enum(options) · image · url · array<T> · object{…}`

Non-serializable params (callbacks, render props) are excluded from JSON, documented in Docs, and default to no-ops in standalone mode.

### 3.3 Composition data flow

- Each child keeps its own `params.json` + `data.json` (independent display).
- A composite's `data.json` mirrors its children (e.g. `plans: [...]` conforming to `PricingCard`'s schema); code passes slices down as props.
- **`library:sync` validates composite data slices against child schemas** — mismatches fail the import.

### 3.4 QA gate

Every sync runs a **standalone render smoke test** (defaults only → must render → screenshot). A component that cannot display independently cannot be published.

### 3.5 Payoffs

- Docs tab auto-generates the props table from `params.json`
- Live mode (P2) can render auto-generated controls from the schema
- Exports include `data.json`; `params.json` stays platform-side

---

## 4. Taxonomy 🔒 (refinable by owner)

- **Usage pattern:** required, single per component
- **Industry:** optional, multi-tag (components recreated from a site inherit its industry; neutral components carry none)
- **Tags:** free-form, cross-cutting (dark, animated, minimal, gradient)

### 4.1 Industries (12)

SaaS & Software · Ecommerce & Retail · Fintech & Finance · Healthcare & Medical · Education · Real Estate · Food & Restaurant · Travel & Hospitality · Agency & Portfolio · Crypto & Web3 · Fitness & Wellness · Events & Entertainment

### 4.2 Usage patterns (26), grouped by zone

| Zone | Patterns |
|---|---|
| Navigation | Navbar · Footer · Sidebar · Mega Menu · Breadcrumb |
| Opening | Hero · Logo Cloud · Announcement Banner |
| Content | Feature Grid · About Section · Gallery · Team · Blog List · Blog Article · Stats |
| Social proof | Testimonial · Reviews & Ratings · Case Study |
| Conversion | Pricing · CTA · FAQ · Contact Form · Newsletter · Auth Forms |
| Commerce | Product Card · Product Detail · Cart & Checkout |
| App UI | Dashboard Card · Data Table · Modal & Dialog · Alerts & Toast · Empty State & 404 |

### 4.3 Governance

- A category appears in the UI once it has **≥ 3 components**
- A new industry is added only when **≥ 5 components** exist for it
- The industries × usage **coverage matrix** doubles as the authoring roadmap

---

## 5. Preview System 🔒

### 5.1 Strategy

Two rendering paths. **No live compiler on the default path.**

**Path 1 — Prebuilt (default):** publish-time static HTML per framework, shown in a sandboxed iframe. Instant, secure, cacheable, identical mechanics for React and Vue.

**Path 2 — Live edit mode (P2):** in-browser compilation, lazy-loaded on demand; keystrokes never touch the server.

### 5.2 Preview build pipeline (publish time)

1. Resolve composition closure (children, depth ≤ 10)
2. Generate entry file mounting the component with its sample data
3. Dedicated Vite build per framework → single self-contained HTML (JS + CSS inlined)
4. AST transform injects `data-fp-*` instrumentation
5. Headless browser screenshots at 375 / 768 / 1280 → QA gate + catalog thumbnails + OG images
6. Store at `storage/previews/{component}/{version}/{react|vue}.html`

Rebuilds only on change of the component or any descendant. Components cannot publish without passing 3-viewport visual review.

### 5.3 Iframe sandbox & protocol

- `<iframe sandbox="allow-scripts">` (no `allow-same-origin`) → full isolation
- Viewport width set on the iframe element; responsive Tailwind classes do the rest; height auto-reports

```
Parent → iframe:  { type: 'highlight', slug, instance: n | null }
                  { type: 'clear' }   { type: 'theme', mode }
Iframe → parent:  { type: 'ready' }   { type: 'height', px }
```

### 5.4 Preview modal layout 🔒 (layout itself editable)

**Header (fixed):** component name + level badge · usage category + industry tags · citation ("layout reference: {site}") · access badge · actions: **Add to Project** (primary) · Download · Share (deep link) · Close

**Toolbar:** viewport presets 375 / 768 / 1280 / full (+ live width readout, drag-resize) · React | Vue toggle (synced across preview, code, download) · dark/light toggle · structure panel toggle

**Tabs:**

| Tab | Contents |
|---|---|
| Preview | Structure tree + iframe stage |
| Code | File tabs (one per component file), syntax-highlighted source, per-file copy, zip CTA |
| Data | Sample JSON, pretty-printed, copy |
| Docs | Code-logic explanation, usage scenario, npm deps, auto-generated props table, version/changelog |
| Edit (P2) | Live multi-file editor + JSON editor + Save to Project / Reset / Download |

**Editable layout:** body = two swappable panes (stage ↔ content), drag-handle split, header/tab bar fixed; preference persisted to `localStorage` (anonymous) and account profile (logged-in).

**Behavior:** split view (content left, stage right) on Code/Data/Edit tabs; full-screen pane swap on mobile; keyboard shortcuts (`1–4` viewports, `r/v` framework, `esc`); "open preview in new tab"; focus trap + aria.

**Pro gating:** locked components show Preview + Docs; Code/Data blurred with upgrade overlay; CTAs become "Upgrade".

### 5.5 Structure tree menu 🔒

```
▼ PricingSection01          [section]
  ▼ SectionTitle            [element]
  ▾ PricingCard        ×3   [block]
  │   #1   #2   #3
  ▶ GuaranteeNote           [block]
```

- Foldable/unfoldable every node; default: depth 1–2 expanded
- Hover type → all instances outlined (soft); hover instance → single outline (strong)
- Click node name → navigate to that child's component page
- Click instance chip → preview scrolls it into view; click pins the highlight until dismissed
- Keyboard: `↑↓` move, `→` unfold, `←` fold
- Empty state for primitives; large instance groups scroll
- Tree JSON comes from the composition graph — always in sync with the code
- One-way sync only (panel → iframe); no reverse hover

### 5.6 Live edit mode (P2) 🔒

| Piece | React | Vue |
|---|---|---|
| Compiler | Sandpack-style in-browser bundler (esbuild-wasm) | `@vue/repl` (esbuild-wasm) |
| Styling | `@tailwindcss/browser` (official v4 browser compiler) | same |
| Files | parent + each child as editable tabs | same |
| Data | JSON editor pane, instant re-render | same |

- Lazy-loaded on first "Edit" click; cached after; desktop-gated ("best on desktop" notice on mobile)
- Same attribute-injection runs client-side so outlines keep working (fallback: v1 without outlines, documented)
- **Download** of edits = instant (source files, no build)
- **Save to Project** = customized fork; background server build produces its prebuilt preview + screenshots (progress UI; seconds acceptable for explicit save)

---

## 6. Projects & Exports 🔒

### 6.1 Projects

- `projects` (id, user_id, name, timestamps) and `project_components` (project_id, component_id, `is_dependency`)
- Projects are **framework-agnostic** component sets; React/Vue + Next/Nuxt chosen at export
- **Auto-add closure:** adding a composite inserts its full descendant tree (`is_dependency = true`), deduplicated
- **Removal cascade:** removing a direct component prunes now-orphaned dependencies (with user notice); dependencies still used elsewhere stay
- The pack cart *is* a project — pack zip and scaffolds are both project exports
- Adding to a project requires an account (doubles as license tracking); quick copy of free components stays accountless

**Project limits per plan** 🔒 (admin-editable, §8.7): Free 1 · Starter 3 · Pro unlimited

### 6.2 Pack zip export

`components/` (by level, full transitive closure) + `data/` + merged `package.json` dependency snippet + Tailwind setup notes + README.

### 6.3 Project scaffolding (Next.js / Nuxt) 🔒

- **Next.js:** App Router, TypeScript-only, Next 15 + React 19 + Tailwind 4
- **Nuxt:** TypeScript-only, Nuxt 4 + Vue 3 + Tailwind 4
- Contains: `components/`, `pages/` (or `app/`), `public/`, `data/`, `package.json`, `tsconfig`, configs, `.gitignore`
- Pages come from **both**: page-level components → routes; loose selected sections → assembled into the index page in selection order
- Sample images stay **remote URLs** (not downloaded into `public/`)
- Assembled server-side by a queued job → zip download

### 6.4 GitHub repo export 🔒

- Laravel Socialite, GitHub OAuth, scope `repo`; token stored **encrypted**
- Flow: pick project → name repo (public/private) → API creates repo → all files committed in a single commit via Git Trees API → returns repo URL

---

## 7. Plans, Pricing & Licensing 🔒

### 7.1 Structure

**Free / Starter / Pro × monthly / quarterly / yearly / lifetime.** Team tier deferred to v2 (model stays compatible).

| | Free | Starter | Pro |
|---|---|---|---|
| Browse + preview full catalog | ✅ | ✅ | ✅ |
| Components copy/download | Free subset (20–30%) | ✅ 100% | ✅ 100% |
| React + Vue versions | Free subset | ✅ | ✅ |
| Pack builder | Free subset | ✅ | ✅ |
| Next.js / Nuxt scaffolding | — | — | ✅ |
| New drops | Free subset | ✅ | ✅ + early access |
| Future pro features | — | — | ✅ |

### 7.2 Price points 🔒 (admin-editable via `plan_prices`)

| Period | Starter | Pro |
|---|---|---|
| Monthly | $9 | $15 |
| Quarterly | $24 ($8/mo) | $36 ($12/mo) |
| Yearly | $72 ($6/mo) | $108 ($9/mo) |
| Lifetime | $149 | $299 |

Lifetime is a **permanent offering** (≈ 2.5–3× yearly). Pricing page visually pushes yearly as "best value".

### 7.3 Mechanics

- **Paddle** (merchant of record) via **Cashier Paddle**; checkout overlay; webhooks update orders
- `plan_prices` table (plan × period → Paddle price ID, amount) — repricing without deploys
- `BillingPeriod` enum += `Quarterly`, `Lifetime`; lifetime orders have `ends_at = null`
- `components.access_level: free | paid` drives the 20–30% free subset
- 14-day refund window (admin-editable, §8.7)

### 7.4 License (one license, all paid plans)

Unlimited personal + commercial projects incl. client work · no redistribution/resale/sublicensing, no publishing as a competing library · downloaded code kept forever after lapse (access to new downloads/drops expires, usage rights don't) · single user/seat until team tier (v2).

### 7.5 Domestic payments — WeChat Pay / Alipay 🔒

Dual payment backends behind one unified checkout:

| | International | Mainland China |
|---|---|---|
| Provider | **Paddle** (MoR) | **Alipay + WeChat Pay** via `yansongda/pay` |
| Currency | USD | CNY |
| Fees | ~5% + processing | ~0.6% |
| Buyer UX | card / PayPal / Apple Pay | desktop QR scan · mobile app wake-up |

- **Routing:** buyer region (geo-detect + manual currency switch) selects the backend; both providers' webhooks normalize into the **same `orders` state machine** (§7.3)
- **Entity & compliance (owner-approved):** register a **个体工商户**; domain gets **ICP备案** with mainland hosting for checkout; merchant accounts for 支付宝 + 微信支付
- **Pricing:** `plan_prices` gains `provider` + CNY amounts per plan/period (admin-editable, §8.7)
- **Domestic subscriptions (owner-approved):** launch as **one-time payment per period** with renewal-reminder emails before expiry — **no auto-deduct** at launch (支付宝周期扣款 / 微信委托代扣 requires extra merchant qualification; apply later once the account has history). **Lifetime plans unaffected** — natural fit for QR pay
- **Refunds:** provider APIs, honoring the 14-day window
- **Reporting:** CNY revenue normalized into dashboard MRR at an admin-configurable FX rate
- **Timeline:** WS-1 paperwork (entity → ICP → merchant applications, 4–8 weeks) starts in **P0**; WS-2 build ships in **P2** (after Paddle in P1)

---

## 8. Authoring Pipeline 🔒

### 8.1 File-based, code-first

```
library/
├── react/    ← standalone Vite + React app
│   └── sections/pricing-section-01/{index.tsx, params.json, data.json}
└── vue/      ← standalone Vite + Vue app
    └── sections/pricing-section-01/{index.vue, params.json, data.json}
```

Both folders live in the main repo (git-versioned). Same slug in both = same component, two implementations.

### 8.2 Annotation metadata (source of truth)

```tsx
/**
 * @component  pricing-section-01
 * @name       Pricing Section 01
 * @level      section
 * @usage      pricing
 * @industries saas, fintech
 * @tags       dark, gradient
 * @access     pro
 * @source     https://stripe.com/pricing
 * @deps       lucide-react
 * @version    1.0.0
 */
```

### 8.3 Sync command

`library:sync` (Artisan; also triggerable from Filament): scan folders → parse annotations → validate (Vue/React twin exists · taxonomy exists · JSON valid · **no cycles** · depth ≤ 10 · data slices match child schemas) → upsert DB → queue preview builds.

### 8.4 Standalone preview

Each library folder serves `/preview/{slug}` mounting the component with its `data.json` — used during authoring **and** by the preview build pipeline (one renderer, no duplication).

### 8.5 Workflow

`draft → in_review → published`. QA checklist per component: renders at 3 viewports · React/Vue visual parity · data fully separated · content license-clean · **for interactive components: accessibility check (keyboard navigation, focus management, ARIA roles) — required since all behavior is hand-rolled (§2.5)**. Filament = management/QA/publish dashboard; code is never form-edited.

### 8.6 Admin dashboard 🔒

Answers four questions at a glance: pipeline health · catalog growth · revenue · what needs attention now. Global date-range filter (7d / 30d / 12m).

**Row 1 — KPI stat cards:** Published components (+N/week) · **Awaiting review** (highlighted when > 0) · Registered users (+N/week) · Active subscribers (Starter+Pro) · MRR (normalized across periods) · Downloads 30d (components + zips + scaffolds)

**Row 2 — Money:** Revenue trend (line, 12 months; lifetime spikes + MRR line) · Plan mix (donut, active orders by plan × period — watches lifetime cannibalization)

**Row 3 — Growth:** Signups & downloads (dual-axis, 30d) · Catalog growth (cumulative area, split by level)

**Row 4 — Action tables:** Latest orders (user, plan, period, amount, status, Paddle link) · Drafts awaiting review (inline actions: Preview / Publish / Reject)

**Row 5 — Catalog intelligence:** Top components (views + downloads, 30d) · **Coverage matrix** — industries × usage heatmap, empty cells flagged red ("what to build next" board)

**Row 6 — System health:** Failed preview builds (error excerpt + retry) · Last `library:sync` run (timestamp, scanned, upserted, validation errors) · Failed queue jobs

**Widget phasing:** P0 = row 6, catalog stats, drafts queue, coverage matrix · P1 = users, orders, revenue, plan mix · P2 = downloads/projects tracking, top components

**Data model addition 🔒 — `component_events` table:** `id · component_id · user_id (nullable) · type (view | copy | download | scaffold) · created_at`. Feeds dashboard metrics, per-component popularity stats, and license download tracking.

### 8.7 Platform settings (Filament) 🔒

**Owner requirement:** every tunable product value is admin-editable in Filament — never hardcoded. Stored in a cached, typed `settings` key-value table and surfaced through a Filament **Settings** page, grouped as:

| Group | Settings | Current values 🔒 |
|---|---|---|
| **Plans & limits** | Project limit per plan · refund window (days) | Free 1 · Starter 3 · Pro unlimited · 14 days |
| **Pricing** | `plan_prices` resource — plan × period → amount + Paddle price ID | §7.2 ladder |
| **Feature flags** | Preview dark/light toggle · tree interactions (pin, navigate, scroll-to, keyboard) · live-edit mode (P2) | all enabled |
| **Goals** | Launch component target · success metric targets (organic visits, signup & paid conversion, churn, components/mo, MRR) | ≥ 100 components · PRD §2 targets |

Goals feed the admin dashboard (§8.6) as target-vs-actual tracking. Feature flags are read by the site at runtime (edge-cached), so toggles take effect without deploys.

---

## 9. Content Sourcing & Legal 🔒

- Components are **recreated layouts inspired by** live sites, always attributed; never copied code, images, text, logos, or trademarks
- All sample content is original placeholder text + placeholder/free-license images
- Citation ("layout reference: {site}") displayed on component page and modal header
- Takedown policy: documented contact → prompt removal path

---

## 10. SEO, Rendering & Performance 🔒

### 10.1 Rendering strategy (three zones)

| Zone | Rendering | Why |
|---|---|---|
| **Public frontend** (home, catalog, component pages, blog, pricing) | **SSR** (Inertia SSR) | SEO — every component page is a landing page |
| **Checkout** | **CSR** | Excluded from SSR; dynamic pricing, Paddle overlay is client-side anyway; `noindex` |
| **User dashboard** (projects, packs, settings, downloads) | **CSR** | Authenticated, non-indexable; no SSR cost/hydration complexity for user-specific data |
| **Admin dashboard** | **Filament panel** (own Livewire stack, separate from Inertia) | Already isolated at `/admin` |

**Mechanics:** Inertia SSR enabled globally; a lightweight middleware disables the SSR gateway per-request for dashboard/checkout route groups (runtime config flip, no separate app needed). Auth + checkout routes also carry `noindex` robots meta.

### 10.2 SEO mechanics

- Clean URLs: `/components/{usage}/{slug}`; per-component meta + OG image (auto screenshot); sitemap; structured data
- Blog doubles as keyword engine (industry × usage articles)

### 10.3 Performance

- Preview iframes lazy-load; static previews cache aggressively
- Live-edit runtime lazy-loads only on "Edit" click

---

## 11. Architecture & Infrastructure

- **Backend:** Laravel 13 + MySQL, Filament 5 admin, database queue, local/S3 storage
- **Frontend:** Inertia v2 + React 19 + Tailwind 4 (SSR on public pages, CSR on dashboard/checkout — §10.1)
- **Library apps:** `library/react`, `library/vue` (standalone Vite apps in-repo)
- **Payments:** Cashier Paddle
- **OAuth:** Socialite (GitHub)
- **Existing models reused:** User/Admin, Blog (content marketing), Order (subscriptions)

---

## 12. Phasing 🔒

| Phase | Ships |
|---|---|
| **P0 — Foundation** | Data model · `library:sync` + preview build pipeline · catalog browse · preview modal (prebuilt) · structure tree · free component copy/download |
| **P1 — Monetization** | Projects · pack zip export · Paddle checkout (Starter/Pro × 4 periods incl. lifetime) · Blog · Documentation · Support ticketing |
| **P2 — Power features** | Live-edit mode · Next.js/Nuxt scaffolding · GitHub repo export · domestic payments (Alipay/WeChat, CNY — WS-1 paperwork starts P0) |
| **P3 — Growth** | Meilisearch · team tier · community submissions · AI features |

---

## 13. Blog, Documentation & Support 🔒

### 13.1 Blog (SEO content engine)

- **Public (SSR):** `/blog` index + `/blog/{slug}` article pages
- **Features:** categories + tags · cover image · reading time · per-article TOC · related posts · **related components** (article ↔ catalog cross-linking — the core SEO interlinking mechanic) · Article structured data · RSS feed · sitemap inclusion · scheduled publishing
- **Admin:** extends the existing `blogs` table + Filament Blog resource (add: categories/tags, SEO meta fields, related-components pivot, reading time)
- **Content strategy:** industry × usage keyword articles ("10 SaaS pricing page designs, recreated") linking into the catalog

### 13.2 Documentation

- **File-based markdown** in the repo (`docs/content/`) — code-first, PR-reviewable, versioned with the product (same philosophy as the component library)
- **Public (SSR):** `/docs/{section}/{page}` with sidebar nav tree · per-page TOC · prev/next · copyable code blocks · search (basic at launch → Meilisearch at P3)
- **Launch sections:** Getting Started · Install per framework (React / Vue / Next / Nuxt) · Using Components (params & data model) · Customizing (Tailwind tokens) · Scaffolding & GitHub Export · License FAQ · Troubleshooting

### 13.3 Support ticketing

- **`support_tickets`:** id · user_id · subject · category (`billing | technical | license | takedown | other`) · status (`open | pending | resolved | closed`) · timestamps
- **`support_ticket_messages`:** ticket_id · author (user or admin) · body · attachments · created_at
- **User side (dashboard, CSR):** ticket list · create form · threaded reply view
- **Admin side (Filament):** ticket inbox with status/category filters · reply interface · **order context attached for billing tickets** · assignment deferred (single admin at MVP)
- **Email notifications** both directions (new ticket → admin; admin reply → user)
- The `takedown` category doubles as the §9 legal takedown channel

---

## 14. Ruled Items

All open items were ruled by the owner on 2026-07-19: **all proposals approved**, with the standing requirement that every tunable value is admin-configurable via Filament (§8.7). No open items remain.

| # | Item | Ruling |
|---|---|---|
| 1 | Project limits per plan | 🔒 Free 1 · Starter 3 · Pro unlimited (admin-editable) |
| 2 | Price points | 🔒 §7.2 ladder (admin-editable via `plan_prices`) |
| 3 | Dark/light toggle in preview modal | 🔒 included (feature-flagged) |
| 4 | Tree interactions (pin, navigate, scroll-to, keyboard) | 🔒 all included (feature-flagged) |
| 5 | Refund window | 🔒 14 days (admin-editable) |
| 6 | Launch component target | 🔒 ≥ 100 across priority matrix cells (dashboard goal) |
| 7 | Success metric targets | 🔒 PRD §2 (dashboard goal tracking) |

---

## Change Log

- **2026-07-19** — Initial compilation from design discussion (all modules)
- **2026-07-19** — Rendering strategy locked: SSR on public pages, CSR on dashboard + checkout (`noindex`), Filament for admin (§10)
- **2026-07-19** — Admin dashboard spec locked (§8.6): KPI cards, revenue/growth charts, action tables, coverage matrix, system health; new `component_events` table added to data model
- **2026-07-19** — Blog, Documentation & Support ticketing locked (§13): SEO blog with component cross-linking, file-based markdown docs, ticket system with billing context + takedown channel; all three added to P1 phasing
- **2026-07-19** — External npm dependency policy locked (§2.5): three-tier allowlist, central `deps.registry.json` version pinning, both-ecosystems parity rule
- **2026-07-19** — Tier-2 rejected by owner: no Radix/Reka primitives — all behavior hand-rolled, components stay dependency-light (§2.5)
- **2026-07-19** — All 7 open items ruled: proposals approved; new §8.7 Platform Settings — every tunable value admin-editable in Filament (plans & limits, pricing, feature flags, goals). Open-items list cleared.
- **2026-07-19** — Domestic payments locked (§7.5): dual backends (Paddle international + Alipay/WeChat CNY), 个体工商户 + ICP备案 approved, manual-renewal domestic subscriptions at launch, WS-1 paperwork in P0 / WS-2 build in P2
