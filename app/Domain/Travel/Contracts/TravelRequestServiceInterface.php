<?php

namespace App\Domain\Travel\Contracts;

use App\Domain\Travel\Models\TravelRequest;
use Illuminate\Pagination\LengthAwarePaginator;

interface TravelRequestServiceInterface
{
    public function paginate(): LengthAwarePaginator;

    public function findById(int|string $id): TravelRequest;

    public function create(array $data): TravelRequest;

    public function approve(TravelRequest $travelRequest, int $approverId): TravelRequest;

    public function reject(TravelRequest $travelRequest, int $approverId, ?string $reason = null): TravelRequest;

    public function cancel(TravelRequest $travelRequest): TravelRequest;
}
