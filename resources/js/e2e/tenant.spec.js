import { test, expect } from '@playwright/test';
import { login } from './helpers/auth.js';

test.describe('Tenant portal', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.goto('/tenant');
    });

    test('shows the welcome message', async ({ page }) => {
        await expect(page.getByText(/good (morning|afternoon|evening)/i)).toBeVisible();
    });

    test('shows stats cards', async ({ page }) => {
        await expect(page.locator('#stat-reports-active')).toBeVisible();
        await expect(page.locator('#stat-reports-failed')).toBeVisible();
        await expect(page.locator('#stat-errors')).toBeVisible();
        await expect(page.locator('#stat-documents')).toBeVisible();
    });

    test('shows quick action links', async ({ page }) => {
        await expect(page.getByRole('link', { name: /queue report/i })).toBeVisible();
        await expect(page.locator('#tour-quick-actions a[href="/documents"]')).toBeVisible();
        await expect(page.locator('#tour-quick-actions a[href="/tenant/errors"]')).toBeVisible();
    });

    test('shows recent activity sections', async ({ page }) => {
        await expect(page.locator('#tour-recent-reports')).toBeVisible();
        await expect(page.locator('#tour-recent-errors')).toBeVisible();
        await expect(page.locator('#tour-recent-documents')).toBeVisible();
    });
});
