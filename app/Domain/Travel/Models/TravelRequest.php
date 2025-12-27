<?php

namespace App\Domain\Travel\Models;

use App\Domain\Employee\Models\Employee;
use App\Domain\Tenant\Scopes\TenantScope;
use App\Domain\Tenant\Traits\BelongsToTenant;
use Database\Factories\TravelRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelRequest extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'approved_by',
        'type',
        'status',
        'destination',
        'departure_date',
        'return_date',
        'estimated_cost',
        'purpose',
        'notes',
        'approved_at',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'estimated_cost' => 'decimal:2',
        'approved_at' => 'datetime',
    ];
    
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope());
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    protected static function newFactory(): TravelRequestFactory
    {
        return TravelRequestFactory::new();
    }
}
