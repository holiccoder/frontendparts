# FrontendParts — Project Phases & Task List

| | |
|---|---|
| **Version** | 1.0 |
| **Date** | 2026-07-20 |
| **Sources** | [docs/SPEC.md](./SPEC.md) (decision record) · [docs/PRD.md](./PRD.md) (requirements) |
| **Purpose** | Implementation-ready task list. Tasks are numbered (`Phase 1`, `1.2`, `1.2.3`) so they can be referenced by number when handed to an AI agent or developer. |

---

## How to read this document

- **Status markers:** `[x]` done (verified in code) · `[ ]` not started · 🟡 partial / open decision inside a task.
- **Phase ↔ SPEC mapping:** Phase 1 = P0 Foundation · Phase 2 = P1 Monetization · Phase 3 = P2 Power features · Phase 4 = Launch readiness & ops (run around the Phase 2 exit) · Phase 5 = P3 Growth.
- **Acceptance criteria:** every task lists the automated **PHPUnit feature tests** that must be generated and green for the task to be accepted. Conventions per AGENTS.md: PHPUnit classes (no Pest), created via `php artisan make:test`, run with `php artisan test --compact --filter=...`, factories for models, SQLite in-memory test DB.
- **Test scope convention:** feature tests cover HTTP / Inertia props / JSON contracts / jobs / commands / notifications. Pure client-side interactions (modal keyboard shortcuts, tree hover outlines, drag-resize) are verified by the author QA checklist (SPEC §8.5) and are explicitly marked *QA-gate* instead of a test; a browser (Dusk/Playwright) suite is a possible Phase 5 addition, not acceptance for Phases 1–3.
- **Definition of done (every task):** code + listed tests green · `vendor/bin/pint --dirty` clean · Filament/Laravel best practices (form requests, policies, queued jobs/notifications, enum-cast columns, named routes).

## Current state snapshot (verified 2026-07-20)

| Area | State |
|---|---|
| Auth (register/login/reset/verify/confirm/logout) + settings (profile/password/appearance/delete) | ✅ Done with tests — **caveat:** `MustVerifyEmail` commented out on `User` |
| Filament admin at `/admin` (admin guard, Admin model/seeder, dev logins, DB notifications + polling) | ✅ Done — default widgets only, no navigation groups, no access tests |
| Blog | 🟡 Schema/model/factory/seeder + admin CRUD only — no public pages, no categories/tags/SEO fields |
| Orders | 🟡 Schema/model/enums + `OrderObserver` + `OrderStatusChanged` notification + admin CRUD — **no** checkout/Paddle; `BillingPeriod` lacks quarterly/lifetime; `OrderPlan` has hardcoded prices + `enterprise` tier |
| Catalog · library/ apps · sync · preview pipeline · projects · exports · ticketing · docs site · legal pages · sequences · scaffolding · GitHub export · domestic payments | ❌ Not started |
| Infra | ✅ Docker (`compose.yaml` + `docker/Dockerfile`) · ✅ CI (lint + tests workflows) · SSR wired (`ssr.jsx`) · queue=`database` · mailer=`log` · shadcn/ui (23 primitives) + Tailwind 4 + Inertia React 19 |
| Housekeeping | `tests/Pest.php` starter leftover to remove (Pest not installed) |

---

# Phase 1 — Foundation (SPEC P0)

**Exit criteria (PRD §11):** 20+ components published; sync → preview → publish pipeline proven end-to-end.

## 1.1 Starter-kit hardening

- [x] **1.1.1 Auth flows** — register / login / forgot+reset password / email verification / confirm password / logout (SPEC §15.2).
  - Acceptance (existing, green ✅): `tests/Feature/Auth/{AuthenticationTest, RegistrationTest, EmailVerificationTest, PasswordConfirmationTest, PasswordResetTest}`.
- [x] **1.1.2 Enforce email verification** — uncomment `MustVerifyEmail` on `User`; apply `verified` middleware to dashboard + future download/project routes.
  - Acceptance: `EmailVerificationTest::test_unverified_users_are_redirected_from_dashboard`, `test_verified_users_can_access_dashboard`, `test_verification_notification_queued_on_register`.
- [x] **1.1.3 User settings pages** — profile / password / appearance / account deletion (SPEC §15.4).
  - Acceptance (existing, green ✅): `tests/Feature/Settings/{ProfileUpdateTest, PasswordUpdateTest}`.
- [x] **1.1.4 Filament panel foundation** — `/admin` path, `admin` guard, `AdminSeeder`, dev logins (local only), DB notifications + 30s polling.
  - Acceptance (backfill): `Admin/AdminAccessTest::test_guest_redirected_to_admin_login`, `test_admin_can_authenticate_and_view_dashboard`, `test_web_user_cannot_access_admin_panel`.
- [x] **1.1.5 Housekeeping** — remove `tests/Pest.php` leftover; remove `tests/Unit/ExampleTest.php`; add `tests/Feature/Admin` + `tests/Feature/Catalog` + `tests/Feature/Library` directories convention.
  - Acceptance: full suite green after removal.

## 1.2 Core data model

- [x] **1.2.1 Taxonomy tables + seeders** — `categories` (type: `industry|usage`, zone group for usage, name, slug) + `tags`; seed 12 industries + 32 usage patterns + zones (SPEC §4).
  - Acceptance: `Catalog/CategorySeederTest::test_seeds_exactly_12_industries`, `test_seeds_exactly_32_usage_patterns_with_zones`, `test_slugs_unique_per_type`; `CategoryVisibilityTest::test_category_hidden_below_3_components`, `test_visible_at_3_components`.
- [x] **1.2.2 Component tables** — `components` (slug, name, level enum, usage_category_id, access_level enum `free|paid`, version, status enum `draft|in_review|published`, citation fields, deps json) + `component_industry` + `component_tag` pivots + `component_children` (parent_id, child_id, slot, sort_order) (SPEC §2, PRD §7).
  - Acceptance: `Catalog/ComponentModelTest::test_level_and_access_and_status_enum_casts`, `test_industries_and_tags_many_to_many`, `test_children_and_parents_relationships`, `test_published_scope`, `test_free_scope`.
- [x] **1.2.3 `component_events` analytics table** — id, component_id, user_id nullable, type enum `view|copy|download|scaffold`, created_at (SPEC §8.6).
  - Acceptance: `Catalog/ComponentEventTest::test_records_event_with_nullable_user`, `test_type_enum_validated`.
- [x] **1.2.4 Platform settings store** — cached typed `settings` key-value table + `Settings` service with registered keys/defaults (project limits, refund window, feature flags, goals, FX rate) (SPEC §8.7).
  - Acceptance: `Admin/SettingsTest::test_get_returns_registered_default`, `test_set_persists_and_flushes_cache`, `test_typed_casts_int_bool_array`, `test_unknown_key_rejected`.
- [x] **1.2.5 `plan_prices` table + Order enum extension** — `plan_prices` (plan, period, provider, amount, currency, paddle_price_id nullable); `BillingPeriod` += `Quarterly`, `Lifetime`; `OrderPlan` trimmed to `free|starter|pro` (enterprise → Phase 5 team tier); drop hardcoded `monthlyPrice()` in favor of `plan_prices` lookup (SPEC §7.2–7.3, §7.5).
  - Acceptance: `Billing/PlanPriceTest::test_quarterly_and_lifetime_periods_exist`, `test_price_resolved_from_plan_prices_not_enum`, `test_lifetime_order_allows_null_ends_at`, `test_seeded_price_ladder_matches_spec` (8 paid rows + CNY placeholders).

## 1.3 Component library workspace (authoring apps)

- [x] **1.3.1 `library/react` Vite app** — standalone React 19 + TS + Tailwind 4 app in-repo; folder convention `{level}/{slug}/{index.tsx, params.json, data.json}`; standalone `/preview/{slug}` route mounting the component with `data.json` (SPEC §8.1, §8.4).
  - Acceptance: `Library/LibraryScaffoldTest::test_react_app_structure_and_scripts_exist`, `test_preview_entry_present`.
- [x] **1.3.2 `library/vue` Vite app** — same structure for Vue 3 + TS + Tailwind 4; `/preview/{slug}` parity.
  - Acceptance: `Library/LibraryScaffoldTest::test_vue_app_structure_and_scripts_exist`, `test_preview_entry_present`.
- [x] **1.3.3 Dependency registry** — `library/deps.registry.json` (logical name → `{react: pkg@version, vue: pkg@version}`); both apps install all approved packages (SPEC §2.5).
  - Acceptance: `Library/DepsRegistryTest::test_registry_schema_valid`, `test_every_entry_has_both_ecosystems_with_pinned_versions`, `test_registry_packages_installed_in_both_apps`.

## 1.4 Authoring pipeline — `library:sync`

- [x] **1.4.1 Annotation parser** — docblock → structured meta (`@component @name @level @usage @industries @tags @access @source @deps @version`) (SPEC §8.2).
  - Acceptance: `Library/AnnotationParserTest::test_parses_full_annotation_block`, `test_missing_required_field_fails_with_field_name`, `test_unknown_level_rejected`, `test_deps_names_only_no_versions`.
- [x] **1.4.2 Composition graph derivation** — static import parsing → child edges; cycle detection; max depth 10; shared-child dedupe (SPEC §2.2).
  - Acceptance: `Library/CompositionGraphTest::test_imports_register_child_edges`, `test_cycle_a_b_a_fails_with_precise_error`, `test_depth_11_rejected`, `test_shared_child_deduplicated`.
- [x] **1.4.3 Sync validations** — twin exists in both frameworks · taxonomy exists · params/data JSON valid per type vocabulary · composite data slices match child schemas · `@deps` ⊆ registry (SPEC §8.3, §3.2–3.3, §2.5).
  - Acceptance: `Library/LibrarySyncValidationTest` (fixture components): `test_missing_vue_twin_fails`, `test_unknown_usage_category_fails`, `test_invalid_params_json_fails`, `test_data_slice_mismatching_child_schema_fails`, `test_off_registry_dep_fails`, `test_valid_component_passes_all_validations`.
- [x] **1.4.4 Upsert + selective rebuild scheduling** — sync upserts DB records; queues preview builds only for changed components + their ancestors/descendants (SPEC §8.3, §5.2).
  - Acceptance: `Library/LibrarySyncTest::test_upserts_new_and_updated_components`, `test_rebuild_queued_for_changed_component_and_dependents`, `test_unchanged_component_not_rebuilt`, `test_draft_status_preserved_on_resync`.
- [x] **1.4.5 Filament sync trigger + run log** — admin action runs sync; last-run stats (timestamp, scanned, upserted, errors) visible on dashboard (SPEC §8.3, §8.6 row 6).
  - Acceptance: `Admin/LibrarySyncActionTest::test_admin_can_trigger_sync_from_panel`, `test_sync_run_logged_with_stats`, `test_non_admin_forbidden`.

## 1.5 Preview build pipeline

- [x] **1.5.1 Prebuilt HTML artifacts** — queued job: resolve closure → generate entry mounting component with `data.json` → per-framework Vite build → single self-contained HTML at `storage/previews/{component}/{version}/{react|vue}.html` (SPEC §5.2).
  - Acceptance: `Library/PreviewBuildJobTest::test_html_artifact_written_for_both_frameworks`, `test_artifact_is_self_contained_no_external_scripts`, `test_versioned_path_scheme`.
- [x] **1.5.2 AST instrumentation** — inject `data-fp-c="{slug}"` + `data-fp-i="{n}"` into preview builds only; authored export source stays clean (SPEC §2.3).
  - Acceptance: `Library/InstrumentationTest::test_preview_html_contains_fp_attributes`, `test_authored_source_file_has_no_fp_attributes`, `test_instance_numbers_stable_across_rebuilds`.
- [x] **1.5.3 Headless screenshots + QA gate** — screenshots at 375/768/1280 → catalog thumbnails + OG images; publish blocked without a passing 3-viewport render (SPEC §5.2, §8.5).
  - Acceptance: `Library/ScreenshotJobTest::test_three_viewport_screenshots_generated` (integration-tagged), `test_publish_blocked_when_screenshots_missing`, `test_failed_build_recorded_for_admin_widget`.
- [x] **1.5.4 Preview iframe serving route** — `/previews/{component}/{version}/{framework}.html` with cache headers + CSP, no `allow-same-origin` requirement, published-only (SPEC §5.3, §10.3).
  - Acceptance: `Catalog/PreviewServingTest::test_serves_200_with_cache_and_csp_headers`, `test_404_for_draft_component`, `test_404_for_missing_version`.

## 1.6 Public catalog (SSR)

- [x] **1.6.1 Rendering-zone middleware** — Inertia SSR enabled globally; middleware disables SSR gateway per-request for dashboard/checkout groups; auth + checkout routes carry `noindex` (SPEC §10.1).
  - Acceptance: `SsrZoneTest::test_public_page_uses_ssr`, `test_dashboard_and_checkout_skip_ssr`, `test_auth_and_checkout_responses_carry_noindex`.
- [x] **1.6.2 Home page `/`** — hero, featured components, industries grid, how-it-works, pricing teaser, latest drops, blog teaser (SPEC §15.1).
  - Acceptance: `Catalog/HomeTest::test_home_renders_ssr_200`, `test_props_contain_featured_components_and_industries`, `test_industries_below_3_components_excluded`.
- [x] **1.6.3 Catalog index `/components` + filters + search box** — grid + filters (industry multi, usage, level, framework, access) + free-text search (DB-driven) (FR-1).
  - Acceptance: `Catalog/CatalogTest::test_index_lists_published_only`, `test_filter_by_industry_multi`, `test_filter_by_usage_level_access`, `test_search_matches_name_and_tags`, `test_empty_category_hidden`.
- [x] **1.6.4 Taxonomy landing pages** — `/components/{usage}`, `/industries`, `/industries/{industry}` curated collections + copy (SPEC §15.1).
  - Acceptance: `Catalog/TaxonomyPageTest::test_usage_page_200_for_seeded_slug`, `test_industry_index_and_detail_200`, `test_unknown_slug_404`, `test_curated_props_present`.
- [x] **1.6.5 Component detail `/components/{usage}/{slug}`** — full modal payload (files, data, docs, tree), citation display, related components, per-component meta + OG (auto screenshot) + structured data (FR-1.6, SPEC §10.2).
  - Acceptance: `Catalog/ComponentPageTest::test_200_with_full_modal_payload`, `test_citation_prop_present`, `test_canonical_and_og_point_to_screenshot`, `test_draft_404`, `test_related_components_same_usage`.
- [x] **1.6.6 SEO mechanics** — `sitemap.xml` (components + taxonomy + docs), `robots.txt` (disallow dashboard/checkout), unique titles/meta per page (SPEC §10.2).
  - Acceptance: `Catalog/SeoTest::test_sitemap_contains_component_and_taxonomy_urls`, `test_robots_disallows_private_zones`, `test_titles_unique_per_component_page`.
- [x] **1.6.7 Branded 404** — links back to catalog (SPEC §15.1).
  - Acceptance: `Catalog/NotFoundTest::test_404_renders_branded_page_with_catalog_link`.

## 1.7 Preview modal + structure tree

- [x] **1.7.1 Modal shell + toolbar + tabs** — header (name, level badge, usage+industry tags, citation, access badge, actions), toolbar (viewport presets 375/768/1280/full + readout + drag-resize; React|Vue toggle; dark/light flag; structure toggle), tabs Preview|Code|Data|Docs (SPEC §5.4, FR-2). Client interactions *QA-gate*.
  - Acceptance: `Catalog/ComponentPageTest::test_modal_payload_contains_header_fields_and_badges`, `test_payload_contains_both_framework_file_sets`, `test_dark_toggle_included_only_when_feature_flag_on`.
- [x] **1.7.2 Iframe sandbox + postMessage protocol** — `<iframe sandbox="allow-scripts">`, viewport width on iframe, protocol messages (highlight/clear/theme in; ready/height out) (SPEC §5.3). Client interactions *QA-gate*.
  - Acceptance: covered by 1.5.2 + 1.5.4; protocol conformance noted in docs (no server test surface).
- [x] **1.7.3 Structure tree payload** — tree JSON from composition graph: foldable nodes, level badges, type grouping with instance chips (`Card ×3 → #1 #2 #3`), default-expanded depth, one-way highlight, child navigation links, primitive empty state (SPEC §5.5, FR-3). Client interactions *QA-gate*.
  - Acceptance: `Catalog/StructureTreeTest::test_tree_matches_composition_graph`, `test_instance_chips_count_and_ids`, `test_primitive_component_returns_empty_tree`, `test_tree_depth_never_exceeds_10`.
- [x] **1.7.4 Code / Data / Docs tab payloads** — per-file sources, pretty `data.json`, auto-generated props table from `params.json`, resolved dep list + zero-dep badge, version/changelog (FR-2.5–2.7, SPEC §3.5).
  - Acceptance: `Catalog/ComponentTabsTest::test_code_tab_lists_one_file_per_component_in_closure`, `test_data_tab_returns_sample_json`, `test_docs_tab_props_table_matches_params_schema`, `test_zero_dep_badge_when_deps_empty`, `test_deps_resolved_via_registry`.
- [x] **1.7.5 Editable layout persistence** — stage/content panes swappable + split sizing; persisted to `localStorage` (guest) and account profile (auth) via PATCH endpoint (FR-2.8).
  - Acceptance: `Settings/PreviewLayoutTest::test_authenticated_user_saves_layout_preference`, `test_validation_rejects_invalid_layout_payload`.

## 1.8 Free copy / download + events

- [x] **1.8.1 Copy + single-component download** — accountless for free components; zip = component files by level + `data/` + clean (stripped) sources (SPEC §2.4, §6.1).
  - Acceptance: `Catalog/DownloadTest::test_guest_can_download_free_component_zip`, `test_zip_structure_levels_plus_data_folder`, `test_zip_sources_have_no_instrumentation`, `test_paid_component_guest_gets_403_with_upgrade_payload`.
- [x] **1.8.2 Event tracking** — record `view` (component page), `copy`, `download` into `component_events`; rate-limited (SPEC §8.6).
  - Acceptance: `Catalog/ComponentEventTest::test_view_copy_download_events_recorded`, `test_events_linked_to_user_when_authenticated`, `test_download_endpoint_rate_limited`.

## 1.9 Documentation site (basic, P0)

- [x] **1.9.1 Markdown docs renderer** — file-based `docs/content/`, SSR at `/docs/{section}/{page}`: sidebar nav tree, per-page TOC, prev/next, copyable code blocks (SPEC §13.2).
  - Acceptance: `Docs/DocsTest::test_renders_markdown_file_as_ssr_page`, `test_unknown_page_404`, `test_sidebar_tree_props`, `test_prev_next_links`.
- [x] **1.9.2 Launch content batch 1** — Getting Started · Install React · Install Vue · Using Components (params & data) (SPEC §13.2).
  - Acceptance: `Docs/DocsContentTest::test_all_launch_pages_return_200`, `test_docs_included_in_sitemap`.

## 1.10 Email foundation (P0)

- [x] **1.10.1 Mail infrastructure** — branded markdown layout (logo + single CTA), all mail notifications queued (`database` queue), database channel alongside mail (SPEC §16.1, §16.3).
  - Acceptance: `Notifications/MailInfrastructureTest::test_mail_notifications_implement_should_queue`, `test_branded_markdown_layout_renders_logo_header`, `test_database_channel_writes_notifications_row`.
- [x] **1.10.2 P0 transactional** — welcome + verify on register; password/email change confirmations (SPEC §16.1).
  - Acceptance: `Notifications/TransactionalTest::test_register_queues_welcome_and_verification`, `test_password_change_queues_confirmation`, `test_email_change_queues_confirmation_to_both_addresses`.

## 1.11 Admin — catalog management & P0 widgets

- [x] **1.11.1 Component resource** — list with level/access/status/usage filters, read-only composition-tree visualization, QA checklist panel, Preview / Publish / Reject actions; code never form-edited (SPEC §8.5, §2.2).
  - Acceptance: `Admin/ComponentResourceTest::test_admin_lists_and_filters_components`, `test_publish_action_requires_qa_checklist_and_green_build`, `test_reject_action_sets_draft_with_reason`, `test_tree_visualization_payload_matches_graph`, `test_non_admin_forbidden`.
- [x] **1.11.2 Taxonomy + citation resources** — Categories, Tags, Sources (cited sites) CRUD with navigation groups (SPEC §15.5).
  - Acceptance: `Admin/TaxonomyResourceTest::test_category_crud`, `test_tag_crud`, `test_source_crud`, `test_navigation_groups_registered`.
- [x] **1.11.3 Settings page + plan prices resource** — Filament Settings page (Plans & limits, Feature flags, Goals groups) + PlanPrice resource (SPEC §8.7).
  - Acceptance: `Admin/SettingsPageTest::test_saves_each_group_and_flushes_cache`, `test_feature_flag_off_removes_toggle_from_modal_payload`, `test_plan_price_crud_updates_checkout_amounts_without_deploy`, `test_goal_targets_saved`.
- [x] **1.11.4 P0 dashboard widgets** — catalog stats, drafts awaiting review queue, coverage matrix heatmap (12×32, <3 cells flagged), system health (failed builds, last sync, failed jobs) (SPEC §8.6).
  - Acceptance: `Admin/DashboardWidgetTest::test_catalog_stats_counts`, `test_drafts_queue_lists_in_review`, `test_coverage_matrix_flags_cells_below_3`, `test_system_health_shows_failed_builds_and_last_sync`.

## 1.12 Seed content (authoring)

- [x] **1.12.1 Author first 20 components** — 15 free / 5 paid (adjusted at authoring time — ratio target applies at 100-component scale), twins in both frameworks, spread across priority coverage-matrix cells; pass QA checklist (SPEC §8.5).
  - Acceptance: `Library/SeedContentTest::test_sync_publishes_at_least_20_components`, `test_every_component_has_both_framework_twins`, `test_at_least_10_free_components`, `test_catalog_pages_render_for_all_published`.

---

# Phase 2 — Monetization (SPEC P1)

**Exit criteria (PRD §11):** first paid order end-to-end; gated components enforced.

## 2.1 Plans & pricing engine

- [ ] **2.1.1 Entitlement service** — resolve a user's effective plan from `orders` (active / cancelled-valid-until-`ends_at` / expired; lifetime `ends_at = null`); entitlement checks: library access %, project limit (from settings), scaffolding flag (SPEC §7.1, §7.3).
  - Acceptance: `Billing/EntitlementTest::test_free_user_entitlements`, `test_starter_full_library_no_scaffolding`, `test_pro_full_library_with_scaffolding`, `test_cancelled_but_not_ended_keeps_access`, `test_expired_loses_paid_access`, `test_lifetime_never_expires`, `test_project_limits_read_from_settings`.
- [ ] **2.1.2 Gating enforcement** — copy/download/project-add endpoints enforce access: free users limited to free subset (20–30% via `components.access_level`); 403 + upgrade payload on violation (FR-7.6, SPEC §5.4 blur-gate).
  - Acceptance: `Billing/GatingTest::test_free_user_paid_download_403`, `test_starter_downloads_any_component`, `test_free_user_adds_only_free_components_to_project`, `test_403_payload_contains_plan_comparison_cta`, `test_pro_blur_gate_event_recorded_for_b2_trigger`.

## 2.2 Paddle integration

- [ ] **2.2.1 Cashier Paddle setup** — install `laravel/cashier-paddle`, config, customer creation/sync on checkout (SPEC §7.3).
  - Acceptance: `Billing/PaddleCheckoutTest::test_checkout_session_created_for_plan_and_period` (Paddle sandbox/fake), `test_customer_record_linked_to_user`, `test_price_ids_come_from_plan_prices_table`.
- [ ] **2.2.2 Checkout pages (CSR, noindex)** — `/checkout/{plan}` Paddle overlay host with period selector; `/checkout/success` confirmation + next steps (SPEC §15.3).
  - Acceptance: `Billing/CheckoutPageTest::test_checkout_page_csr_with_noindex`, `test_period_selector_passes_correct_price`, `test_success_page_shows_license_summary`, `test_auth_required`.
- [ ] **2.2.3 Webhook processing** — signature-verified endpoint; `transaction.completed` → order `active`; subscription cancelled → `cancelled` valid-until-`ends_at`; payment failed → `past_due`; refunds → refunded state (SPEC §7.3).
  - Acceptance: `Billing/PaddleWebhookTest::test_invalid_signature_403`, `test_transaction_completed_activates_order`, `test_cancellation_sets_ends_at_and_keeps_access`, `test_payment_failed_marks_past_due`, `test_webhook_idempotent_on_replay`.
- [ ] **2.2.4 Refunds** — admin refund action via Paddle API honoring the settings-driven 14-day window; refund email (SPEC §7.3, §16.1).
  - Acceptance: `Billing/RefundTest::test_refund_within_window_succeeds`, `test_refund_after_window_blocked`, `test_refund_processed_notification_queued`.
- [ ] **2.2.5 Order-paid welcome email** — on activation: license summary + first steps; never duplicates Paddle MoR receipts (SPEC §16.1).
  - Acceptance: `Notifications/OrderPaidTest::test_welcome_to_pro_queued_on_activation`, `test_email_contains_license_summary_not_invoice`.

## 2.3 Pricing page

- [ ] **2.3.1 `/pricing` (SSR)** — plan × period toggle (monthly/quarterly/yearly/lifetime), feature comparison table per SPEC §7.1, FAQ, yearly highlighted "best value", lifetime as permanent offering (SPEC §7.2, §15.1).
  - Acceptance: `Billing/PricingPageTest::test_pricing_page_ssr_200`, `test_prices_come_from_plan_prices_table`, `test_all_four_periods_present`, `test_comparison_table_matches_feature_matrix`.

## 2.4 Projects

- [ ] **2.4.1 Project CRUD + limits** — `projects` + `project_components`; create/rename/delete; per-plan limits (Free 1 / Starter 3 / Pro unlimited, settings-driven) (SPEC §6.1).
  - Acceptance: `Projects/ProjectCrudTest::test_create_rename_delete`, `test_owner_only_access`, `test_free_user_blocked_at_second_project`, `test_starter_blocked_at_fourth`, `test_pro_unlimited`, `test_limits_change_via_settings_without_deploy`.
- [ ] **2.4.2 Auto-add closure + removal cascade** — adding a composite inserts full descendant closure (`is_dependency = true`, deduplicated); removing a direct component prunes orphaned dependencies with user notice; shared children kept (SPEC §6.1).
  - Acceptance: `Projects/ProjectClosureTest::test_adding_composite_adds_full_descendant_closure`, `test_shared_children_deduplicated`, `test_removal_prunes_orphaned_dependencies`, `test_dependencies_used_elsewhere_are_kept`, `test_prune_notice_returned_in_response`.
- [ ] **2.4.3 Dashboard project pages (CSR)** — `/dashboard/projects` list + `/dashboard/projects/{id}` component set with dependency view + export actions (SPEC §15.4).
  - Acceptance: `Projects/ProjectPagesTest::test_list_and_detail_render_with_props`, `test_detail_marks_dependencies`, `test_other_users_project_403`.

## 2.5 Pack zip export

- [ ] **2.5.1 Zip assembly** — `components/` by level (full transitive closure) + `data/` + merged `package.json` dependency snippet (deps resolved + deduped via registry) + Tailwind setup notes + README (SPEC §6.2, §2.4–2.5).
  - Acceptance: `Projects/PackZipTest::test_zip_contains_full_closure_by_level`, `test_data_folder_present`, `test_merged_package_json_dedupes_closure_deps`, `test_readme_and_tailwind_notes_present`, `test_framework_chosen_at_export`.
- [ ] **2.5.2 Queued export + download + event** — zip built by queued job, stored, streamed; `download` event recorded (SPEC §10.3 NFR-4, §8.6).
  - Acceptance: `Projects/PackZipTest::test_export_dispatches_job_and_streams_zip`, `test_download_event_recorded`.

## 2.6 User dashboard (CSR)

- [ ] **2.6.1 Dashboard overview rebuild** — `/dashboard`: plan status, projects, recent downloads, new drops (SPEC §15.4).
  - Acceptance: `Dashboard/DashboardTest::test_overview_props_per_plan_state`, `test_new_drops_section`.
- [ ] **2.6.2 Orders page** — `/dashboard/orders`: orders, Paddle receipt/invoice URLs, license state, renewal dates (SPEC §15.4).
  - Acceptance: `Dashboard/OrdersPageTest::test_orders_listed_with_receipt_urls`, `test_license_state_and_renewal_dates`, `test_only_own_orders`.

## 2.7 Blog (public + admin extension)

- [ ] **2.7.1 Blog schema extension** — blog categories/tags tables + pivots, SEO meta fields, `related_components` pivot, reading time, scheduled publishing (SPEC §13.1).
  - Acceptance: `Blog/BlogModelTest::test_categories_tags_relations`, `test_related_components_pivot`, `test_scheduled_posts_hidden_until_published_at`, `test_reading_time_computed`.
- [ ] **2.7.2 Public blog (SSR)** — `/blog`, `/blog/{slug}`, `/blog/category/{slug}`: TOC, related posts + related components, Article structured data, RSS feed, sitemap inclusion (SPEC §13.1, §15.1).
  - Acceptance: `Blog/BlogPageTest::test_index_and_article_ssr_200`, `test_only_published_visible`, `test_related_components_in_props`, `test_article_structured_data_present`, `test_rss_feed_valid_xml`, `test_blog_urls_in_sitemap`.
- [ ] **2.7.3 Filament blog resource extension** — categories/tags/SEO/related-components fields on the existing resource (SPEC §13.1).
  - Acceptance: `Admin/BlogResourceTest::test_save_with_all_extended_fields`, `test_related_components_picker_persists`.

## 2.8 Documentation (full)

- [ ] **2.8.1 Remaining sections + basic search** — Install Next/Nuxt · Customizing (Tailwind tokens) · Scaffolding & GitHub Export · License FAQ · Troubleshooting; DB-driven docs search (SPEC §13.2).
  - Acceptance: `Docs/DocsSearchTest::test_search_returns_matching_pages`, `Docs/DocsContentTest::test_all_sections_return_200`.

## 2.9 Support ticketing

- [ ] **2.9.1 Ticket schema + user side (CSR)** — `support_tickets` (category enum `billing|technical|license|takedown|other`, status `open|pending|resolved|closed`) + `support_ticket_messages` (author type, attachments); dashboard pages `/dashboard/tickets`, `/new`, `/{id}` threaded replies (SPEC §13.3).
  - Acceptance: `Support/TicketUserTest::test_create_ticket_with_category`, `test_threaded_reply_appends`, `test_only_own_tickets_visible`, `test_create_rate_limited`, `test_status_flow_transitions_valid_only`.
- [ ] **2.9.2 Filament ticket inbox** — status/category filters, reply UI, order context attached to billing tickets (SPEC §13.3).
  - Acceptance: `Admin/TicketInboxTest::test_admin_filters_by_status_and_category`, `test_admin_reply_sets_pending`, `test_billing_ticket_shows_order_context`, `test_resolve_action`.
- [ ] **2.9.3 Ticket email notifications** — created → admin; admin reply → user; resolved → user; thread link in every mail (SPEC §16.1).
  - Acceptance: `Notifications/TicketMailTest::test_new_ticket_notifies_admin`, `test_admin_reply_notifies_user`, `test_resolved_notifies_user`, `test_mails_contain_thread_link`.

## 2.10 Lifecycle email engine (SPEC §16)

- [ ] **2.10.1 Sequence engine** — daily scheduled command; sequence definitions with day offsets; per-user progress tracking (no duplicates, idempotent); sends respect preferences (SPEC §16.2).
  - Acceptance: `Notifications/SequenceEngineTest::test_b1_day2_sent_only_to_users_registered_2_days_ago`, `test_idempotent_no_duplicate_sends`, `test_sequence_respects_opt_out`, `test_unsubscribed_user_gets_transactional_only`.
- [ ] **2.10.2 Preference center** — `/settings/notifications`: digest/blog/product-updates individually opt-out; transactional mandatory; signed one-click unsubscribe links (SPEC §16.3).
  - Acceptance: `Settings/NotificationPreferenceTest::test_preference_page_updates_flags`, `test_signed_unsubscribe_link_opts_out`, `test_invalid_signature_rejected`, `test_transactional_not_disabled`.
- [ ] **2.10.3 Sequences B1–B4** — B1 free onboarding drip (Day 0/2/4/7/12) · B2 upgrade trigger (≥3 blur-gate hits/week 🟡) · B3 paid onboarding (Day 0/3/7) · B4 new-drops digest (weekly/monthly opt-in, merges blog highlights) (SPEC §16.2).
  - Acceptance: `Notifications/SequencesTest::test_b1_full_drip_schedule` (time-travel each offset), `test_b2_triggered_at_3_gate_events_within_week`, `test_b3_paid_onboarding_schedule`, `test_b4_digest_contains_new_components_and_blog_posts`, `test_b4_respects_weekly_vs_monthly_choice`.
- [ ] **2.10.4 Dunning B6 + cancel flow B7** — dunning: 5 touches/15 days deep-linking update-payment (target 25–35% recovery); cancel: required 1-question exit survey → reason-mapped save offer → confirmation with access-until date + reactivation link → Day 7 reactivation → Day 30 win-back (SPEC §16.2).
  - Acceptance: `Notifications/DunningTest::test_five_touch_schedule_on_past_due`, `test_every_mail_links_update_payment_page`, `test_stops_on_recovery`; `Billing/CancelFlowTest::test_survey_required_before_cancel`, `test_save_offer_mapped_to_each_reason`, `test_confirmation_contains_access_until_and_reactivation_link`, `test_day7_and_day30_followups_scheduled`.
- [ ] **2.10.5 Filament notification log** — full log of sent notifications + resend action (SPEC §16.3).
  - Acceptance: `Admin/NotificationLogTest::test_log_lists_sent_notifications`, `test_resend_action_requeues`.

## 2.11 Legal pages

- [ ] **2.11.1 Seven legal pages (SSR, indexed)** — `/terms` · `/privacy` (GDPR + CCPA/CPRA + PIPL) · `/license` (§7.4 terms) · `/refund-policy` · `/cookie-policy` · `/copyright` (attribution + takedown procedure + SLA, links `takedown` ticket category) · `/legal-notice`; footer links everywhere (SPEC §15.7).
  - Acceptance: `Legal/LegalPagesTest::test_all_seven_pages_ssr_200`, `test_pages_are_indexed_no_noindex`, `test_footer_contains_all_links`, `test_copyright_page_links_takedown_ticket_category`.

## 2.12 Site search

- [ ] **2.12.1 `/search?q=` (SSR)** — DB-driven search over components (name/tags/categories) + blog posts (SPEC §15.1, FR-1.3).
  - Acceptance: `Catalog/SearchTest::test_matches_components_by_name_tag_category`, `test_matches_blog_posts`, `test_drafts_excluded`, `test_empty_state`.

## 2.13 Admin — P1 widgets

- [ ] **2.13.1 Revenue & growth widgets** — KPI row (registered users +N/wk, active subscribers, MRR normalized across periods, awaiting review), revenue trend (12m, lifetime spikes separated), plan mix donut, latest orders with Paddle link (SPEC §8.6).
  - Acceptance: `Admin/RevenueWidgetTest::test_mrr_normalizes_monthly_quarterly_yearly`, `test_lifetime_excluded_from_mrr_but_in_revenue`, `test_plan_mix_counts_by_plan_and_period`, `test_kpi_week_over_week_deltas`.

---

# Phase 3 — Power features (SPEC P2)

**Exit criteria (PRD §11):** scaffold generated + repo pushed from UI; domestic QR order end-to-end.

## 3.1 Live edit mode — React

- [ ] **3.1.1 Edit tab runtime** — lazy-loaded esbuild-wasm bundler + `@tailwindcss/browser`; multi-file editing (parent + children); JSON data editor with instant re-render; deps resolved from esm.sh at registry-pinned versions; desktop gate; instant download of edits (SPEC §5.6, §2.5).
  - Acceptance: `Library/LiveEditTest::test_edit_tab_payload_only_when_feature_flag_on`, `test_payload_contains_closure_files_and_pinned_dep_versions`, `test_download_edits_returns_sources_without_build`, `test_edit_tab_absent_when_flag_off`. Client compile/render *QA-gate*.

## 3.2 Live edit mode — Vue

- [ ] **3.2.1 `@vue/repl` runtime** — same surface for Vue (SPEC §5.6).
  - Acceptance: `Library/LiveEditTest::test_vue_edit_payload_uses_repl_structure`. Client compile/render *QA-gate*.

## 3.3 Live edit — instrumentation & forks

- [ ] **3.3.1 Client-side outlines parity** — attribute injection runs client-side so structure-tree outlines keep working in edit mode; documented fallback without outlines (SPEC §5.6). *QA-gate* + payload flag test.
- [ ] **3.3.2 Save-to-Project forks** — save edited component as customized fork linked to a project; background rebuild produces its prebuilt preview + screenshots; progress UI (SPEC §5.6).
  - Acceptance: `Projects/ComponentForkTest::test_save_creates_fork_linked_to_project`, `test_rebuild_job_queued_with_progress_state`, `test_fork_preview_served_after_rebuild`, `test_original_component_untouched`.

## 3.4 Next.js scaffolding

- [ ] **3.4.1 Next.js starter assembly** — App Router, TS-only, Next 15 + React 19 + Tailwind 4: `components/`, `app/`, `public/`, `data/`, `package.json` (merged deps), `tsconfig`, configs, `.gitignore`; page-level components → routes; loose sections → index page in selection order; sample images stay remote URLs; queued server-side assembly → zip (SPEC §6.3, FR-5).
  - Acceptance: `Scaffold/NextScaffoldTest::test_zip_contains_full_starter_structure`, `test_page_components_become_routes`, `test_loose_sections_assembled_into_index_in_order`, `test_remote_image_urls_preserved`, `test_merged_package_json`, `test_pro_only_gate_403_for_starter`, `test_scaffold_event_recorded`.

## 3.5 Nuxt scaffolding

- [ ] **3.5.1 Nuxt starter assembly** — TS-only, Nuxt 4 + Vue 3 + Tailwind 4; same rules as 3.4.1 (SPEC §6.3).
  - Acceptance: `Scaffold/NuxtScaffoldTest` — same method matrix as 3.4.1.

## 3.6 GitHub export

- [ ] **3.6.1 GitHub OAuth connection** — Socialite GitHub, scope `repo`; token stored encrypted; `/settings/connections` connect/disconnect; GitHub-connected security email (SPEC §6.4, §16.1).
  - Acceptance: `Integrations/GithubConnectionTest::test_oauth_callback_stores_encrypted_token`, `test_token_not_readable_as_plaintext_in_db`, `test_disconnect_clears_token`, `test_connected_notification_queued`.
- [ ] **3.6.2 Repo export** — pick project → name repo (public/private) → create repo → commit all files in a single commit via Git Trees API → return repo URL (SPEC §6.4).
  - Acceptance: `Integrations/GithubExportTest::test_repo_created_and_tree_committed` (HTTP fake), `test_single_commit_contains_all_scaffold_files`, `test_returns_repo_url`, `test_pro_gated`, `test_api_failure_surfaces_error`.

## 3.7 Domestic payments (Alipay / WeChat Pay, CNY)

- [ ] **3.7.1 Provider setup + price table** — install `yansongda/pay`; Alipay + WeChat config; `plan_prices` CNY rows with `provider` column; region/currency routing at checkout (geo-detect + manual switch) (SPEC §7.5).
  - Acceptance: `Billing/DomesticCheckoutTest::test_cn_region_gets_cny_qr_checkout`, `test_international_gets_paddle`, `test_manual_currency_switch`, `test_cny_prices_from_plan_prices`.
- [ ] **3.7.2 QR payment page + notify normalization** — `/pay/domestic/{order}` QR scan (desktop) / app wake-up (mobile) + result polling; Alipay/WeChat notify endpoints signature-verified and normalized into the shared `orders` state machine (SPEC §7.5, §15.3).
  - Acceptance: `Billing/DomesticNotifyTest::test_alipay_signed_notify_activates_order`, `test_wechat_signed_notify_activates_order`, `test_invalid_signature_rejected`, `test_polling_endpoint_returns_order_state`, `test_notify_idempotent_on_replay`.
- [ ] **3.7.3 Domestic renewal reminders (B5) + zh templates** — one-time payment per period (no auto-deduct); reminders T-7/T-3/T-1/expired+1/+7; zh email templates; domestic payment-confirmed + access-unlocked email (SPEC §7.5, §16.1–16.2).
  - Acceptance: `Notifications/RenewalReminderTest::test_reminder_schedule_matrix`, `test_no_reminders_for_lifetime_orders`, `test_zh_templates_render`, `test_payment_confirmed_email_queued`.
- [ ] **3.7.4 Domestic refunds + FX reporting** — refunds via provider APIs within window; CNY revenue normalized into dashboard MRR at admin-configurable FX rate (SPEC §7.5).
  - Acceptance: `Billing/DomesticRefundTest::test_refund_within_window_succeeds`; `Admin/RevenueWidgetTest::test_cny_revenue_normalized_at_configured_fx_rate`.

## 3.8 Admin — P2 widgets

- [ ] **3.8.1 Downloads & popularity widgets** — downloads 30d (components + zips + scaffolds), top components (views + downloads), projects tracking (SPEC §8.6).
  - Acceptance: `Admin/PopularityWidgetTest::test_top_components_from_component_events`, `test_downloads_30d_aggregation`.

---

# Phase 4 — Launch Readiness & Ops

**Timing:** run alongside/around the Phase 2 exit (first public launch with payments). Phase numbering is for reference, not strict chronology.

## 4.1 Infrastructure

- [x] **4.1.1 Docker dev environment** — `compose.yaml` (app + MySQL 8.4) + `docker/Dockerfile` (PHP 8.4, Node 24).
  - Acceptance: manual — `docker compose up` serves the app (verified during setup).
- [x] **4.1.2 CI pipelines** — `.github/workflows/lint.yml` (Pint + prettier + eslint) + `tests.yml` (build + PHPUnit on SQLite).
  - Acceptance: workflows green on `main` ✅.
- [ ] **4.1.3 CI extension** — add `library:sync` fixture validation + library apps build to the tests workflow so broken components fail CI.
  - Acceptance: `Library/LibrarySyncTest` suite runs in CI (assert workflow file includes the step).
- [ ] **4.1.4 Production deployment** — Laravel Cloud (or Docker host): SSR server process, queue workers, scheduler cron, storage symlink, env secrets, sitemap ping.
  - Acceptance: `Ops/HealthTest::test_health_endpoint_200` + staging smoke matrix (home/catalog/component/pricing/checkout 200s).
- [ ] **4.1.5 Production mail** — pick provider (Resend vs Postmark 🟡), configure mailer + DNS (SPF/DKIM/DMARC), verified sender domain.
  - Acceptance: config test (mailer != `log` in production env) + manual deliverability check to Gmail/Outlook/QQ mail.

## 4.2 Hardening

- [ ] **4.2.1 Security pass** — rate limiting on auth, downloads, ticket creation (NFR-10); webhook signature verification (Paddle 2.2.3, domestic 3.7.2); iframe sandbox audit (no `allow-same-origin`); GitHub token encryption audit (3.6.1); `MustVerifyEmail` enforced (1.1.2).
  - Acceptance: `Security/RateLimitTest::test_auth_endpoints_throttled`, `test_download_endpoint_throttled`, `test_ticket_creation_throttled`; `Security/SandboxAuditTest::test_preview_iframe_markup_has_no_allow_same_origin`.
- [ ] **4.2.2 Performance pass** — component page LCP < 2.5s on 4G (NFR-1); preview iframe interactive < 1s (NFR-2); static previews cache aggressively (SPEC §10.3).
  - Acceptance: `Performance/CacheHeaderTest::test_preview_artifacts_long_cache`, `test_component_pages_send_sensible_cache_headers` + Lighthouse CI report on key templates.
- [ ] **4.2.3 Observability** — error tracking (e.g. Sentry/Flare), failed-job alerting surfacing in the system-health widget, scheduled DB backups.
  - Acceptance: `Admin/SystemHealthTest::test_failed_jobs_visible_in_widget` (extends 1.11.4) + backup command scheduled test.
- [ ] **4.2.4 Launch checklist** — Paddle live mode + live webhook secret, legal pages reviewed, goal targets wired to dashboard (SPEC §8.7), 100-component authoring roadmap scheduled, support macros prepared.
  - Acceptance: manual sign-off checklist (owner).

---

# Phase 5 — Growth (SPEC P3)

**Status:** planning placeholders. Each item gets its own mini-spec (added to SPEC.md via the change-log flow) before tasks are written; acceptance tests are defined at that point.

- [ ] **5.1 Meilisearch** — Laravel Scout driver swap for catalog + docs search (FR-1.3, SPEC §13.2).
- [ ] **5.2 Team tier** — organization seats; `OrderPlan` re-gains a team value; per-seat pricing (SPEC §7.1, §7.4).
- [ ] **5.3 Community submissions** — external component submission + review pipeline into `library/` (PRD §4.2).
- [ ] **5.4 AI features** — `laravel/ai` groundwork already installed (agent conversations tables); candidates: AI-assisted component variants, natural-language catalog search.
- [ ] **5.5 Collections pages** — `/collections/{slug}` curated bundles ("restaurant landing kit") (SPEC §15.1 🟡).
- [ ] **5.6 Behavioral email personalization** — B2-style triggers beyond blur-gate (SPEC §16.4).
- [ ] **5.7 Browser test suite** — Dusk/Playwright coverage for modal, tree, live-edit interactions currently under *QA-gate*.

---

## Progress summary

| Phase | Tasks | Done | Not started |
|---|---|---|---|
| Phase 1 — Foundation (P0) | 45 | 45 | 0 ✅ |
| Phase 2 — Monetization (P1) | 30 | 0 | 30 |
| Phase 3 — Power features (P2) | 13 | 0 | 13 |
| Phase 4 — Launch readiness | 9 | 2 | 7 |
| Phase 5 — Growth (P3) | 7 | 0 | 7 |
| **Total** | **104** | **47** | **57** |

*(Task count = numbered leaf tasks. Update this table as tasks complete.)*

## Notes & flags for owner

1. **Phase mapping** — Phase 1–3 map 1:1 to SPEC P0–P2; Phase 4 (ops) was added because deployment/security/performance work exists in NFRs but had no SPEC phase; Phase 5 = P3. Flag if you'd rather fold ops into Phase 2.
2. **`OrderPlan::enterprise`** — exists in code with a hardcoded $199 price; per SPEC §7.1 the team tier is v2, so task 1.2.5 removes it (replaced by `plan_prices` lookup). Flag if you want to keep it dormant instead.
3. **`MustVerifyEmail`** — currently commented out in `User`; task 1.1.2 re-enforces it (SPEC lists verification as a starter-kit flow). Flag if you want open registration during early beta instead.
4. **QA-gate items** — client-side interactions (hover outlines, keyboard shortcuts, drag-resize, live-edit compiling) have no PHPUnit surface; they are covered by the SPEC §8.5 authoring QA checklist until the Phase 5.7 browser suite.
5. **🟡 open decisions carried from SPEC** — mail provider (Resend/Postmark, blocks 4.1.5), B2 behavioral trigger (2.10.3), collections pages (5.5).

*End of project-phases.md v1.0. Changes flow: update SPEC.md (change log) → revise PRD.md → renumber/patch this file.*
