<?php

namespace App\Policies;

use App\Domain\Travel\Models\TravelRequest;
use App\Models\User;

class TravelRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TravelRequest $travelRequest): bool
    {
        return $user->tenant_id === $travelRequest->tenant_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user, TravelRequest $travelRequest): bool
    {
        return $user->tenant_id === $travelRequest->tenant_id
            && $user->isManager();
    }

    public function reject(User $user, TravelRequest $travelRequest): bool
    {
        return $user->tenant_id === $travelRequest->tenant_id
            && $user->isManager();
    }

    public function cancel(User $user, TravelRequest $travelRequest): bool
    {
        return $user->tenant_id === $travelRequest->tenant_id
            && ($user->isManager() || $travelRequest->employee_id === $user->id);
    }
}
