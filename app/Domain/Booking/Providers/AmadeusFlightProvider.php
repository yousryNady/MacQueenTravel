<?php

namespace App\Domain\Booking\Providers;

use App\Domain\Booking\Contracts\ExternalProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmadeusFlightProvider implements ExternalProviderInterface
{
    private const API_BASE_URL = 'https://api.amadeus.com/v2';

    public function __construct(
        private string $apiKey,
        private string $apiSecret
    ) {}

    public function search(array $criteria): array
    {
        Log::info('Amadeus: Searching flights', $criteria);

        return [
            'provider' => $this->getProviderName(),
            'flights' => [
                [
                    'flight_number' => 'AA' . rand(100, 999),
                    'departure' => $criteria['origin'] ?? 'JFK',
                    'arrival' => $criteria['destination'] ?? 'LAX',
                    'price' => rand(200, 1500),
                    'currency' => 'USD',
                ],
            ],
        ];
    }

    public function book(array $details): array
    {
        Log::info('Amadeus: Booking flight', $details);

        return [
            'provider' => $this->getProviderName(),
            'confirmation_code' => 'AMD' . strtoupper(bin2hex(random_bytes(4))),
            'status' => 'confirmed',
            'booked_at' => now()->toIso8601String(),
        ];
    }

    public function cancel(string $reference): bool
    {
        Log::info('Amadeus: Cancelling booking', ['reference' => $reference]);

        return true;
    }

    public function getProviderName(): string
    {
        return 'Amadeus';
    }
}
