<?php

namespace App\Domain\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;

trait QueryOptimization
{
    public function scopeOptimized(Builder $query, array $relations = []): Builder
    {
        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query;
    }

    public function scopePaginateOptimized(Builder $query, int $perPage = 15, array $relations = [])
    {
        return $query->optimized($relations)->paginate($perPage);
    }

    public function scopeCursorPaginateOptimized(Builder $query, int $perPage = 15, array $relations = [])
    {
        return $query->optimized($relations)->cursorPaginate($perPage);
    }
}
