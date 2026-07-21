/**
 * FrontendParts browser suite (task 5.7) — Playwright driving the SYSTEM
 * Google Chrome (`channel: 'chrome'`; no chromium download, no ChromeDriver
 * to version-match). It covers the interactions that used to be QA-gate-only:
 * the CodePen-style preview modal, the structure tree with hover outlines,
 * viewport switching, and live-edit mode (React).
 *
 * HOW TO RUN
 *   npm run test:browser          # build the app, seed fixtures, run headless
 *   npm run test:browser:headed   # same, with a visible browser (debugging)
 *   npx playwright test --ui      # Playwright UI mode (after a first build)
 *
 * WHAT HAPPENS PER RUN
 *   1. global-setup (tests/Browser/scripts/global-setup.ts)
 *      - checks public/build/manifest.json (npm scripts chain `npm run build`)
 *      - wipes database/browser.sqlite and reseeds it through
 *        `php artisan browser:fixtures` — REAL library sync + REAL vite
 *        preview builds for the composite fixtures (sections/hero-01 and
 *        sections/feature-grid-01), the live-edit flag on, everything published
 *      - pre-bundles offline ESM shims for the esm.sh calls the live-edit
 *        runtime makes (tests/Browser/scripts/esm-shims.ts)
 *   2. webServer (tests/Browser/scripts/serve.mjs)
 *      - boots the app on http://127.0.0.1:8899 via the same single-process
 *        `php -S` router artisan serve uses, in APP_ENV=browser
 *        (.env.browser) against the sqlite file — Playwright owns the
 *        process lifecycle, so nothing is left running afterwards
 *
 * ISOLATION
 *   Specs live in tests/Browser (this config's testDir) and NEVER run inside
 *   `php artisan test`; tests/Feature/BrowserSuiteTest.php only asserts the
 *   suite's wiring. Artifacts (results/, report/, .cache/) are gitignored.
 */
import { defineConfig } from '@playwright/test';
import { baseURL, dbPath } from './tests/Browser/scripts/env.mjs';

export default defineConfig({
    testDir: './tests/Browser',
    testMatch: '**/*.spec.ts',
    globalSetup: './tests/Browser/scripts/global-setup.ts',
    outputDir: './tests/Browser/results',
    timeout: 90_000,
    expect: { timeout: 15_000 },
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list'], ['html', { outputFolder: 'tests/Browser/report', open: 'never' }]],
    use: {
        baseURL,
        channel: 'chrome',
        viewport: { width: 1440, height: 900 },
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    webServer: {
        command: 'node tests/Browser/scripts/serve.mjs',
        url: baseURL,
        timeout: 120_000,
        reuseExistingServer: false,
        env: {
            APP_ENV: 'browser',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: dbPath,
        },
    },
});
