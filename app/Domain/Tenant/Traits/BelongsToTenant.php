<?php

namespace App\Domain\Tenant\Traits;

use App\Domain\Tenant\Models\Tenant;
use App\Domain\Tenant\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (TenantManager::id()) {
                $builder->where('tenant_id', TenantManager::id());
            }
        });

        static::creating(function ($model) {
            if (TenantManager::id() && ! $model->tenant_id) {
                $model->tenant_id = TenantManager::id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
