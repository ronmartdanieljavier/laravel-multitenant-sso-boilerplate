import { test, expect } from '@playwright/test';

test.describe('Reports suite', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/reports');
    });

    test('shows the reports suite header', async ({ page }) => {
        await expect(page.getByText('Reports Suite')).toBeVisible();
    });

    test('shows four summary cards', async ({ page }) => {
        await expect(page.getByText('Total Logins')).toBeVisible();
        await expect(page.getByText('Unique Users')).toBeVisible();
        await expect(page.getByText('SSO Tokens Issued')).toBeVisible();
        await expect(page.getByText('Failed Attempts')).toBeVisible();
    });

    test('shows report rows in the table', async ({ page }) => {
        await expect(page.locator('tbody tr')).toHaveCount(4);
    });

    test('switches tab on click', async ({ page }) => {
        await page.getByRole('button', { name: 'users' }).click();
        await expect(page.getByRole('button', { name: 'users' })).toHaveClass(/border-blue-500/);
    });

    test('shows the generate report button', async ({ page }) => {
        await expect(page.getByRole('button', { name: /generate report/i })).toBeVisible();
    });
});
