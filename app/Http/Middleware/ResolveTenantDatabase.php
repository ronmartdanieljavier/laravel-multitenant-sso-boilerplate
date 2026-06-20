<?php

namespace App\Http\Middleware;

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
        $tenantSlug = $request->header('X-Tenant');

        if (! $tenantSlug) {
            return response()->json(['message' => 'Tenant not specified.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $request->user();

        $tenant = Tenant::query()
            ->where('slug', $tenantSlug)
            ->where('is_active', true)
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->first();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found or access denied.'], Response::HTTP_FORBIDDEN);
        }

        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => $tenant->db_host,
            'port' => $tenant->db_port ?? 3306,
            'database' => $tenant->db_name,
            'username' => $tenant->db_username,
            'password' => $tenant->db_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);

        DB::purge('tenant');

        $request->merge(['current_tenant' => $tenant]);

        return $next($request);
    }
}
