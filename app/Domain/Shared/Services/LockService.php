<?php

namespace App\Domain\Shared\Services;

use App\Domain\Shared\Models\Lock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LockService
{
    private const DEFAULT_TTL = 30;
    private const MAX_RETRY_ATTEMPTS = 3;
    private const RETRY_DELAY_MS = 100;

    public function acquire(Model $model, string $action, ?int $ttl = null): ?string
    {
        $ttl = $ttl ?? self::DEFAULT_TTL;
        $lockKey = $this->generateLockKey($model, $action);
        $owner = Str::uuid()->toString();

        $this->releaseExpiredLocks();

        for ($attempt = 1; $attempt <= self::MAX_RETRY_ATTEMPTS; $attempt++) {
            $existingLock = Lock::where('lock_key', $lockKey)->first();

            if ($existingLock && ! $existingLock->isExpired()) {
                Log::debug('Lock already held, retrying', [
                    'lock_key' => $lockKey,
                    'attempt' => $attempt,
                    'max_attempts' => self::MAX_RETRY_ATTEMPTS,
                ]);

                if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                    usleep(self::RETRY_DELAY_MS * 1000);
                    continue;
                }

                return null;
            }

            if ($existingLock) {
                $existingLock->delete();
            }

            try {
                Lock::create([
                    'lockable_type' => get_class($model),
                    'lockable_id' => $model->id,
                    'lock_key' => $lockKey,
                    'owner' => $owner,
                    'expires_at' => now()->addSeconds($ttl),
                ]);

                Log::debug('Lock acquired', [
                    'lock_key' => $lockKey,
                    'owner' => $owner,
                    'ttl' => $ttl,
                ]);

                return $owner;
            } catch (\Exception $e) {
                Log::warning('Failed to create lock, may be race condition', [
                    'lock_key' => $lockKey,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRY_ATTEMPTS) {
                    usleep(self::RETRY_DELAY_MS * 1000);
                    continue;
                }
            }
        }

        return null;
    }

    public function release(Model $model, string $action, string $owner): bool
    {
        $lockKey = $this->generateLockKey($model, $action);

        $deleted = Lock::where('lock_key', $lockKey)
            ->where('owner', $owner)
            ->delete();

        if ($deleted > 0) {
            Log::debug('Lock released', [
                'lock_key' => $lockKey,
                'owner' => $owner,
            ]);
        }

        return $deleted > 0;
    }

    public function isLocked(Model $model, string $action): bool
    {
        $lockKey = $this->generateLockKey($model, $action);

        $lock = Lock::where('lock_key', $lockKey)->first();

        return $lock && ! $lock->isExpired();
    }

    public function executeWithLock(Model $model, string $action, callable $callback, ?int $ttl = null)
    {
        $owner = $this->acquire($model, $action, $ttl);

        if (! $owner) {
            Log::error('Failed to acquire lock', [
                'model' => get_class($model),
                'model_id' => $model->id,
                'action' => $action,
            ]);

            throw new \Exception('Could not acquire lock. Resource is busy. Please try again shortly.');
        }

        try {
            $result = $callback();

            return $result;
        } catch (\Exception $e) {
            Log::error('Error during locked operation', [
                'model' => get_class($model),
                'model_id' => $model->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $this->release($model, $action, $owner);
        }
    }

    private function generateLockKey(Model $model, string $action): string
    {
        return sprintf('%s:%s:%s', class_basename($model), $model->id, $action);
    }

    private function releaseExpiredLocks(): void
    {
        $deleted = Lock::where('expires_at', '<', now())->delete();

        if ($deleted > 0) {
            Log::debug('Released expired locks', ['count' => $deleted]);
        }
    }
}
