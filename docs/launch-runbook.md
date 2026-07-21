# FrontendParts — Go-Live Runbook

| | |
|---|---|
| **Version** | 1.0 |
| **Date** | 2026-07-22 |
| **Scope** | Every manual step between the finished codebase (Phases 1–5, 112/112 tasks) and a production launch, in execution order. |
| **How to use** | Work top to bottom. Phases A–C (external accounts) can run in parallel with D–G (server + config). H–J are strictly ordered. Check items off as you go. |

> **Automated safety net already in CI/tests:** `Ops/StagingSmokeTest` (home/catalog/component/pricing 200s), `Ops/HealthTest` (`/up`), `Ops/MailConfigTest` (production mailer ≠ `log`), `Ops/DeploymentReadinessTest`, `Security/*` (rate limits, webhook signatures, sandbox audit, token encryption), `Performance/CacheHeaderTest`, and the Playwright suite (`npm run test:browser`, 11 specs). Re-run them against staging before Phase J.

---

## Phase A — Third-party accounts to create

### A.1 Paddle (international card payments — merchant of record)
- [ ] Create a **Paddle account** (vendor verification takes days — start early).
- [ ] Complete business verification + payout bank details.
- [ ] Create **products + prices** for the 12 Paddle rows (amounts must match `plan_prices` exactly — see C.2):
  - Starter: $9 mo / $24 qtr / $72 yr / $149 lifetime
  - Pro: $15 mo / $36 qtr / $108 yr / $299 lifetime
  - Team (per seat): $13.50 mo / $36 qtr / $108 yr / $270 lifetime
- [ ] Note: subscriptions for mo/qtr/yr; one-time products for lifetime.

### A.2 Alipay (domestic CNY)
- [ ] Register on ** Alipay 开放平台** with the 个体工商户 entity; create a 网页&移动应用 and enable **电脑网站支付** (precreate/QR) + **手机网站支付** (wake-up).
- [ ] Generate RSA2 key pair; upload 应用公钥; download 应用公钥证书 / 支付宝公钥证书 / 支付宝根证书.
- [ ] Sandbox first: 支付宝沙箱 app + sandbox buyer account.

### A.3 WeChat Pay (domestic CNY)
- [ ] Register a **微信支付商户号** (same entity); bind a 公众号/开放平台应用 (for `WECHAT_APP_ID`).
- [ ] Set the **API v3 key**; download merchant API certs (`apiclient_key.pem`, `apiclient_cert.pem`); enable **Native 支付** (QR) + **H5 支付** (mobile).

### A.4 GitHub OAuth app (repo export, Pro feature)
- [ ] GitHub → Settings → Developer settings → OAuth Apps → New.
- [ ] Homepage URL: `https://<domain>` · Authorization callback URL: `https://<domain>/settings/connections/github/callback` (must equal `GITHUB_REDIRECT_URL`).
- [ ] Scope used: `repo` (no extra scopes needed).

### A.5 Transactional mail provider — **OPEN RULING 🟡**
- [ ] Pick one: **Resend** or **Postmark** (both have env placeholders; pick documented in SPEC change log once decided).
- [ ] Verify the sending domain (see Phase E for DNS).

### A.6 Optional services
- [ ] **AI provider** (OpenAI or compatible) — required only if enabling `features.ai_search` / `features.ai_variants`.
- [ ] **Meilisearch** host (self-host or cloud) — optional; `collection` driver works without it.
- [ ] **Sentry or Flare** — error tracking (package install + DSN).

---

## Phase B — Production environment (.env)

Everything below has an `.env.example` placeholder with an inline comment. Values to set:

### B.1 Core
- [ ] `APP_NAME` (product name) · `APP_ENV=production` · `APP_DEBUG=false` · `APP_KEY` (`php artisan key:generate`)
- [ ] `APP_URL=https://<domain>` (drives webhook/OAuth/notify URLs derived from it)
- [ ] `DB_*` — production MySQL · `QUEUE_CONNECTION=database` (queue workers required — see D.4)
- [ ] `SESSION_DRIVER` / `CACHE_STORE` per host (database is fine at launch scale)

### B.2 Mail
- [ ] `MAIL_MAILER=resend|postmark|smtp` (+ that provider's key: `RESEND_API_KEY` / `POSTMARK_TOKEN`)
- [ ] `MAIL_FROM_ADDRESS` on the verified domain · `MAIL_ADMIN_ADDRESS` (receives ticket alerts, submission alerts — currently `support@example.com` placeholder)

### B.3 Payments
- [ ] `PADDLE_API_KEY` · `PADDLE_CLIENT_SIDE_TOKEN` · `PADDLE_WEBHOOK_SECRET` (from C.3) · `PADDLE_SANDBOX=true` until Phase J
- [ ] Alipay: `ALIPAY_APP_ID`, `ALIPAY_APP_SECRET_CERT` (app private key, one-line base64), 3 cert paths, `ALIPAY_SANDBOX=true` until Phase J
- [ ] WeChat: `WECHAT_MCH_ID`, `WECHAT_APP_ID`, `WECHAT_MCH_SECRET_KEY` (API v3), secret cert + public cert path, `WECHAT_SANDBOX=true` until Phase J
- [ ] `ALIPAY_NOTIFY_URL` / `WECHAT_NOTIFY_URL` default to `${APP_URL}/pay/domestic/{alipay,wechat}/notify` — must be publicly reachable HTTPS (no auth walls, no basic-auth staging on these paths)

### B.4 Integrations
- [ ] `GITHUB_CLIENT_ID` / `GITHUB_CLIENT_SECRET` / `GITHUB_REDIRECT_URL`
- [ ] AI (optional): `AI_PROVIDER`, `OPENAI_API_KEY` (or other provider key), `AI_MODEL` (blank = provider default)
- [ ] Search (optional): `SCOUT_DRIVER=meilisearch` + `MEILISEARCH_HOST` + `MEILISEARCH_KEY`
- [ ] Error tracking (optional): `SENTRY_DSN` or `FLARE_API_KEY`

### B.5 Library build pipeline (preview builds + screenshots on the server)
- [ ] `LIBRARY_NODE_BINARY` / `LIBRARY_NPM_BINARY` — prod paths (CI default works when node/npm are on PATH)
- [ ] `LIBRARY_CHROME_BINARY` — required for preview **screenshots** (headless Chrome on the host)
- [ ] Queue workers must be able to run `npm` builds (memory ≥ 1 GB recommended)

---

## Phase C — Third-party dashboard wiring

- [ ] **C.1 Paddle webhook**: developer dashboard → Notifications → add endpoint `https://<domain>/paddle/webhook` → subscribe to `transaction.completed`, `transaction.updated`, `subscription.canceled`, subscription payment-failed events → copy the signing secret into `PADDLE_WEBHOOK_SECRET`. (Signature = `Paddle-Signature` HMAC header; replay-safe via `paddle_events`.)
- [ ] **C.2 Paddle price IDs → plan_prices**: Filament admin → **PlanPrices** resource → paste each Paddle `pri_…` price ID into the matching row's `paddle_price_id` (12 Paddle rows). Checkout 404s/fails for rows left null.
- [ ] **C.3 Alipay**: app config → set 授权回调/异步通知 URL `https://<domain>/pay/domestic/alipay/notify`; place the 3 cert files on the server (paths in B.3).
- [ ] **C.4 WeChat Pay**: merchant platform → API安全 → set certs/APIv3 key; 产品中心 → Native/H5 支付 → notify URL `https://<domain>/pay/domestic/wechat/notify`.
- [ ] **C.5 GitHub**: callback URL matches B.4; nothing else server-side.
- [ ] **C.6 Search (if Meilisearch)**: create the instance + API key; after deploy run `php artisan scout:import "App\Models\Component"`, `… "App\Models\Blog"`, `… "App\Models\DocsPage"` and `php artisan scout:sync-index-settings`.

---

## Phase D — Server & deployment

- [ ] **D.1 Runtime**: PHP 8.4 + composer, Node 24 + npm, MySQL 8, (Chrome headless for screenshots).
- [ ] **D.2 Build**: `composer install --no-dev -o` · `npm ci && npm run build` · `php artisan migrate --force`.
- [ ] **D.3 Seed (production-safe only)**: `php artisan db:seed --class=CategorySeeder` and `--class=PlanPriceSeeder`. ⚠️ **Do NOT run the full `db:seed`** (UserSeeder/OrderSeeder/BlogSeeder are dev fixtures). AdminSeeder creates `admin@example.com` / `password` — **change immediately** (or create the admin manually and skip the seeder).
- [ ] **D.4 Processes**:
  - Queue worker daemon: `php artisan queue:work --tries=3` (pack zips, scaffolds, preview builds, fork rebuilds, AI variants, all mail).
  - Scheduler cron: `* * * * * cd <app> && php artisan schedule:run >> /dev/null 2>&1` — drives `mail:run-sequences` (daily), `affiliates:mark-payable` (daily), `db:backup` (daily 03:00).
  - SSR server: `php artisan inertia:start-ssr` (daemonized) — public pages are SSR; without it they fall back to CSR and lose SEO.
  - `php artisan storage:link`.
- [ ] **D.5 Backups**: point the `backups` filesystem disk at durable storage (local path or S3), then verify: `php artisan db:backup` and confirm the dump lands on the disk.
- [ ] **D.6 Health**: `https://<domain>/up` returns 200 (load-balancer/uptime target).
- [ ] **D.7 Caches**: `php artisan config:cache && php artisan route:cache && php artisan event:cache` (re-run on every deploy).

---

## Phase E — DNS & mail authentication

- [ ] Domain A/AAAA records → host; HTTPS cert (Paddle/Alipay/WeChat webhooks **require** valid HTTPS).
- [ ] Mail domain: add the provider's **SPF**, **DKIM**, and **DMARC** records for the `MAIL_FROM_ADDRESS` domain.
- [ ] Deliverability check to Gmail / Outlook / QQ mail (SPEC §16): register a real account → welcome email lands in inbox, not spam.
- [ ] Transactional vs marketing sanity: order a sandbox product → Paddle receipt comes from Paddle, our license email from us (not duplicated).

---

## Phase F — Admin panel configuration (Filament → /admin)

- [ ] **Settings → Plans & limits**: `plans.project_limit.free=1`, `starter=3`, `pro`/`team` = unlimited (null) — confirm intended values.
- [ ] **Settings → Billing**: `billing.refund_window_days=14` (drives refund action + affiliate commission maturation).
- [ ] **Settings → FX**: `fx.cny_to_usd=0.14` — set to a real rate; drives MRR normalization + affiliate threshold on CNY.
- [ ] **Settings → Affiliate**: review the 5 live knobs — `commission_rate=30%`, `cookie_days=30`, `recurring_months=12`, `holding_days=30`, `payout_threshold=$50`.
- [ ] **Settings → Feature flags**: `features.live_edit` (turn on when ready — QA'd by the browser suite), `features.ai_search`/`features.ai_variants` (off until an AI key exists), `features.preview_dark_toggle`, `features.tree_interactions`.
- [ ] **Settings → Goals**: `goals.*` targets (SPEC §8.7) — wire dashboard targets.
- [ ] **PlanPrices**: C.2 price-ID mapping complete for all 12 Paddle rows.
- [ ] Review seeded categories/taxonomy; create launch **Collections** (e.g. "SaaS landing kit") via the Collections resource.

---

## Phase G — Content & legal

- [ ] **`resources/legal/legal-notice.md`**: fill the bracketed placeholders — operator legal name, registered address, contact email, commercial register / VAT IDs.
- [ ] Confirm the **takedown SLA** on the copyright page (currently: acknowledge ≤ 2 business days, substantive response ≤ 5 business days).
- [ ] Legal pages reviewed by counsel (GDPR + CCPA/CPRA + PIPL coverage; affiliate terms; license terms §7.4).
- [ ] Prepare **support macros** (canned replies: refund request, license question, takedown, billing failed, account access).
- [ ] Schedule the **100-component authoring roadmap** (currently 20 published: 15 free / 5 paid).
- [ ] Blog: first 2–3 SEO posts ready (categories/tags set, scheduled publishing works).

---

## Phase H — Pre-flight smoke matrix (staging, all must pass)

Run automated first: `php artisan test --compact` (552 expected) + `npm run test:browser` (11 specs) against staging config.

Then manually click through:

| # | Flow | Pass criteria |
|---|---|---|
| 1 | Register → verify email → login | Welcome email arrives once; dashboard loads |
| 2 | Free component copy + download zip | Works logged-in; events appear in admin |
| 3 | Paid component as free user | Blur gate + 403 upgrade payload; gate_hit recorded |
| 4 | **Paddle sandbox purchase** (Starter monthly, test card) | Overlay opens with real price; webhook activates order (check Paddle dashboard delivery + admin notification log); welcome email arrives once |
| 5 | Cancel subscription from /settings/billing | Survey required → save offer → confirm → access-until email with reactivation link |
| 6 | Refund within 14d (admin) | Paddle adjustment succeeds; order → Refunded; access revoked; refund email |
| 7 | Project: add composite → closure auto-adds; export pack zip | Queued build → download; zip contains components/data/package.json/README |
| 8 | Scaffold Next.js (Pro) + GitHub export | Repo created, single commit, files run (`npm i && npm run build` spot-check) |
| 9 | Domestic QR page (Alipay sandbox) | QR renders; sandbox scan → notify activates order; zh confirmation email; polling endpoint live |
| 10 | Ticket create → admin reply → resolve | Both directions mail; thread link works |
| 11 | Search + docs search + AI search (if enabled) | Results correct; AI mode falls back gracefully without key |
| 12 | Affiliate: join at /dashboard/affiliate → share /r/{code} → sandbox purchase via link | Click recorded; referral linked; commission pending in admin; emails fire |
| 13 | Unsubscribe link in a marketing email | One-click, logged-out, opts out; transactional still arrives |
| 14 | `/r/invalid-code` | Silent redirect, nothing recorded |
| 15 | Sitemap + RSS + legal pages | All render; footer legal links everywhere |

---

## Phase I — QA click-throughs (manual, beyond the browser suite)

- [ ] Live edit (flag on): React + Vue compile in-browser, JSON data edit re-renders, download-of-edits zips, desktop gate on mobile.
- [ ] Structure tree: hover outlines in both preview and edit mode; pin behavior.
- [ ] Save-to-project fork → rebuild progress → fork preview renders.
- [ ] Preview modal on mobile/tablet/desktop viewports.
- [ ] Dunning: set an order past_due (sandbox failed payment) → 5-touch sequence fires across travel days (check notification log).
- [ ] zh renewal reminders render correctly for a domestic order near expiry.

---

## Phase J — Go-live flip (in order)

1. [ ] Paddle: switch to **live** API key + client-side token; **new live webhook endpoint + secret** (sandbox secrets do not carry over); `PADDLE_SANDBOX=false`.
2. [ ] Alipay / WeChat: live certs/keys; `ALIPAY_SANDBOX=false`, `WECHAT_SANDBOX=false`.
3. [ ] `APP_ENV=production`, `APP_DEBUG=false`, mailer → provider, `SCOUT_DRIVER=meilisearch` (if used) + imports run.
4. [ ] Re-cache: `config:cache && route:cache && event:cache`; restart queue workers + SSR.
5. [ ] One **real-money micro-purchase** (cheapest plan) → verify webhook → refund it.
6. [ ] Sitemap ping to search engines; verify `/robots.txt` allows public pages, blocks checkout/dashboard.
7. [ ] Watch for 48h: error tracker, failed-jobs widget (Filament dashboard), notification log, webhook delivery dashboards (Paddle/Alipay/WeChat).

---

## Phase K — Post-launch cadence

- **Daily** (automated): `db:backup` 03:00 (verify weekly restorability), sequences, commission maturation.
- **Weekly**: support SLA review; dunning recovery rate (target 25–35%); new components published per roadmap.
- **Monthly**: affiliate payout batch (Filament → AffiliatePayouts → run batch, ≥ $50 threshold, then mark-paid with reference); MRR/goals vs SPEC §8.7 targets; FX rate sanity.

---

## Open rulings to make before or at launch

1. **Mail provider**: Resend vs Postmark 🟡 (blocks Phase E).
2. **Company/operator identity** for legal notice + Paddle/Alipay/WeChat account applications.
3. **Paddle auto-renewal affiliate commissions**: currently only new-order lifecycles earn (domestic renewals + re-purchases); enabling per-renewal Paddle commissions needs a schema relaxation (unique key on `affiliate_commissions`). Rule: ship as-is or change?
4. **`lucide-vue-next`** is deprecated upstream but pinned per SPEC §2.5 — rule: keep pin, or migrate the registry to the successor package.
5. **Affiliate defaults** (30% / 30d / 12mo / 30d / $50) are live and admin-editable — confirm before affiliates join.

*End of runbook. When a step is automated later (e.g. Paddle price sync via API), check it off here and link the automation.*
