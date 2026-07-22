# SaaS Skeleton

A Laravel 13 + Filament 5 + Inertia React 19 + Tailwind 4 skeleton that keeps
the **monetization & ops chassis** of a production SaaS and drops the product
it used to ship. Start a new product here without rewriting billing, auth,
admin, mail, blog, affiliate or support.

- PHP 8.4 · Laravel 13 · Filament 5.7 · Inertia React 19 · Tailwind 4 · PHPUnit 12
- Tests run plain against sqlite `:memory:`: `php artisan test --compact`
- Build: `npm run build` · Dev: `npm run dev` (PHP server + Vite + queue worker)

---

## What's included

### Auth & accounts
- Register / login / password reset / email verification (env-flagged via
  `REQUIRE_EMAIL_VERIFICATION`, see `EnsureEmailIsVerified`).
- Settings pages: profile, password, appearance, notifications (preference
  center), billing (cancel flow), connections (GitHub OAuth — see below).
- `User` model with orders, organizations, tickets, affiliate, GitHub
  connection relations; `notification_preferences` JSON column.

### Billing
- Plans (`OrderPlan`: free/starter/pro/team) driven by the `plan_prices`
  table — **prices are never hardcoded**; admins reprice from the panel.
- `EntitlementService` → immutable `Entitlement` (effective plan + `isPaid`),
  resolved from the order state machine (Active / PastDue grace /
  Cancelled-until-ends_at entitle; everything else is Free). Team seats
  inherit the owner's team order.
- Order state machine + `OrderObserver`, Paddle (Cashier, checkout pages,
  webhook, refunds, welcome mail), domestic payments (Alipay/WeChat gateway,
  QR page, notifies, manual-renewal B5 mails, refunds, FX normalization).
- Pricing page (`PricingController` — swap its taglines/comparison/FAQ copy
  for your product), orders page, B7 cancel flow with exit survey + save
  offers, B6 dunning.
- **Team tier**: organizations, per-seat pricing, seats on orders,
  invitations with signed accept links, member management.

### Lifecycle email engine
- Sequence registry/runner/`mail:run-sequences` command (scheduled daily),
  `sequence_sends` idempotency, marketing/transactional split
  (`MarketingNotification` + `NotificationCategory`), preference center,
  signed one-click unsubscribe, notification log.
- Sequences shipped: **B3 paid onboarding**, **B5 domestic renewal
  reminders (zh)**, **B6 dunning**, **B7 cancel followups** + the
  transactional Day-0 `WelcomeNotification` and order/ticket/affiliate/admin
  notifications.

### Content & growth
- **Blog**: posts, categories, tags, public pages, RSS feed, Filament
  resource, Scout-searchable (collection driver locally, Meilisearch in
  production).
- **Docs system**: file-based markdown under `docs/content/`, nav in
  `config/docs_nav.php`, SSR pages with TOC + prev/next, file-based search.
  Ships one getting-started page — add your own sections.
- **Affiliate program**: tracking links (`/r/{code}`), first-party cookie
  attribution, commissions (incl. renewal window), payouts, dashboard,
  Filament admin, mails, `/affiliate-terms`.
- **Support tickets**: user pages + threaded replies + Filament inbox,
  attachments, mail on create/reply/resolve, takedown category.

### Admin (Filament panel at /admin)
- Resources: Orders, PlanPrices, Users, Blogs (+taxonomies), SupportTickets,
  NotificationLogs, Affiliates/Commissions/Payouts.
- Settings page: refund window, FX rate, affiliate knobs (register your own
  keys in `App\Support\Settings`).
- Widgets: revenue stats, revenue trend, plan mix, latest orders, system
  health (failed queue jobs).

### Legal & ops
- 8 legal pages from markdown (`resources/legal/`), settings-token
  interpolation (`{{ refund_window_days }}`), footer nav from the registry.
- Docker (`compose.yaml` + `docker/`), CI workflows (`.github/workflows`),
  `scripts/dev-server.mjs`, Playwright smoke spec (home renders),
  Sentry/backup/health endpoints, rate limits, queue + scheduler,
  sitemap.xml + robots.txt (home, pricing, blog, docs, legal, affiliate-terms).

### GitHub connection (kept deliberately)
The standalone `/settings/connections` GitHub OAuth connection
(`GithubConnection` model, `ConnectionsController`,
`GithubConnectedNotification`) is kept as the skeleton's connected-accounts
example: Socialite handshake + encrypted token storage, no product
coupling. Adjust the OAuth scopes (`repo` today) in
`ConnectionsController::redirect` for your use case, or drop the whole
feature — it is self-contained (model, migration, controller, notification,
settings page, tests).

---

## What was removed

The component-catalog product, end to end:

- Catalog domain: `Component`, `Category`, `Tag`, `ComponentEvent`,
  `ComponentFork`, `Collection`, `DocsPage`, `ComponentSubmission` models,
  their migrations/factories/pivots and catalog-only enums.
- Library pipeline: `library/` tree, `Services/Library` (sync, scanner,
  composition graph, preview builders, screenshotter), preview jobs, preview
  storage, `LibrarySyncRun`/`PreviewBuildFailure`.
- Export/scaffold: Projects + exports, zippers, scaffold templates,
  `BuildProjectPackZip`/`BuildProjectScaffold`, GitHub repo export
  (`GithubClient`, export controller/dialog).
- Live edit + AI: live-edit payloads/runtimes, `@vue/repl`/`vue`/
  `esbuild-wasm`/`@tailwindcss/browser` npm deps, `laravel/ai` +
  `Services/Ai` + `config/ai.php` + agent conversations, community
  submissions pipeline.
- Catalog frontend: home catalog grid, components/industries/collections/
  search pages, preview modal, catalog controllers + admin resources,
  popularity widgets, catalog email sequences (B1/B2/B4/B9/B10).
- Product planning docs (PRD/SPEC/phases/runbook) and catalog fixtures.

## Start a new product

1. **Clone this branch** and rename:
   - `APP_NAME` in `.env` (+ `VITE_APP_NAME`), `name` in `composer.json`,
     and `public/brand/logo.png`.
   - Product copy lives in: `HomeController` (+ `resources/js/pages/home.tsx`),
     `PricingController` (taglines/comparison/FAQ), `resources/legal/*.md`,
     `docs/content/**`.
2. **Plans & prices**: plans are the `OrderPlan` enum (free/starter/pro/team —
   rename cases if your tiers differ, they flow through checkout, admin and
   mails); seed real prices via `PlanPriceSeeder` or the admin panel
   (PlanPrices resource). Wire Paddle price IDs before taking payments.
3. **Build your product** on the extension points:
   - **Entitlements**: add capability methods to `App\Services\Billing\Entitlement`
     (e.g. `canUseFeatureX()`) and expose them in
     `HandleInertiaRequests::entitlements()`.
   - **Dashboard**: `DashboardController` + `resources/js/pages/dashboard.tsx`
     — replace the welcome placeholder with your widgets.
   - **Sequences**: implement `SequenceDefinition`, register in
     `AppServiceProvider`'s `SequenceRegistry`; pick a `NotificationCategory`
     and the preference center/unsubscribe work for free.
   - **Settings keys**: register in `App\Support\Settings::DEFAULTS`, add a
     field on the Filament Settings page, read via `app(Settings::class)->get()`.
   - **Docs**: markdown in `docs/content/{section}/{page}.md` + one entry in
     `config/docs_nav.php`; sitemap picks it up automatically.
   - **Legal**: edit `resources/legal/*.md`; the 8 routes, footer nav and
     sitemap already exist.
4. **Routes**: add product routes to `routes/web.php` — public SSR zone on
   top, auth CSR zone in the `auth+verified+ssr.skip+noindex` group.
5. Keep the gates green: `php artisan test --compact`, `npm run build`,
   and the Playwright smoke spec (`npm run test:browser`).

## Sync policy

This skeleton is a **one-way fork**, not an upstream you merge from. Take it,
rename it, and let it diverge. If chassis fixes land upstream later, cherry-pick
them by area (billing/email/admin are cleanly separated under
`app/Services/{Billing,Sequences,Notifications}`, `app/Filament`, and the
`settings`/`dashboard` route groups) rather than attempting whole-tree merges.
