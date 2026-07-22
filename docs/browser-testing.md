# Browser Testing Guide

| | |
|---|---|
| **Version** | 1.0 |
| **Date** | 2026-07-23 |
| **Scope** | Detailed browser-based test procedures for FrontendParts (main product) and the `saas-skeleton` branch — automated suite usage + full manual QA matrices. |

Use this before every release, after large merges, and when verifying a fresh environment. Every procedure lists **steps** and the **expected result**. Anything marked 🔑 needs real third-party credentials (see `docs/launch-runbook.md`); everything else works fully offline.

---

## 1. Environment setup

### 1.1 Start the app

```bash
npm run dev              # boots: artisan serve (app) + vite (HMR) + queue worker
npm run dev -- --port 7100   # custom port (Kimi Work preview uses 7100)
```

- App: `http://localhost:8000` (default) or the forwarded port.
- The queue worker is required for preview builds, pack zips, scaffolds, fork rebuilds, and mail.
- Stop with `Ctrl+C` — all three processes die with the parent.

### 1.2 Reset to a known state

```bash
php artisan migrate:fresh --seed     # fresh DB: admin, users, plans, blog posts
php artisan library:sync             # import the 20 components from library/
php artisan queue:work --stop-when-empty   # build all previews + screenshots (~5 min)
php artisan tinker --execute '\App\Models\Component::query()->update(["status" => "published"]);'
```

### 1.3 Accounts & access

| Role | Credential | Notes |
|---|---|---|
| Admin panel | `admin@example.com` / `password` at `/admin` | seeded |
| Users | register your own | 20 random factory users also exist (emails unknown — register fresh) |
| Email verification | **disabled in local dev** (`REQUIRE_EMAIL_VERIFICATION=false` in `.env`) | to test the verification flow, set it `true` |
| Mail | `MAIL_MAILER=log` | inspect mails in `storage/logs/laravel.log` |

### 1.4 Feature flags (Admin → Settings)

- `features.live_edit` — **off by default**; enable for the live-edit procedures (§4).
- `features.ai_search` / `features.ai_variants` — off; need an AI key 🔑.

---

## 2. Automated browser suite (run first)

```bash
npm run test:browser            # builds assets, seeds fixtures, serves, runs 11 specs
npm run test:browser:headed     # same, with a visible browser window
```

| Spec file | Covers |
|---|---|
| `preview-modal.spec.ts` | modal opens with real artifact, tabs render, viewport presets resize, React↔Vue toggle, catalog-card overlay + Escape |
| `structure-tree.spec.ts` | composition tree, hover soft-outlines on all instances, pin strong-outline on one instance |
| `live-edit.spec.ts` | Edit tab mounts, esbuild-wasm compiles in-browser, data + source edits re-render without error overlay |

**Expected:** `11 passed`. Artifacts (traces/screenshots on failure): `tests/Browser/results/`. PHPUnit sanity (`BrowserSuiteTest`) must also be green in `php artisan test`.

---

## 3. Manual matrix A — public catalog & SEO

| # | Steps | Expected |
|---|---|---|
| A1 | Open `/` | Hero, featured components, blog teasers; no console errors |
| A2 | Open `/components` | 20 published cards; pagination if >12; industry filter works |
| A3 | Open `/components/marketing` (any usage page) | filtered grid, SEO title in browser tab |
| A4 | Open a component detail (e.g. `/components/feature-grid/feature-card-01`) | name, level, access badge, preview button, citation/source info, code copy actions |
| A5 | Open `/collections` → a collection | curated grid in admin-defined order |
| A6 | Open `/search?q=hero` | components matching "hero" grouped above blog results |
| A7 | Open `/docs` → any page; use docs search | redirect to first page; search returns matching pages |
| A8 | Open `/blog` → an article | index grid; article with TOC rail, related posts, related components; `/blog/feed` is valid RSS |
| A9 | Open `/pricing` | 4-period toggle (monthly/quarterly/yearly/lifetime), "best value" on yearly, Team card with seat stepper, comparison table, FAQ |
| A10 | Open `/terms`, `/privacy`, `/license`, `/refund-policy`, `/cookie-policy`, `/copyright`, `/legal-notice`, `/affiliate-terms` | all render with footer nav present on every page |
| A11 | View-source any public page | `<title>` + meta description present (SSR) |
| A12 | Open `/sitemap.xml`, `/robots.txt` | sitemap lists public pages; robots blocks checkout/dashboard |
| A13 | Open `/nonexistent-page` | branded 404 with catalog link |

## 4. Manual matrix B — preview modal & structure tree

| # | Steps | Expected |
|---|---|---|
| B1 | Component detail → open preview modal | modal with stage + tabs (Preview/Code/Data/Structure) |
| B2 | Switch viewports (mobile/tablet/desktop/full) | stage resizes; preview re-renders correctly |
| B3 | Toggle React ↔ Vue | iframe swaps to the other framework's artifact |
| B4 | Code tab | multi-file source of the component **and its children**, syntax-formatted; copy button works |
| B5 | Data tab | the component's sample data (JSON/text + image URLs) separate from code |
| B6 | Structure tab | foldable tree: component → child components with instance counts |
| B7 | Hover a child row | matching regions outlined (dashed) inside the preview iframe |
| B8 | Pin an instance chip | exactly that instance gets a strong outline; unpin clears |
| B9 | As a **free** user on a **paid** component | blur gate over code/actions with upgrade CTA; no source visible |
| B10 | As **paid** user (admin-side: give your user a Starter order) | copy + download zip enabled |

## 5. Manual matrix C — live edit (enable `features.live_edit` first)

| # | Steps | Expected |
|---|---|---|
| C1 | Open modal → Edit tab | editor lazy-loads (network shows the esbuild chunk), desktop gate notice on small screens |
| C2 | Multi-file tabs | parent + every child component file editable |
| C3 | Change text in the JSON data editor | preview re-renders within ~1s, no error overlay |
| C4 | Change a class in a source file | re-compiles in-browser, change visible |
| C5 | Introduce a syntax error | error overlay/message; recover via Reset |
| C6 | Download edits | zip of the edited sources, no server build |
| C7 | Save to project (pick a project) | fork appears on project page → rebuild progress → fork preview renders; original component untouched |
| C8 | Structure outlines inside edit mode | hover outlines still work (client-injected) |
| C9 | Vue toggle in edit mode | @vue/repl editor with SFC files, same edit/re-render behavior |

## 6. Manual matrix D — auth & settings

| # | Steps | Expected |
|---|---|---|
| D1 | `/register` → submit | logged in, lands on dashboard (no verification wall in dev) |
| D2 | Logout → `/login` | dashboard returns |
| D3 | Forgot password → reset link | "sent" confirmation (mail in laravel.log); link resets |
| D4 | `/settings/profile` | name/email update persists |
| D5 | `/settings/password` | change works; confirmation email queued |
| D6 | `/settings/appearance` | theme switch applies instantly |
| D7 | `/settings/notifications` | digest/blog/product toggles persist; transactional locked on |
| D8 | `/settings/billing` | shows "no subscription" state for fresh user |
| D9 | Verification flow (optional): set `REQUIRE_EMAIL_VERIFICATION=true`, register | redirected to verification notice; mail link verifies |

## 7. Manual matrix E — pricing, checkout & billing 🔑 (Paddle sandbox)

> Needs `PADDLE_API_KEY` / `PADDLE_CLIENT_SIDE_TOKEN` / `PADDLE_WEBHOOK_SECRET` (sandbox). Webhook delivery to local requires a tunnel (e.g. `ngrok http 8000`) with the tunnel URL registered in Paddle. Card: `4242 4242 4242 4242`, any future expiry/CVC.

| # | Steps | Expected |
|---|---|---|
| E1 | `/pricing` → Starter monthly → checkout | Paddle overlay with the exact seeded price |
| E2 | Period switcher on checkout page | price/product updates per period |
| E3 | Complete sandbox purchase | `/checkout/success` with license summary |
| E4 | Webhook received | order Active on `/dashboard/orders`; welcome email in log; notification log row in admin |
| E5 | Download a paid component now | allowed (was 403 before) |
| E6 | `/settings/billing` → Cancel | survey required → reason-mapped save offer → confirm → access-until date shown + email |
| E7 | Admin → Orders → refund (within 14d) | Paddle adjustment; order Refunded; access revoked; refund email |
| E8 | Team checkout: `/pricing` Team card, 3 seats | total = 3 × per-seat price; org + seats on `/dashboard/team` |
| E9 | Invite member (email) → accept link | member sees Pro-level entitlements; removing them revokes |

## 8. Manual matrix F — projects, exports & scaffolding

| # | Steps | Expected |
|---|---|---|
| F1 | `/dashboard/projects` → create | appears with plan-limit usage badge |
| F2 | Add a **section** component | child components auto-added as dependencies (badged) |
| F3 | Remove the section | orphaned dependencies pruned with notice; shared children kept |
| F4 | Limit: free user creating a 2nd project | blocked with upgrade message (1 free / 3 starter / unlimited pro) |
| F5 | Export → framework react/vue → wait → download | zip: `components/` by level, `data/`, merged `package.json`, README, `TAILWIND.md` |
| F6 | Scaffold Next.js (Pro) | queued build → zip: `app/` routes for page components, index from loose sections, configs, `.gitignore` |
| F7 | Scaffold Nuxt (Pro) | Nuxt 4 structure, `app/pages/`, `nuxt.config.ts` |
| F8 | Zip sanity | unzip → `npm install` works conceptually (deps resolve; spot-check imports) |
| F9 | GitHub export 🔑 | connect at `/settings/connections` (OAuth) → export → new repo with single commit containing all files; disconnect clears token |

## 9. Manual matrix G — dashboard areas

| # | Steps | Expected |
|---|---|---|
| G1 | `/dashboard` | plan card with state-aware CTA, projects, recent downloads, new drops |
| G2 | `/dashboard/orders` | orders with license state badges, renewal dates, Paddle receipt links |
| G3 | `/dashboard/tickets` → new (billing) → submit | appears as open; admin alert in log; rate limit after 5/min |
| G4 | Reply in thread | message appends; admin inbox shows pending |
| G5 | `/dashboard/affiliate` → accept terms → join | referral link `/r/{code}` card, zeroed stats |
| G6 | `/dashboard/team` (after E8) | seat usage, members, invite form |
| G7 | `/dashboard/submissions` → submit component | appears pending; admin alert in log |

## 10. Manual matrix H — admin panel (`/admin`)

| # | Steps | Expected |
|---|---|---|
| H1 | Login as admin | dashboard: KPI row (users, subscribers, MRR, awaiting review), revenue trend, plan mix, latest orders, downloads 30d, top components, system health |
| H2 | Components → a draft → Publish | status flips; preview build queued |
| H3 | Components → Reject with note | note stored |
| H4 | Submissions inbox → Approve | component created `in_review`, library files written, submitter notified; Reject → note + mail |
| H5 | Tickets inbox → filter billing → reply | status → pending; user emailed; order context visible on billing tickets |
| H6 | Notification logs → Resend | re-queued, logged |
| H7 | Blogs → create with categories/tags/SEO/related components → publish | visible on `/blog` with Article JSON-LD |
| H8 | Affiliates → suspend | no new commissions for them; Commissions → void works; AffiliatePayouts → run batch ≥ $50 → mark paid with reference |
| H9 | Settings | plans/limits, refund window, FX, affiliate knobs, feature flags, goals — all save without deploy |
| H10 | PlanPrices | price ladder editable; `paddle_price_id` mapping fields present |

## 11. Manual matrix I — emails & sequences

All mail lands in `storage/logs/laravel.log` locally (search for the subject). Sequences run daily via scheduler — trigger manually with `php artisan mail:run-sequences`.

| # | Trigger | Expected mail |
|---|---|---|
| I1 | Register | one Welcome (exactly once — regression-tested) |
| I2 | Order paid (Paddle) | license summary (EN); domestic → zh payment-confirmed |
| I3 | Order past_due | dunning touch 1 of 5, update-payment link to `/settings/billing` |
| I4 | Cancel | confirmation with access-until + reactivation link; day-7/30 followups scheduled |
| I5 | Domestic near expiry | zh renewal reminders T-7/T-3/T-1/+1/+7 |
| I6 | Affiliate events | conversion credited / commission payable / payout sent |
| I7 | Marketing mail footer | one-click unsubscribe works logged-out; transactional mail still arrives after unsubscribing |

## 12. Manual matrix J — affiliate attribution (end-to-end)

| # | Steps | Expected |
|---|---|---|
| J1 | User A joins affiliate program | code + link issued |
| J2 | Incognito: open `/r/{code}` | 301 to pricing; click recorded (admin) |
| J3 | Register User B in that session | referral linked to B |
| J4 | User B buys (sandbox) 🔑 | pending commission at configured rate in admin + User A's dashboard; conversion email to A |
| J5 | `php artisan affiliates:mark-payable` after refund window + holding (or travel in tests) | commission → payable; email to A |
| J6 | Admin runs payout batch, marks paid | payout row with reference; payout-sent email; A's dashboard shows paid |

## 13. Manual matrix K — domestic payments 🔑 (Alipay/WeChat sandbox)

| # | Steps | Expected |
|---|---|
| K1 | Switch currency on `/pricing` to CNY (or zh browser locale) | CNY prices from `plan_prices`; checkout routes domestic |
| K2 | `/pay/domestic/{order}` | QR renders (desktop) / wake-up link (mobile UA); status polling active |
| K3 | Pay in provider sandbox | notify activates order; zh confirmation email; dashboard shows Active |
| K4 | Refund via admin | provider refund; order Refunded |

## 14. Manual matrix L — AI features 🔑 (flags + `OPENAI_API_KEY`)

| # | Steps | Expected |
|---|---|
| L1 | Enable `features.ai_search` → `/search?q=a pricing section with three cards` + AI mode | AI-assisted strip with refined keywords + filter chips; relevant components |
| L2 | Remove the key → retry | silent fallback to plain results (never a 500) |
| L3 | Enable `features.ai_variants` → admin component → Generate variant | queued job → new `in_review` draft variant linked to original, AI-credited; failure shows danger notice, no partial component |

## 15. `saas-skeleton` branch — quick acceptance matrix

Clone the branch, `composer install && npm ci && npm run build`, `.env` → sqlite, `migrate:fresh --seed`, `npm run dev`.

| # | Steps | Expected |
|---|---|---|
| S1 | `/` | marketing landing (hero + 3 cards), no catalog links |
| S2 | `/pricing`, `/login`, `/register`, `/blog`, `/docs`, `/terms`…(all 8 legal) | all render |
| S3 | Register → dashboard | plan card + orders + welcome block; **no** projects/downloads blocks |
| S4 | `/admin` | all chassis resources present (Orders, PlanPrices, Users, Blogs, Tickets, NotificationLogs, Affiliates×3, Collections **absent**, Components **absent**) |
| S5 | `php artisan test --compact` | 270 passed |
| S6 | Sitemap | only home/pricing/blog/docs/legal/affiliate-terms |

---

## 16. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Blank page, HTTP 200 | JS crash in hydration | open devtools console; report the first error (example fixed: blog paginator `meta` shape) |
| Preview modal spins forever | previews not built / worker off | `php artisan queue:work --stop-when-empty`; check `preview_built_at` |
| Export stuck "pending" | queue worker not running | `npm run dev` includes one; check `jobs` table |
| Mail "not sent" | `MAIL_MAILER=log` in dev | read `storage/logs/laravel.log` |
| 403 on paid download | expected gating | buy a plan (or grant an order in tinker) |
| Verification wall | flag flipped on | `REQUIRE_EMAIL_VERIFICATION=false` in `.env` |
| Assets 404 / unstyled | no build or stale `public/hot` | `npm run build`; `rm public/hot` if vite isn't running |
| Checkout overlay won't open | missing Paddle keys | expected locally — needs sandbox keys 🔑 |
| AI search does nothing | flag off or no key | expected fallback to plain search |

---

*Automated coverage note: everything in §3–§5 has PHPUnit + Playwright backing (552 PHPUnit, 11 browser specs on `main`). The matrices above are for human verification of UX, third-party integrations, and fresh environments.*
