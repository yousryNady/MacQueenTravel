<?php

namespace App\Domain\Wallet\Models;

use App\Domain\Tenant\Scopes\TenantScope;
use App\Domain\Tenant\Traits\BelongsToTenant;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'wallet_id',
        'tenant_id',
        'idempotency_key',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];
    
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope());
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    protected static function newFactory(): WalletTransactionFactory
    {
        return WalletTransactionFactory::new();
    }
}
