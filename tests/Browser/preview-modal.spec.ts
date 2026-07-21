/**
 * Browser coverage for the CodePen-style preview modal (task 5.7, SPEC
 * §5.4) — previously QA-gate-only:
 *
 *  - the modal IS the component detail page's main section (inline variant),
 *    rendering the Preview|Code|Data|Docs tabs (+ Edit while the live-edit
 *    flag is on) and a REAL built preview artifact in the sandboxed iframe,
 *  - viewport presets (375/768/1280/Full) resize the stage live,
 *  - the React|Vue toggle swaps the served artifact,
 *  - a catalog card click opens the SAME modal as an overlay (radix dialog)
 *    fed by the /api/components payload, and Escape closes it.
 *
 * Fixtures: php artisan browser:fixtures (real library sync + real vite
 * preview builds for sections/hero-01, a free composite component).
 */
import { expect, test } from '@playwright/test';

const PAGE_URL = '/components/hero/hero-01';

/** The inline modal section on the component detail page. */
function modal(page: import('@playwright/test').Page) {
    return page.locator('section[aria-label="Hero 01 preview"]');
}

test('component page renders the preview modal with tabs and a real preview artifact', async ({ page }) => {
    await page.goto(PAGE_URL);

    const dialog = modal(page);
    await expect(dialog).toBeVisible();

    const tablist = dialog.getByRole('tablist', { name: 'Component detail tabs' });

    for (const tab of ['Preview', 'Code', 'Data', 'Docs', 'Edit']) {
        await expect(tablist.getByRole('tab', { name: tab, exact: true })).toBeVisible();
    }

    // The iframe points at the REAL built artifact and renders it.
    const iframe = dialog.locator('iframe[title="Hero 01 react preview"]');
    await expect(iframe).toHaveAttribute('src', /\/previews\/sections\/hero-01\/1\.0\.0\/react\.html$/);

    const preview = page.frameLocator('iframe[title="Hero 01 react preview"]');
    await expect(preview.getByRole('heading', { name: 'Ship your next product in days, not months' })).toBeVisible();
});

test('viewport presets resize the preview stage', async ({ page }) => {
    await page.goto(PAGE_URL);

    const dialog = modal(page);
    const stage = dialog.locator('iframe[title="Hero 01 react preview"]').locator('xpath=..');

    await dialog.getByRole('button', { name: '375', exact: true }).click();
    await expect(stage).toHaveAttribute('style', /width: 375px/);
    await expect(stage).toHaveCSS('width', '375px');

    await dialog.getByRole('button', { name: '768', exact: true }).click();
    await expect(stage).toHaveCSS('width', '768px');

    await dialog.getByRole('button', { name: '1280', exact: true }).click();
    await expect(stage).toHaveCSS('width', '1280px');

    await dialog.getByRole('button', { name: 'Full', exact: true }).click();
    await expect(stage).toHaveAttribute('style', /width: 100%/);
});

test('framework toggle swaps between the react and vue artifacts', async ({ page }) => {
    await page.goto(PAGE_URL);

    const dialog = modal(page);

    await dialog.getByRole('button', { name: 'vue', exact: true }).click();

    const vueFrame = dialog.locator('iframe[title="Hero 01 vue preview"]');
    await expect(vueFrame).toHaveAttribute('src', /\/previews\/sections\/hero-01\/1\.0\.0\/vue\.html$/);

    const vuePreview = page.frameLocator('iframe[title="Hero 01 vue preview"]');
    await expect(vuePreview.getByRole('heading', { name: 'Ship your next product in days, not months' })).toBeVisible();

    await dialog.getByRole('button', { name: 'react', exact: true }).click();
    await expect(dialog.locator('iframe[title="Hero 01 react preview"]')).toBeVisible();
});

test('catalog card click opens the modal as an overlay and Escape closes it', async ({ page }) => {
    // The usage page lists exactly one card (hero-01) — no pagination drift.
    await page.goto('/components/hero');

    await page
        .getByRole('link', { name: /Hero 01/ })
        .first()
        .click();

    const overlay = page.getByRole('dialog');
    await expect(overlay).toBeVisible();
    await expect(overlay.getByRole('tab', { name: 'Preview', exact: true })).toBeVisible();
    await expect(overlay.locator('iframe[title="Hero 01 react preview"]')).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(overlay).toBeHidden();
});
