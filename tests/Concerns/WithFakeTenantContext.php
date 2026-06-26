<?php

namespace Tests\Concerns;

use App\Http\Middleware\ResolveTenantDatabase;
use App\Http\Middleware\ResolveWebTenantDatabase;
use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Swaps tenant-database middlewares with a no-op that injects the tenant
 * into the request without re-configuring the database connection.
 * This preserves the SQLite :memory: tenant connection used in tests.
 */
trait WithFakeTenantContext
{
    private Tenant $contextTenant;

    protected function setFakeTenant(Tenant $tenant): void
    {
        $this->contextTenant = $tenant;

        $inject = function (Request $request, Closure $next) use ($tenant): Response {
            $request->attributes->set('current_tenant', $tenant);

            return $next($request);
        };

        $this->app->bind(ResolveWebTenantDatabase::class, fn () => new class($inject)
        {
            public function __construct(private readonly Closure $inject) {}

            public function handle(Request $request, Closure $next): Response
            {
                return ($this->inject)($request, $next);
            }
        });

        $this->app->bind(ResolveTenantDatabase::class, fn () => new class($inject)
        {
            public function __construct(private readonly Closure $inject) {}

            public function handle(Request $request, Closure $next): Response
            {
                return ($this->inject)($request, $next);
            }
        });
    }
}
