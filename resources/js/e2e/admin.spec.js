import { test, expect } from '@playwright/test';
import { login } from './helpers/auth.js';

test.describe('Admin dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.goto('/admin');
    });

    test('shows the dashboard heading', async ({ page }) => {
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
    });

    test('shows four stat cards', async ({ page }) => {
        await expect(page.getByText('Total Users')).toBeVisible();
        await expect(page.getByText('Active Apps')).toBeVisible();
        await expect(page.getByText('Active Tenants')).toBeVisible();
        await expect(page.getByText('SSO Sessions')).toBeVisible();
    });

    test('shows the recent users table', async ({ page }) => {
        const section = page.locator('div').filter({ hasText: /^Recent Users/ }).first();
        await expect(section).toBeVisible();
        await expect(section.locator('tbody tr')).toHaveCount(4);
    });

    test('shows the invite user button', async ({ page }) => {
        await expect(page.getByRole('link', { name: /invite user/i })).toBeVisible();
    });
});
