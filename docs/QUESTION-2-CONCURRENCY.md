# Question 2 – Deep Technical & Problem Solving

## The Problem

We have a critical production issue:
- Users are sometimes charged twice
- Wallet balance becomes inconsistent
- Only happens under high concurrency
- Logs show multiple requests hitting the same endpoint simultaneously

---

## A. Root Cause Analysis

### Why Does This Happen?

I've dealt with this exact problem in a previous fintech project. The root cause is almost always a **race condition** in the wallet debit flow.

Here's what's happening:
```
Timeline (without proper locking):
─────────────────────────────────────────────────────────────────────

Request A                          Request B
    │                                  │
    ▼                                  ▼
Read balance: $1000                Read balance: $1000
    │                                  │
    ▼                                  ▼
Check: $1000 >= $800? ✓            Check: $1000 >= $800? ✓
    │                                  │
    ▼                                  ▼
Deduct: $1000 - $800 = $200        Deduct: $1000 - $800 = $200
    │                                  │
    ▼                                  ▼
Save: balance = $200               Save: balance = $200

Result: User charged $1600, but balance shows only $800 deducted!
```

Both requests read the same balance before either one commits. This is the classic "lost update" problem.

### What Are Race Conditions?

A race condition happens when multiple processes access shared data simultaneously, and the outcome depends on timing. In PHP/Laravel specifically:

1. **PHP is stateless** — Each request runs in complete isolation, no shared memory
2. **Database is shared state** — Multiple PHP workers all talk to the same DB
3. **No native locking** — Unlike Java or Go, PHP doesn't have built-in thread synchronization

The dangerous part? Your code looks perfectly fine:
```php
// Looks correct, but NOT safe under concurrency
$wallet = Wallet::find($id);

if ($wallet->balance >= $amount) {
    $wallet->balance -= $amount;
    $wallet->save();
}
```

The gap between reading and writing is where another request sneaks in. Might be milliseconds, but under load, it's enough.

### Why Database Constraints Alone Aren't Enough

First instinct: "Add a CHECK constraint so balance can't go negative!"
```sql
ALTER TABLE wallets ADD CONSTRAINT balance_non_negative CHECK (balance >= 0);
```

This prevents negative balances, but doesn't solve double-charging:

1. **Both transactions pass the check** — If balance is $1000 and two requests deduct $800, both see $1000 >= $800 as true
2. **Constraint fires too late** — Only kicks in at commit time
3. **No idempotency** — Can't distinguish a retry from a duplicate
4. **Race window still exists** — Gap between SELECT and UPDATE is unprotected

I've seen teams add constraints thinking they're protected, then get hit hard when traffic spikes. Constraints are a safety net, not a solution.

---

## B. Solution Design

### Overview

We need multiple layers of defense. No single mechanism is bulletproof:
```
┌─────────────────────────────────────────────────────────────┐
│                    DEFENSE IN DEPTH                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│   Layer 1: Idempotency Key                                  │
│   └── Same request twice? Return cached result              │
│                                                              │
│   Layer 2: Distributed Lock (Redis)                          │
│   └── Only one process touches wallet at a time             │
│                                                              │
│   Layer 3: Database Transaction + Row Lock                   │
│   └── Atomic read-modify-write with SELECT FOR UPDATE       │
│                                                              │
│   Layer 4: Balance Validation                                │
│   └── Double-check inside the locked transaction            │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Layer 1: Idempotency

Client sends a unique key with each request. If we've processed it, return the same result.

**Why this matters:**
- Network timeouts cause automatic retries
- Users double-click buttons
- Mobile apps retry on flaky connections
```json
POST /api/wallet/debit
{
    "amount": 800,
    "description": "Booking payment",
    "idempotency_key": "booking-123-payment-abc"
}
```

Server crashes after charging but before responding? Client retries with same key. We detect the duplicate and return original result. No double charge.

### Layer 2: Distributed Lock (Redis)

Before touching the wallet, grab a lock:
```php
Cache::lock("wallet:{$walletId}:lock", 10)->block(5, function () {
    // Only one process can be here at a time
});
```

**Why Redis over DB locks?**
- **Speed** — In-memory, much faster
- **Distributed** — Works across multiple app servers
- **Auto-timeout** — Lock releases if process dies
- **No connection exhaustion** — DB locks can eat your connection pool

### Layer 3: Database Transaction with Row Lock

Even with Redis, we use DB transactions for data integrity:
```php
DB::transaction(function () use ($wallet, $amount) {
    $wallet = Wallet::lockForUpdate()->find($wallet->id);
    
    // Exclusive access to this row now
    $wallet->balance -= $amount;
    $wallet->save();
});
```

`lockForUpdate()` translates to `SELECT ... FOR UPDATE`. Other transactions can't read or modify this row until we commit.

### Layer 4: Queue vs Synchronous

**Synchronous (our choice for wallet):**
- User needs immediate feedback
- Operation is fast (< 1 second)
- Failure should be visible right away

**Queue (for external provider calls):**
- Can tolerate delay
- External APIs might be slow
- Bulk operations

For wallet debits, synchronous makes sense:
1. User is waiting to see if booking worked
2. Need to reserve money immediately
3. Wallet operations are fast anyway

Provider bookings (Amadeus, etc.)? Those get queued — they're slower and can retry.

---

## C. Code-Level Fix

### WalletService Implementation

Here's our actual implementation:
```php
<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Contracts\WalletServiceInterface;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService implements WalletServiceInterface
{
    private const CACHE_TTL = 3600;
    private const LOCK_TIMEOUT = 10;
    private const LOCK_WAIT = 5;

    private function processTransaction(
        Wallet $wallet,
        string $type,
        float $amount,
        string $description,
        ?string $idempotencyKey
    ): WalletTransaction {
        
        // LAYER 1: Idempotency check
        if ($idempotencyKey) {
            $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                Log::info('Idempotent wallet transaction detected', [
                    'idempotency_key' => $idempotencyKey,
                    'existing_transaction_id' => $existing->id,
                ]);

                return $existing;
            }
        }

        $lockKey = "wallet:{$wallet->id}:lock";

        try {
            // LAYER 2: Redis distributed lock
            return Cache::lock($lockKey, self::LOCK_TIMEOUT)
                ->block(self::LOCK_WAIT, function () use ($wallet, $type, $amount, $description, $idempotencyKey) {
                    
                    // LAYER 3: Database transaction with row lock
                    return DB::transaction(function () use ($wallet, $type, $amount, $description, $idempotencyKey) {
                        
                        // Lock the specific row
                        $wallet = Wallet::lockForUpdate()->find($wallet->id);

                        $balanceBefore = $wallet->balance;

                        // LAYER 4: Validate inside the lock
                        if ($type === 'debit') {
                            if ($wallet->balance < $amount) {
                                Log::warning('Insufficient wallet balance', [
                                    'wallet_id' => $wallet->id,
                                    'current_balance' => $wallet->balance,
                                    'requested_amount' => $amount,
                                ]);

                                throw new \Exception(
                                    "Insufficient balance. Available: {$wallet->balance}, Required: {$amount}"
                                );
                            }
                            $wallet->balance -= $amount;
                        } else {
                            $wallet->balance += $amount;
                        }

                        $wallet->save();

                        $transaction = WalletTransaction::create([
                            'wallet_id' => $wallet->id,
                            'tenant_id' => $wallet->tenant_id,
                            'idempotency_key' => $idempotencyKey,
                            'type' => $type,
                            'amount' => $amount,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $wallet->balance,
                            'description' => $description,
                        ]);

                        Log::info('Wallet transaction completed', [
                            'transaction_id' => $transaction->id,
                            'type' => $type,
                            'amount' => $amount,
                            'balance_after' => $wallet->balance,
                        ]);

                        return $transaction;
                    });
                });
        } catch (LockTimeoutException $e) {
            Log::error('Wallet lock timeout', [
                'wallet_id' => $wallet->id,
                'type' => $type,
                'amount' => $amount,
            ]);

            throw new \Exception('System is busy. Please try again shortly.');
        }
    }
}
```

### How It Prevents Double Charging

Let's trace through with our fix in place:
```
Timeline WITH our fix:
─────────────────────────────────────────────────────────────────────

Request A                              Request B
    │                                      │
    ▼                                      ▼
Check idempotency: not found          Check idempotency: not found
    │                                      │
    ▼                                      ▼
Acquire Redis lock ✓                  Wait for lock...
    │                                      │
    ▼                                      │
BEGIN TRANSACTION                          │
SELECT ... FOR UPDATE                      │
    │                                      │
    ▼                                      │
Read balance: $1000                        │
Check: $1000 >= $800? ✓                    │
Update: balance = $200                     │
    │                                      │
    ▼                                      │
COMMIT                                     │
Release lock                               │
    │                                      ▼
    │                                 Acquire lock ✓
    │                                      │
    │                                      ▼
    │                                 BEGIN TRANSACTION
    │                                 SELECT ... FOR UPDATE
    │                                      │
    │                                      ▼
    │                                 Read balance: $200  ← fresh value!
    │                                 Check: $200 >= $800? ✗
    │                                      │
    │                                      ▼
    │                                 ROLLBACK
    │                                 "Insufficient balance"
    ▼                                      ▼
Charged $800 ✓                        Error returned ✓

Final balance: $200 (correct!)
```

### LockService for Bookings

Same pattern for preventing double bookings:
```php
<?php

namespace App\Domain\Shared\Services;

class LockService
{
    private const DEFAULT_TTL = 30;
    private const MAX_RETRY_ATTEMPTS = 3;
    private const RETRY_DELAY_MS = 100;

    public function executeWithLock(Model $model, string $action, callable $callback, ?int $ttl = null)
    {
        $owner = $this->acquire($model, $action, $ttl);

        if (! $owner) {
            Log::error('Failed to acquire lock', [
                'model' => get_class($model),
                'model_id' => $model->id,
                'action' => $action,
            ]);

            throw new \Exception('Could not acquire lock. Resource is busy.');
        }

        try {
            return $callback();
        } finally {
            $this->release($model, $action, $owner);
        }
    }
}
```

### Error Handling in Controllers

Graceful degradation when system is under load:
```php
public function debit(WalletTransactionRequest $request): JsonResponse
{
    try {
        $wallet = $this->walletService->getWallet(tenant_id());

        $transaction = $this->walletService->debit(
            $wallet,
            $request->amount,
            $request->description,
            $request->idempotency_key
        );

        return $this->created($transaction, 'Debit successful');

    } catch (\Exception $e) {
        if (str_contains($e->getMessage(), 'Insufficient balance')) {
            return $this->error($e->getMessage(), 422);
        }

        if (str_contains($e->getMessage(), 'System is busy')) {
            return $this->error($e->getMessage(), 503);
        }

        throw $e;
    }
}
```

---

## D. PHP 8 / Laravel 12 Features

### PHP 8 Features We Use

**1. Constructor Property Promotion**

Huge time saver:
```php
// Before PHP 8
class BookingService 
{
    private WalletServiceInterface $walletService;
    
    public function __construct(WalletServiceInterface $walletService) 
    {
        $this->walletService = $walletService;
    }
}

// PHP 8
class BookingService 
{
    public function __construct(
        private WalletServiceInterface $walletService
    ) {}
}
```

**2. Null Safe Operator**
```php
// Instead of
$tenantId = $request->user() ? $request->user()->tenant_id : null;

// Now
$tenantId = $request->user()?->tenant_id;
```

**3. Named Arguments**

Self-documenting code:
```php
Cache::lock(
    name: "wallet:{$id}:lock",
    seconds: 10
)->block(seconds: 5, callback: fn() => $this->process());
```

**4. Match Expression**

Cleaner than switch:
```php
$message = match($booking->status) {
    'pending' => 'Awaiting confirmation',
    'confirmed' => 'Booking confirmed',
    'cancelled' => 'Booking cancelled',
    default => 'Unknown status',
};
```

**5. Union Types**
```php
public function findById(int|string $id): Booking
{
    return Booking::findOrFail($id);
}
```

### Laravel 12 Features

**1. Simplified Bootstrap**

Middleware now in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'tenant' => ResolveTenant::class,
        'tenant.ownership' => EnsureTenantOwnership::class,
    ]);
})
```

**2. Cleaner Exception Handling**
```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    });
})
```

**3. Rate Limiting**
```php
RateLimiter::for('wallet', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});
```

### Maintaining Backward Compatibility

**1. Interface-Based Design**

Easy to swap implementations:
```php
// Just change the binding
$this->app->bind(WalletServiceInterface::class, NewWalletService::class);
```

**2. Constants Instead of Magic Numbers**
```php
private const LOCK_TIMEOUT = 10;
private const LOCK_WAIT = 5;
private const CACHE_TTL = 3600;
```

**3. Reversible Migrations**

Every migration has a proper `down()` method.

**4. Comprehensive Logging**

Makes debugging production issues much easier.

---

## E. Advanced DI: Contextual Binding

### The Problem

We have multiple external providers:
- **Flights** → Amadeus API
- **Hotels** → Booking.com API

Both share similar operations (search, book, cancel), but require different implementations. How do we inject the right provider into the right service?

### The Solution: Contextual Binding

Laravel's contextual binding lets us inject different implementations of the same interface based on the consuming class.
```php
// Both handlers depend on the SAME interface
class FlightBookingHandler
{
    public function __construct(
        private ExternalProviderInterface $provider  // Gets Amadeus
    ) {}
}

class HotelBookingHandler
{
    public function __construct(
        private ExternalProviderInterface $provider  // Gets Booking.com
    ) {}
}
```

### Registration in Service Provider
```php
// In DomainServiceProvider

// FlightBookingHandler gets AmadeusFlightProvider
$this->app->when(FlightBookingHandler::class)
    ->needs(ExternalProviderInterface::class)
    ->give(AmadeusFlightProvider::class);

// HotelBookingHandler gets BookingComHotelProvider
$this->app->when(HotelBookingHandler::class)
    ->needs(ExternalProviderInterface::class)
    ->give(BookingComHotelProvider::class);
```

### Provider Implementation
```php
<?php

namespace App\Domain\Booking\Providers;

use App\Domain\Booking\Contracts\ExternalProviderInterface;

class AmadeusFlightProvider implements ExternalProviderInterface
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret
    ) {}

    public function search(array $criteria): array
    {
        // Call Amadeus API
        return [
            'provider' => $this->getProviderName(),
            'flights' => [...],
        ];
    }

    public function book(array $details): array
    {
        return [
            'provider' => $this->getProviderName(),
            'confirmation_code' => 'AMD' . bin2hex(random_bytes(4)),
            'status' => 'confirmed',
        ];
    }

    public function cancel(string $reference): bool
    {
        return true;
    }

    public function getProviderName(): string
    {
        return 'Amadeus';
    }
}
```

### Why This Matters

1. **Same interface, different implementations** — Clean abstraction
2. **No hardcoded dependencies** — Easy to swap providers
3. **Testable** — Mock the interface for unit tests
4. **Framework handles wiring** — No manual instantiation

### Demo Endpoint
```
GET /api/provider-demo

Response:
{
    "success": true,
    "data": {
        "contextual_binding_demo": {
            "explanation": "Both handlers depend on ExternalProviderInterface, but receive different implementations",
            "flight_handler": {
                "class": "App\\Domain\\Booking\\Handlers\\FlightBookingHandler",
                "injected_provider": "Amadeus",
                "results": {
                    "provider": "Amadeus",
                    "flights": [...]
                }
            },
            "hotel_handler": {
                "class": "App\\Domain\\Booking\\Handlers\\HotelBookingHandler",
                "injected_provider": "Booking.com",
                "results": {
                    "provider": "Booking.com",
                    "hotels": [...]
                }
            }
        }
    }
}
```

Both handlers asked for `ExternalProviderInterface`, but Laravel gave each the correct implementation automatically.

### File Structure
```
app/Domain/Booking/
├── Contracts/
│   ├── BookingServiceInterface.php
│   └── ExternalProviderInterface.php
├── Handlers/
│   ├── FlightBookingHandler.php
│   └── HotelBookingHandler.php
├── Providers/
│   ├── AmadeusFlightProvider.php
│   └── BookingComHotelProvider.php
├── Services/
│   └── BookingService.php
└── Models/
    └── Booking.php
```

---

## Summary

The double-charging issue needs multiple layers of protection:

| Layer | Purpose | Implementation |
|-------|---------|----------------|
| Idempotency | Catches duplicate requests | Unique key per transaction |
| Redis Lock | Serializes wallet access | `Cache::lock()` |
| DB Transaction | Ensures atomicity | `DB::transaction()` |
| Row Lock | Prevents concurrent reads | `lockForUpdate()` |
| Balance Check | Final validation | Inside the transaction |

No single layer is sufficient:
- Constraints catch errors but don't prevent them
- Transactions are atomic but can run concurrently
- Locks serialize access but need distribution

Together, they create a robust system that handles high concurrency without double-charging.

### Key Takeaways

1. **Race conditions are timing-dependent** — They might not show up in testing but will hit you in production
2. **Defense in depth** — Multiple layers of protection, each covering different failure modes
3. **Idempotency is essential** — Network failures cause retries; your system must handle them
4. **Logging saves lives** — When issues occur, you need to trace what happened
5. **Graceful degradation** — Return 503 when busy rather than corrupting data
6. **Contextual Binding** — Same interface, different implementations based on consumer

### HTTP Status Codes Used

| Code | Meaning | When Used |
|------|---------|-----------|
| 200 | Success | Normal operations |
| 201 | Created | New resource created |
| 409 | Conflict | Duplicate booking attempt |
| 422 | Unprocessable | Validation error, insufficient balance |
| 503 | Service Unavailable | Lock timeout, system busy |
