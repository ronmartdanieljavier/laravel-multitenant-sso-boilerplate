<?php

namespace App\Http\Middleware;

use App\Models\Central\App;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenant\Services\TenantSwitcherService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveWebTenantDatabase
{
    public function __construct(
        protected TenantSwitcherService $tenantSwitcherService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $app = App::where('slug', 'tenant')->where('is_active', true)->first();

        if (! $app) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Tenant app is not configured.');
        }

        $this->tenantSwitcherService->initializeForUser($user->id, $app->slug);

        $tenantSlug = $this->tenantSwitcherService->getCurrentTenantSlug();

        if (! $tenantSlug) {
            abort(Response::HTTP_FORBIDDEN, 'No tenant assigned.');
        }

        $tenant = Tenant::query()
            ->where('slug', $tenantSlug)
            ->where('is_active', true)
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id)->where('user_app_tenants.app_id', $app->id))
            ->first();

        if (! $tenant) {
            abort(Response::HTTP_FORBIDDEN, 'Tenant not found or access denied.');
        }

        if ($tenant->is_maintenance) {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'This tenant is currently under maintenance.');
        }

        $this->configureConnection($tenant);

        $request->attributes->set('current_app', $app);
        $request->attributes->set('current_tenant', $tenant);

        return $next($request);
    }

    private function configureConnection(Tenant $tenant): void
    {
        $driver = config('database.connections.tenant.driver', 'mysql');
        $defaultPort = $driver === 'pgsql' ? 5432 : 3306;

        $connection = $driver === 'pgsql'
            ? $this->pgsqlConnection($tenant, $defaultPort)
            : $this->mysqlConnection($tenant, $defaultPort);

        Config::set('database.connections.tenant', $connection);
        DB::purge('tenant');
    }

    /** @return array<string, mixed> */
    private function mysqlConnection(Tenant $tenant, int $defaultPort): array
    {
        $connection = [
            'driver' => 'mysql',
            'database' => $tenant->db_name,
            'username' => $tenant->db_username,
            'password' => $tenant->db_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ];

        if ($tenant->hasReadReplica()) {
            $connection['write'] = ['host' => $tenant->db_host, 'port' => $tenant->db_port ?? $defaultPort];
            $connection['read'] = ['host' => $tenant->read_replica_host, 'port' => $tenant->read_replica_port ?? $tenant->db_port ?? $defaultPort];
            if ($tenant->read_replica_username !== null) {
                $connection['read']['username'] = $tenant->read_replica_username;
                $connection['read']['password'] = $tenant->read_replica_password ?? '';
            }
            $connection['sticky'] = true;
        } else {
            $connection['host'] = $tenant->db_host;
            $connection['port'] = $tenant->db_port ?? $defaultPort;
        }

        return $connection;
    }

    /** @return array<string, mixed> */
    private function pgsqlConnection(Tenant $tenant, int $defaultPort): array
    {
        $connection = [
            'driver' => 'pgsql',
            'database' => $tenant->db_name,
            'username' => $tenant->db_username,
            'password' => $tenant->db_password,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ];

        if ($tenant->hasReadReplica()) {
            $connection['write'] = ['host' => $tenant->db_host, 'port' => $tenant->db_port ?? $defaultPort];
            $connection['read'] = ['host' => $tenant->read_replica_host, 'port' => $tenant->read_replica_port ?? $tenant->db_port ?? $defaultPort];
            if ($tenant->read_replica_username !== null) {
                $connection['read']['username'] = $tenant->read_replica_username;
                $connection['read']['password'] = $tenant->read_replica_password ?? '';
            }
            $connection['sticky'] = true;
        } else {
            $connection['host'] = $tenant->db_host;
            $connection['port'] = $tenant->db_port ?? $defaultPort;
        }

        return $connection;
    }
}
