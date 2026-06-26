import { execSync } from 'child_process';

/**
 * Runs before all Playwright tests.
 *
 * Ensures the tenant DB is reachable and has the correct schema.
 * In CI the Demo Tenant is seeded with db_host='127.0.0.1' (from DB_HOST env),
 * and a PostgreSQL service is available at that address.
 * In local dev with Docker, this updates the stored db_host so the tenant
 * connection points to the host-mapped PostgreSQL at 127.0.0.1:5432.
 */
export default async function globalSetup() {
    const env = {
        ...process.env,
        DB_HOST: '127.0.0.1',
        SESSION_DRIVER: 'file',
        CACHE_STORE: 'file',
    };

    // In local dev the Demo Tenant record may have db_host='postgres' (Docker-internal
    // hostname). Rewrite it to 127.0.0.1 so the middleware can reach the tenant DB
    // from the host machine (Docker Compose exposes PostgreSQL at localhost:5432).
    try {
        execSync(
            `php artisan tinker --execute 'App\\Models\\Central\\Tenant::where("slug","demo")->update(["db_host" => "127.0.0.1"]);'`,
            { env, stdio: 'pipe' },
        );
    } catch {
        // Central DB may not be a pgsql instance (e.g. SQLite in CI before the
        // PostgreSQL service is attached). Ignore and let tenant:migrate surface
        // any real errors.
    }

    // Ensure all tenant schema migrations are applied (documents, report_subscriptions, etc.)
    try {
        execSync('php artisan tenant:migrate --force', { env, stdio: 'inherit' });
    } catch {
        // If no tenant DB is reachable, E2E tests that query the tenant DB will
        // fail with clearer errors at the test level.
    }
}
