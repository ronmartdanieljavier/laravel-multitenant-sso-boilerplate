/**
 * Log in via the login form and store session state.
 * Uses the default admin account seeded by migration (ADMIN_EMAIL / ADMIN_PASSWORD env vars,
 * falling back to admin@example.com / password).
 *
 * @param {import('@playwright/test').Page} page
 */
export async function login(page) {
    const email = process.env.ADMIN_EMAIL ?? 'admin@example.com';
    const password = process.env.ADMIN_PASSWORD ?? 'password';

    await page.goto('/login');
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'));
}
