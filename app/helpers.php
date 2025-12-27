<?php

use App\Domain\Tenant\Models\Tenant;
use App\Domain\Tenant\TenantManager;
use App\Models\PersonalAccessToken;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        return TenantManager::get();
    }
}

if (! function_exists('tenant_id')) {
    function tenant_id(): ?int
    {
        return TenantManager::id();
    }
}
if (! function_exists('tenant_id_from_token')) {
    function tenant_id_from_token(): ?string
    {
        return auth()->user()?->currentAccessToken()?->tenant_id;
    }
}
function assignTenantToToken($tokenResult, $tenantId)
{
    $token = PersonalAccessToken::where('tokenable_id', auth()->id())
        ->where('tokenable_type', auth()->user()?->getMorphClass())
        ->latest('created_at')
        ->first();

    if ($token) {
        $token->update(['tenant_id' => $tenantId]);
    }
}
