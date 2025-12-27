<?php

namespace App\Domain\Booking\Models;

use App\Domain\Employee\Models\Employee;
use App\Domain\Tenant\Scopes\TenantScope;
use App\Domain\Tenant\Traits\BelongsToTenant;
use App\Domain\Travel\Models\TravelRequest;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope());
    }
    
    protected $fillable = [
        'tenant_id',
        'travel_request_id',
        'employee_id',
        'type',
        'status',
        'provider',
        'provider_reference',
        'amount',
        'currency',
        'provider_data',
        'booked_at',
        'confirmed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_data' => 'array',
        'booked_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected static function newFactory(): BookingFactory
    {
        return BookingFactory::new();
    }
}
