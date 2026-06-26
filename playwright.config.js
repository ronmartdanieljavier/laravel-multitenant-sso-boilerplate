import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './resources/js/e2e',
    globalSetup: './resources/js/e2e/globalSetup.js',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: process.env.APP_URL ?? 'http://localhost:8000',
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
    webServer: {
        command: 'php artisan serve --no-reload --port=8000',
        url: 'http://localhost:8000/up',
        reuseExistingServer: true,
        timeout: 30_000,
        env: {
            SESSION_DRIVER: 'file',
            CACHE_STORE: 'file',
            QUEUE_CONNECTION: 'sync',
            REDIS_HOST: '127.0.0.1',
            DB_HOST: '127.0.0.1',
        },
    },
});
