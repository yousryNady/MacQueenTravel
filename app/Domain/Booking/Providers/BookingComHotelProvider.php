<?php

namespace App\Domain\Booking\Providers;

use App\Domain\Booking\Contracts\ExternalProviderInterface;
use Illuminate\Support\Facades\Log;

class BookingComHotelProvider implements ExternalProviderInterface
{
    private const API_BASE_URL = 'https://api.booking.com/v1';

    public function __construct(
        private string $apiKey
    ) {}

    public function search(array $criteria): array
    {
        Log::info('Booking.com: Searching hotels', $criteria);

        return [
            'provider' => $this->getProviderName(),
            'hotels' => [
                [
                    'hotel_name' => 'Grand Hotel ' . ($criteria['destination'] ?? 'City'),
                    'location' => $criteria['destination'] ?? 'Unknown',
                    'price_per_night' => rand(80, 500),
                    'currency' => 'USD',
                    'rating' => rand(3, 5),
                ],
            ],
        ];
    }

    public function book(array $details): array
    {
        Log::info('Booking.com: Booking hotel', $details);

        return [
            'provider' => $this->getProviderName(),
            'confirmation_code' => 'BKG' . strtoupper(bin2hex(random_bytes(4))),
            'status' => 'confirmed',
            'booked_at' => now()->toIso8601String(),
        ];
    }

    public function cancel(string $reference): bool
    {
        Log::info('Booking.com: Cancelling booking', ['reference' => $reference]);

        return true;
    }

    public function getProviderName(): string
    {
        return 'Booking.com';
    }
}
