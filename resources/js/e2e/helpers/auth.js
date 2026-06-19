/**
 * Log in via the login form and store session state.
 * Call this in a global setup or beforeEach for protected pages.
 *
 * @param {import('@playwright/test').Page} page
 */
export async function login(page) {
    await page.goto('/login');
    await page.fill('input[type="email"]', 'test@example.com');
    await page.fill('input[type="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'));
}
