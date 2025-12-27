<?php

namespace App\Http\Middleware;

use App\Domain\Tenant\Models\Tenant;
use App\Domain\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        if (! $tenant->is_active) {
            return response()->json(['error' => 'Tenant is inactive'], 403);
        }

        TenantManager::set($tenant);

        return $next($request);
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $tenantId = tenant_id_from_token();

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }
}
