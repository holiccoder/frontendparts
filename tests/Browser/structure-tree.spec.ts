/**
 * Browser coverage for the structure tree + hover outlines (task 5.7, SPEC
 * §5.5 + §5.3) — previously QA-gate-only:
 *
 *  - the tree renders the composition closure with level badges and ×n
 *    instance chips (sections/feature-grid-01 composes
 *    elements/section-title-01 once and blocks/feature-card-01 six times),
 *  - hovering a child row soft-outlines (2px dashed accent) EVERY rendered
 *    instance inside the REAL built preview iframe, via the one-way
 *    postMessage highlight protocol against the data-fp-c/data-fp-i
 *    instrumentation,
 *  - clicking an instance chip pins a strong outline (2px solid + accent
 *    box-shadow) on exactly that instance; moving off the row restores the
 *    pin; unpinning clears.
 *
 * The outline assertions read the iframe DOM computed styles — the same
 * observable state the QA gate checks by eye.
 */
import { expect, test, type Page } from '@playwright/test';

const PAGE_URL = '/components/feature-grid/feature-grid-01';
const FRAME = 'iframe[title="Feature Grid 01 react preview"]';
const CARDS = '[data-fp-c="blocks/feature-card-01"]';
const ACCENT = 'rgb(99, 102, 241)';

/** The structure pane aside (heading "Structure"). */
function treePane(page: Page) {
    return page.locator('aside', { has: page.getByRole('heading', { name: 'Structure' }) });
}

test.beforeEach(async ({ page }) => {
    await page.goto(PAGE_URL);

    // Wait until the real preview artifact is rendered and instrumented.
    await expect(page.frameLocator(FRAME).locator(CARDS)).toHaveCount(6);
});

test('structure tree lists the composition with instance chips', async ({ page }) => {
    const tree = treePane(page);

    await expect(tree.getByRole('link', { name: 'Feature Grid 01' })).toBeVisible();
    await expect(tree.getByRole('link', { name: 'Section Title 01' })).toBeVisible();
    await expect(tree.getByRole('link', { name: 'Feature Card 01' })).toBeVisible();

    await expect(tree.getByText('×6')).toBeVisible();

    for (const chip of ['#1', '#2', '#3', '#4', '#5', '#6']) {
        await expect(tree.getByRole('button', { name: chip, exact: true })).toBeVisible();
    }
});

test('hovering a child row soft-outlines every instance in the preview iframe', async ({ page }) => {
    const tree = treePane(page);
    const cards = page.frameLocator(FRAME).locator(CARDS);

    await tree.getByRole('link', { name: 'Feature Card 01' }).hover();

    for (let index = 0; index < 6; index++) {
        await expect(cards.nth(index)).toHaveCSS('outline-style', 'dashed');
        await expect(cards.nth(index)).toHaveCSS('outline-color', ACCENT);
        await expect(cards.nth(index)).toHaveCSS('outline-width', '2px');
    }

    // Leaving the row clears every outline.
    await tree.getByRole('heading', { name: 'Structure' }).hover();

    await expect(cards.first()).toHaveCSS('outline-style', 'none');
    await expect(cards.nth(5)).toHaveCSS('outline-style', 'none');
});

test('hovering a single-instance row outlines its one region', async ({ page }) => {
    const tree = treePane(page);
    const title = page.frameLocator(FRAME).locator('[data-fp-c="elements/section-title-01"]');

    await expect(title).toHaveCount(1);

    await tree.getByRole('link', { name: 'Section Title 01' }).hover();

    await expect(title).toHaveCSS('outline-style', 'dashed');
    await expect(title).toHaveCSS('outline-color', ACCENT);
});

test('pinning an instance chip strong-outlines exactly that instance', async ({ page }) => {
    const tree = treePane(page);
    const preview = page.frameLocator(FRAME);
    const third = preview.locator('[data-fp-c="blocks/feature-card-01"][data-fp-i="3"]');
    const fourth = preview.locator('[data-fp-c="blocks/feature-card-01"][data-fp-i="4"]');

    await tree.getByRole('button', { name: '#3', exact: true }).click();

    await expect(third).toHaveCSS('outline-style', 'solid');
    await expect(third).toHaveCSS('outline-color', ACCENT);
    await expect(third).toHaveCSS('box-shadow', /rgba\(99, 102, 241, 0\.25\)/);
    await expect(fourth).toHaveCSS('outline-style', 'none');

    // Moving off the row keeps the pin (clearHighlight restores it).
    await tree.getByRole('heading', { name: 'Structure' }).hover();
    await expect(third).toHaveCSS('outline-style', 'solid');

    // Unpinning clears the outline for good.
    await tree.getByRole('button', { name: '#3', exact: true }).click();
    await tree.getByRole('heading', { name: 'Structure' }).hover();
    await expect(third).toHaveCSS('outline-style', 'none');
});
