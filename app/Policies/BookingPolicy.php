<?php

namespace App\Policies;

use App\Domain\Booking\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function confirm(User $user, Booking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id
            && $user->isManager();
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->tenant_id === $booking->tenant_id
            && $user->isAdmin();
    }
}
