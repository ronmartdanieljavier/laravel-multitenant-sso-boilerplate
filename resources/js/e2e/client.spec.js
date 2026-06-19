import { test, expect } from '@playwright/test';

test.describe('Client portal', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/client');
    });

    test('shows the welcome message', async ({ page }) => {
        await expect(page.getByText(/good morning/i)).toBeVisible();
    });

    test('shows app cards', async ({ page }) => {
        await expect(page.getByText('Admin Portal')).toBeVisible();
        await expect(page.getByText('Reports Suite')).toBeVisible();
        await expect(page.getByText('Client Hub')).toBeVisible();
        await expect(page.getByText('Billing')).toBeVisible();
    });

    test('shows recent activity section', async ({ page }) => {
        await expect(page.getByText('Recent Activity')).toBeVisible();
    });
});
