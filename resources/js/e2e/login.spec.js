import { test, expect } from '@playwright/test';

test.describe('Login page', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
    });

    test('shows the welcome heading', async ({ page }) => {
        await expect(page.getByRole('heading', { name: /welcome back/i })).toBeVisible();
    });

    test('shows email and password fields', async ({ page }) => {
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('input[type="password"]')).toBeVisible();
    });

    test('shows the sign in button', async ({ page }) => {
        await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible();
    });

    test('shows forgot password link', async ({ page }) => {
        await expect(page.getByText('Forgot password?')).toBeVisible();
    });
});
