/**
 * Browser coverage for live-edit mode (task 5.7, SPEC §5.6 — React side;
 * Vue optional) — previously QA-gate-only:
 *
 *  - with features.live_edit on (set by the fixtures seeder) the Edit tab
 *    appears and mounts the multi-file editor over the REAL composition
 *    closure payload (elements/button-01 + sections/hero-01 + data.json),
 *  - the esbuild-wasm session compiles the closure IN THE BROWSER and the
 *    sandboxed live iframe renders it — no error overlay,
 *  - a trivial sample-data edit and a trivial source edit each re-render
 *    the preview (debounced rebuild) with no error overlay surfacing.
 *
 * The esm.sh fetches the runtime makes for react/react-dom are intercepted
 * and served from local offline bundles (scripts/esm-shims.ts) — the
 * compiler, sources and render pipeline under test stay entirely real.
 */
import { expect, test } from '@playwright/test';
import { installEsmShims } from './scripts/esm-shims';

const PAGE_URL = '/components/hero/hero-01';
const LIVE_FRAME = 'iframe[title="Hero 01 live edit preview"]';
const HEADING = 'Ship your next product in days, not months';

test.beforeEach(async ({ page }) => {
    await installEsmShims(page);
    await page.goto(PAGE_URL);
    await page.getByRole('tab', { name: 'Edit', exact: true }).click();

    // The in-browser runtime (esbuild-wasm) is ready once its loader clears.
    await expect(page.getByText('Loading the live edit runtime…')).toHaveCount(0, { timeout: 60_000 });
});

test('edit tab mounts the closure editor and compiles the component in-browser', async ({ page }) => {
    const fileTabs = page.getByRole('tablist', { name: 'Component files' });

    await expect(fileTabs.getByRole('tab', { name: 'elements/button-01/index.tsx' })).toBeVisible();
    await expect(fileTabs.getByRole('tab', { name: 'sections/hero-01/index.tsx' })).toBeVisible();
    await expect(fileTabs.getByRole('tab', { name: 'data.json' })).toBeVisible();

    // The entry source is the active editor tab by default.
    await expect(page.getByLabel('Source editor for sections/hero-01/index.tsx')).toBeVisible();

    // The live iframe compiles + renders the component with its sample data.
    const live = page.frameLocator(LIVE_FRAME);
    await expect(live.getByRole('heading', { name: HEADING })).toBeVisible({ timeout: 30_000 });

    // No compile/runtime error overlay.
    await expect(page.getByRole('alert')).toHaveCount(0);
});

test('a sample-data edit re-renders the live preview without an error overlay', async ({ page }) => {
    const live = page.frameLocator(LIVE_FRAME);
    await expect(live.getByRole('heading', { name: HEADING })).toBeVisible({ timeout: 30_000 });

    await page.getByRole('tab', { name: 'data.json' }).click();

    const editor = page.getByLabel('Sample data JSON editor');
    const json = await editor.inputValue();

    await editor.fill(json.replace(HEADING, 'Edited live in the browser'));

    await expect(live.getByRole('heading', { name: 'Edited live in the browser' })).toBeVisible({ timeout: 30_000 });
    await expect(page.getByRole('alert')).toHaveCount(0);
});

test('a source edit re-renders the live preview without an error overlay', async ({ page }) => {
    const live = page.frameLocator(LIVE_FRAME);
    await expect(live.getByRole('heading', { name: HEADING })).toBeVisible({ timeout: 30_000 });

    const editor = page.getByLabel('Source editor for sections/hero-01/index.tsx');
    const source = await editor.inputValue();

    // Trivial, always-valid TSX edit: swap one spacing utility for another.
    await editor.fill(source.replace('gap-12', 'gap-10'));

    await expect(live.getByRole('heading', { name: HEADING })).toBeVisible({ timeout: 30_000 });
    await expect(page.getByRole('alert')).toHaveCount(0);
});
