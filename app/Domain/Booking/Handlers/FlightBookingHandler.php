<?php

namespace App\Domain\Booking\Handlers;

use App\Domain\Booking\Contracts\ExternalProviderInterface;
use Illuminate\Support\Facades\Log;

class FlightBookingHandler
{
    public function __construct(
        private ExternalProviderInterface $provider
    ) {}

    public function searchFlights(string $origin, string $destination, string $date): array
    {
        Log::info('FlightBookingHandler: Using provider', [
            'provider' => $this->provider->getProviderName(),
        ]);

        return $this->provider->search([
            'origin' => $origin,
            'destination' => $destination,
            'date' => $date,
        ]);
    }

    public function bookFlight(array $flightDetails, array $passengerInfo): array
    {
        return $this->provider->book([
            'flight' => $flightDetails,
            'passenger' => $passengerInfo,
        ]);
    }

    public function cancelFlight(string $reference): bool
    {
        return $this->provider->cancel($reference);
    }
}
