<?php

namespace App\Domain\Booking\Contracts;

use App\Domain\Booking\Models\Booking;
use App\Domain\Travel\Models\TravelRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookingServiceInterface
{
    public function createFromTravelRequest(TravelRequest $travelRequest, string $type, string $provider, array $providerData): Booking;

    public function confirm(Booking $booking, string $providerReference): Booking;

    public function cancel(Booking $booking): Booking;

    public function paginate(): LengthAwarePaginator;

    public function findById(int|string $id): Booking;

    public function getTravelRequest(int|string $id): TravelRequest;
}
