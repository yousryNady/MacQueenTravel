<?php

namespace App\Domain\Shared\Models;

use App\Domain\Tenant\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Lock extends Model
{
    protected $fillable = [
        'lockable_type',
        'lockable_id',
        'lock_key',
        'owner',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function lockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
