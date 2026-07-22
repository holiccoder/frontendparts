import { expect, test } from '@playwright/test';

/**
 * Smoke: the marketing home page renders server-side with its hero and
 * CTAs. This is the skeleton's one browser gate — add product specs next
 * to it as the product grows.
 */
test('home page renders', async ({ page }) => {
    const response = await page.goto('/');

    expect(response?.ok()).toBe(true);

    await expect(page).toHaveTitle(/.+/);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByRole('link', { name: 'View pricing' })).toBeVisible();
});
