<?php

namespace App\Domain\Booking\Handlers;

use App\Domain\Booking\Contracts\ExternalProviderInterface;
use Illuminate\Support\Facades\Log;

class HotelBookingHandler
{
    public function __construct(
        private ExternalProviderInterface $provider
    ) {}

    public function searchHotels(string $destination, string $checkIn, string $checkOut): array
    {
        Log::info('HotelBookingHandler: Using provider', [
            'provider' => $this->provider->getProviderName(),
        ]);

        return $this->provider->search([
            'destination' => $destination,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
        ]);
    }

    public function bookHotel(array $hotelDetails, array $guestInfo): array
    {
        return $this->provider->book([
            'hotel' => $hotelDetails,
            'guest' => $guestInfo,
        ]);
    }

    public function cancelHotel(string $reference): bool
    {
        return $this->provider->cancel($reference);
    }
}
