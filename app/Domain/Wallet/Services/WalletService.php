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

    public function getWallet(int $tenantId): Wallet
    {
        return Cache::remember(
            "tenant:{$tenantId}:wallet",
            self::CACHE_TTL,
            fn () => Wallet::firstOrCreate(
                ['tenant_id' => $tenantId],
                ['balance' => 0, 'currency' => 'USD']
            )
        );
    }

    public function credit(Wallet $wallet, float $amount, string $description, ?string $idempotencyKey = null): WalletTransaction
    {
        $transaction = $this->processTransaction($wallet, 'credit', $amount, $description, $idempotencyKey);

        $this->clearWalletCache($wallet->tenant_id);

        return $transaction;
    }

    public function debit(Wallet $wallet, float $amount, string $description, ?string $idempotencyKey = null): WalletTransaction
    {
        $transaction = $this->processTransaction($wallet, 'debit', $amount, $description, $idempotencyKey);

        $this->clearWalletCache($wallet->tenant_id);

        return $transaction;
    }

    public function hasBalance(Wallet $wallet, float $amount): bool
    {
        return Wallet::find($wallet->id)->balance >= $amount;
    }

    private function processTransaction(Wallet $wallet, string $type, float $amount, string $description, ?string $idempotencyKey): WalletTransaction
    {
        if ($idempotencyKey) {
            $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                Log::info('Idempotent wallet transaction detected', [
                    'idempotency_key' => $idempotencyKey,
                    'existing_transaction_id' => $existing->id,
                    'wallet_id' => $wallet->id,
                ]);

                return $existing;
            }
        }

        $lockKey = "wallet:{$wallet->id}:lock";

        Log::debug('Attempting to acquire wallet lock', [
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
        ]);

        try {
            return Cache::lock($lockKey, self::LOCK_TIMEOUT)->block(self::LOCK_WAIT, function () use ($wallet, $type, $amount, $description, $idempotencyKey) {
                Log::debug('Wallet lock acquired', ['wallet_id' => $wallet->id]);

                return DB::transaction(function () use ($wallet, $type, $amount, $description, $idempotencyKey) {
                    $wallet = Wallet::lockForUpdate()->find($wallet->id);

                    $balanceBefore = $wallet->balance;

                    if ($type === 'debit') {
                        if ($wallet->balance < $amount) {
                            Log::warning('Insufficient wallet balance', [
                                'wallet_id' => $wallet->id,
                                'current_balance' => $wallet->balance,
                                'requested_amount' => $amount,
                            ]);

                            throw new \Exception("Insufficient balance. Available: {$wallet->balance}, Required: {$amount}");
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
                        'wallet_id' => $wallet->id,
                        'type' => $type,
                        'amount' => $amount,
                        'balance_before' => $balanceBefore,
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
                'lock_timeout' => self::LOCK_TIMEOUT,
                'lock_wait' => self::LOCK_WAIT,
            ]);

            throw new \Exception('System is busy processing another transaction. Please try again shortly.');
        }
    }

    private function clearWalletCache(int $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:wallet");
    }
}
