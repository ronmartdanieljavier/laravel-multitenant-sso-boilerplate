<?php

namespace App\Http\Middleware;

use App\Models\Central\App;
use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantDatabase
{
    public function handle(Request $request, Closure $next): Response
    {
        $appSlug = $request->header('X-App');
        $tenantSlug = $request->header('X-Tenant');

        if (! $appSlug) {
            return response()->json(['message' => 'App not specified.'], Response::HTTP_BAD_REQUEST);
        }

        if (! $tenantSlug) {
            return response()->json(['message' => 'Tenant not specified.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $request->user();

        $app = App::query()
            ->where('slug', $appSlug)
            ->where('is_active', true)
            ->first();

        if (! $app) {
            return response()->json(['message' => 'App not found or access denied.'], Response::HTTP_FORBIDDEN);
        }

        if (! $user->tokenCan("app:{$app->slug}")) {
            return response()->json(['message' => 'App not found or access denied.'], Response::HTTP_FORBIDDEN);
        }

        $hasAppAccess = $user->userApps()->where('app_id', $app->id)->exists();

        if (! $hasAppAccess) {
            return response()->json(['message' => 'App not found or access denied.'], Response::HTTP_FORBIDDEN);
        }

        $tenant = Tenant::query()
            ->where('slug', $tenantSlug)
            ->where('is_active', true)
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id)->where('user_app_tenants.app_id', $app->id))
            ->first();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found or access denied.'], Response::HTTP_FORBIDDEN);
        }

        $userAppTenant = $user->userAppTenants()
            ->where('app_id', $app->id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

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
            $connection['write'] = [
                'host' => $tenant->db_host,
                'port' => $tenant->db_port ?? 3306,
            ];
            $connection['read'] = [
                'host' => $tenant->read_replica_host,
                'port' => $tenant->read_replica_port ?? $tenant->db_port ?? 3306,
            ];
            if ($tenant->read_replica_username !== null) {
                $connection['read']['username'] = $tenant->read_replica_username;
                $connection['read']['password'] = $tenant->read_replica_password ?? '';
            }
            $connection['sticky'] = true;
        } else {
            $connection['host'] = $tenant->db_host;
            $connection['port'] = $tenant->db_port ?? 3306;
        }

        Config::set('database.connections.tenant', $connection);

        DB::purge('tenant');

        $request->attributes->set('current_app', $app);
        $request->attributes->set('current_tenant', $tenant);
        $request->attributes->set('current_role', $userAppTenant->role);

        return $next($request);
    }
}
