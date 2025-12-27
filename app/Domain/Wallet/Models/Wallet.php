<?php

namespace App\Domain\Wallet\Models;

use App\Domain\Tenant\Scopes\TenantScope;
use App\Domain\Tenant\Traits\BelongsToTenant;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'balance',
        'currency',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];
    
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope());
    }

    protected static function newFactory(): WalletFactory
    {
        return WalletFactory::new();
    }
}
