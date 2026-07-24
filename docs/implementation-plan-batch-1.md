# Implementation Plan — Feature Batch 1 (OAuth, Tickets, Projects, Editor, Sidebar)

| | |
|---|---|
| **Version** | 1.0 |
| **Date** | 2026-07-24 |
| **Status** | Approved for implementation — defaults locked by owner |
| **Source** | Owner's 7-item request (2026-07-24) |
| **Conventions** | Same as `docs/project-phases.md`: PHPUnit feature tests as acceptance criteria, factories, sqlite `:memory:`, Pint, `php artisan make:*`, Filament/Laravel best practices |

## Locked decisions

1. Social login with an **email collision auto-binds** to the existing account.
2. Project `type` (**nextjs | nuxtjs**) is chosen once at creation and **locks the export framework**: nextjs → Next scaffold + React pack; nuxtjs → Nuxt scaffold + Vue pack.
3. CodeMirror 6 for **both** the Edit tab (editable) and the Code display tab (read-only), reusing the dependency family `@vue/repl` already ships.
4. WeChat OAuth ships as **config placeholder + docs**; Google + GitHub are the live drivers (WeChat requires a verified 微信开放平台 account — activate later without code changes).

## Implementation order

`F7 → F4 → F2 → F5 → F3 → F6 → F1` — smallest first, one commit per feature, suite green after each.

---

## F7 — Remove "Repository" from sidebar footer

**Scope:** `resources/js/components/app-sidebar.tsx` lines ~49-52 (starter-kit leftover linking to `laravel/react-starter-kit`).
**Change:** delete the item; keep any other footer links unless told otherwise.
**Acceptance:** manual visual (trivial); no test.

---

## F4 — Project list as a bordered table

**Current:** `resources/js/pages/dashboard/projects/index.tsx` renders card/list layout.
**Changes:**

- Replace list with a bordered table: **Name · Type (badge) · Components (direct + dependencies counts) · Created · Actions** (open, export, rename, delete).
- Horizontal scroll on mobile; match existing table styling conventions (see admin/blog tables for classes).
- Controller: include `type`, `direct_count`, `dependency_count` per project in props (withCount on the pivot, split by `is_dependency`).

**Acceptance:** extend `Projects/ProjectPagesTest::test_list_and_detail_render_with_props` to assert the new per-project fields (`type`, counts) are present in list props.

---

## F2 — Ticket attachment thumbnails + modal carousel

**Current:** attachments stored on private disk as JSON `[{name, path, size}]` (max 3 × 5 MB); `tickets/show.tsx` renders plain filename text only.

**Backend changes:**

- New owner-scoped streaming route: `GET /dashboard/tickets/{ticket}/messages/{message}/attachments/{index}` — 403 for non-owner, 404 for missing; streams with the stored/original mime (image types inline).
- `TicketController@show` attachment props gain `url` + `is_image` (detected from extension whitelist: jpg/jpeg/png/gif/webp).
- Admin side (Filament thread view) gets the same URL via a follow-up admin-guarded route — **flagged as follow-up**, not in this batch.

**Frontend changes (`tickets/show.tsx`):**

- Image attachments render as a thumbnail grid (`object-cover` cards); non-images keep a file card with download link.
- New `attachment-carousel.tsx` modal: click thumbnail → fullscreen viewer; prev/next buttons; keyboard `Esc` close, `←`/`→` navigate; backdrop click closes; shows `name` + `n/total` counter; collects **all** image attachments of the ticket thread into one carousel sequence.

**Acceptance:** `Support/TicketAttachmentTest::test_owner_can_stream_image_attachment`, `test_other_user_cannot_stream_attachment_403`, `test_attachment_props_include_url_and_image_flag`, `test_non_image_attachment_has_no_image_flag`. Carousel interaction is manual-QA (see `docs/browser-testing.md` matrix G).

---

## F5 — Disable community submissions behind a feature flag

**Changes:**

- New settings key `features.community_submissions` (default **false**) + Settings page FIELD_MAP entry (Admin → Settings toggle to re-enable later).
- Route group for `/dashboard/submissions`: abort 404 when the flag is off (check in controller or a tiny middleware — follow `features.live_edit` precedent from 3.1).
- Sidebar "Submissions" item hidden when off: share the flag via `HandleInertiaRequests` shared props (`features.community_submissions`), hide client-side.
- Filament `ComponentSubmissions` resource stays visible (admin retains history/review).

**Existing-test impact:** `tests/Feature/Submissions/ComponentSubmissionTest` + `Admin/SubmissionResourceTest` must enable the flag in setup (`Settings::set('features.community_submissions', true)`) — admin-side tests unaffected except where they hit user routes.

**Acceptance:** `Submissions/SubmissionFlagTest::test_routes_404_when_flag_off`, `test_sidebar_prop_false_when_flag_off`, `test_submission_flow_works_when_flag_on`.

---

## F3 — Project type at creation (single choice, locked)

**Backend changes:**

- Migration: `projects.type` string, `ProjectType` enum (`Nextjs = 'nextjs'`, `Nuxtjs = 'nuxtjs'`); existing dev rows default `'nextjs'` (document for production).
- `StoreProjectRequest`: `type` required, `Rule::in`; `UpdateProjectRequest`: `type` excluded (immutable).
- Export flow locks framework: `ProjectExportController` (pack zip → react for nextjs, vue for nuxtjs) and `ProjectScaffoldController` (next for nextjs, nuxt for nuxtjs) ignore any `framework` input and derive from `project.type`; GitHub export dialog likewise.

**Frontend changes:**

- Create form: required single-choice selector (two cards or radio: **Next.js** / **Nuxt**).
- List (F4 table) + detail: type badge; export section shows the derived framework instead of a selector.

**Acceptance:** `Projects/ProjectTypeTest::test_type_required_at_creation`, `test_type_stored_and_displayed`, `test_type_immutable_on_update`, `test_pack_export_framework_derived_from_type`, `test_scaffold_framework_derived_from_type` (extend existing PackZip/scaffold suites where cleaner).

---

## F6 — CodeMirror 6 colored editing (Edit + Code tabs)

**Current:** Edit tab = plain textarea (3.1, deliberately dependency-free); Code display tab = uncolored `<pre>` text. Vue edit side already colored via `@vue/repl`'s bundled CodeMirror.

**Deps (sanctioned):** `@codemirror/view`, `@codemirror/state`, `@codemirror/language`, `@codemirror/lang-javascript` (jsx/tsx), `@codemirror/lang-json`, `@codemirror/lang-vue` (optional for future), a theme package or custom minimal theme matching the neutral Tailwind palette. Reuse versions compatible with what `@vue/repl` vendors.

**Changes:**

- New lazy `code-mirror-editor.tsx` wrapper (dynamic `import()` — must NOT enter the main bundle; verify chunk split in build output like 3.1's esbuild chunk).
- **Edit tab** (`edit-tab.tsx`): textarea → CM6 (editable, jsx/tsx + json languages, tab-key handling, debounced onChange feeding the existing re-render pipeline).
- **Code tab**: read-only CM6 viewer component (client-only mount via dynamic import; SSR-safe guard).
- **Playwright** (`tests/Browser/live-edit.spec.ts`): add assertion that `.cm-editor` mounts and an edit through CM re-renders without error overlay (extends existing specs).

**Acceptance:** `Library/LiveEditTest` stays green (payload contract unchanged); `BrowserSuiteTest`-style check that the CM wrapper file exists; Playwright `.cm-editor` assertions pass; build output shows CM in a lazy chunk, not the main bundle.

---

## F1 — Social OAuth (Google, GitHub, WeChat-ready)

**Backend changes:**

- Migration `social_accounts`: `user_id` FK cascade · `provider` string · `provider_user_id` string · `timestamps` · unique `(provider, provider_user_id)` · index `user_id`.
- `App\Models\SocialAccount` + `User::socialAccounts()` HasMany.
- `App\Services\Auth\SocialAuthService::resolveUser(provider, providerUser)`: find by social account → find by email (**auto-bind**: attach social account to the existing user) → create user with `email_verified_at` pre-set (provider-verified mailboxes).
- `App\Http\Controllers\Auth\SocialAuthController`: `redirect(provider)` + `callback(provider)`; routes `GET /auth/{provider}/redirect|callback`, guest-only; **404 for unconfigured providers** (config check) and for `wechat` until activated.
- `config/services.php`: `google` + `github` (login keys) + `wechat` blocks from env; `.env.example` placeholders: `GOOGLE_CLIENT_ID/SECRET`, `GITHUB_CLIENT_ID/SECRET` (note: may share the repo-export OAuth app — different scopes per flow: `user:email` for login vs `repo` for export), `WECHAT_CLIENT_ID/SECRET` (documented inactive).
- **WeChat**: composer `socialiteproviders/wechat` registered only when package installed + keys present; otherwise hidden. Requires verified 微信开放平台 account (see `docs/launch-runbook.md` Phase A note).
- New users via social login fire the same `Registered` flow (welcome mail + sequence engine).

**Frontend changes:**

- Login + register pages: "Continue with Google/GitHub" buttons rendered **only for configured providers** — shared prop `auth.socialProviders: string[]` via `HandleInertiaRequests`; divider "or continue with email".

**Acceptance (`Auth/SocialAuthTest`):** `test_google_redirect_when_configured`, `test_callback_creates_new_user_with_verified_email`, `test_callback_auto_binds_when_email_exists`, `test_returning_social_user_logs_in`, `test_unconfigured_provider_404`, `test_buttons_hidden_when_unconfigured` (Socialite fakes per 3.6 precedent).

---

## Definition of done (every item)

- Listed acceptance tests green + no regressions (`php artisan test --compact` chunked).
- `vendor/bin/pint --dirty --format agent` clean; prettier/eslint on touched tsx; `npm.cmd run build` after frontend changes.
- One commit per feature: `feat: … (Fx)`.
- `docs/browser-testing.md` matrices updated where behavior changed (G for F2/F4/F3, A/D for F1, C for F6).

## Out of scope (this batch)

- Filament-side attachment viewing (admin thread) — flagged follow-up.
- WeChat OAuth activation (blocked on 开放平台 verification, config-only later).
- Social account management UI on `/settings/connections` (list/unlink) — natural follow-up.
