<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    public static function tenantKey(string $key): string
    {
        return 'tenant:'.tenant_id().':'.$key;
    }

    public static function remember(string $key, int $ttl, callable $callback)
    {
        return Cache::remember(self::tenantKey($key), $ttl, $callback);
    }

    public static function forget(string $key): bool
    {
        return Cache::forget(self::tenantKey($key));
    }

    public static function flush(string $pattern): void
    {
        $keys = Cache::get('tenant:'.tenant_id().':keys', []);

        foreach ($keys as $key) {
            if (str_contains($key, $pattern)) {
                Cache::forget($key);
            }
        }
    }
}
