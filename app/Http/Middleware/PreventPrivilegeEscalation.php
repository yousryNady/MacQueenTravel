<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventPrivilegeEscalation
{
    private array $roleHierarchy = [
        'employee' => 1,
        'manager' => 2,
        'admin' => 3,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($request->has('role')) {
            $requestedRole = $request->input('role');
            $userRoleLevel = $this->roleHierarchy[$user->role] ?? 0;
            $requestedRoleLevel = $this->roleHierarchy[$requestedRole] ?? 0;

            if ($requestedRoleLevel > $userRoleLevel) {
                return response()->json([
                    'error' => 'Cannot assign role higher than your own',
                ], 403);
            }
        }

        return $next($request);
    }
}
