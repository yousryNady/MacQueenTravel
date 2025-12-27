<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tokenTenantId = $token->tenant_id;

        if (! $tokenTenantId) {
            return response()->json(['error' => 'Tenant context missing'], 403);
        }

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        foreach ($request->route()->parameters() as $parameter) {
            if (is_object($parameter) && method_exists($parameter, 'getAttribute')) {
                $tenantId = $parameter->getAttribute('tenant_id');

                if ($tenantId && $tenantId !== $user->tenant_id) {
                    return response()->json(['error' => 'Access denied'], 403);
                }
            }
        }
        return $next($request);
    }
}
