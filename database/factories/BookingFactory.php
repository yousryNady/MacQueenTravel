<?php

namespace Database\Factories;

use App\Domain\Booking\Models\Booking;
use App\Domain\Employee\Models\Employee;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Travel\Models\TravelRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['flight', 'hotel']);

        return [
            'tenant_id' => Tenant::factory(),
            'travel_request_id' => TravelRequest::factory(),
            'employee_id' => Employee::factory(),
            'type' => $type,
            'status' => 'pending',
            'provider' => $type === 'flight'
                ? fake()->randomElement(['Amadeus', 'Sabre', 'Travelport'])
                : fake()->randomElement(['Booking.com', 'Expedia', 'Hotels.com']),
            'provider_reference' => null,
            'amount' => fake()->randomFloat(2, 200, 5000),
            'currency' => 'USD',
            'provider_data' => [
                'confirmation_number' => fake()->bothify('??###??'),
            ],
            'booked_at' => now(),
            'confirmed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'provider_reference' => null,
            'confirmed_at' => null,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'provider_reference' => fake()->bothify('REF-#####-???'),
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function flight(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'flight',
            'provider' => fake()->randomElement(['Amadeus', 'Sabre', 'Travelport']),
            'provider_data' => [
                'flight_number' => fake()->bothify('??###'),
                'airline' => fake()->randomElement(['Delta', 'United', 'American', 'Southwest']),
                'class' => fake()->randomElement(['economy', 'business', 'first']),
            ],
        ]);
    }

    public function hotel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'hotel',
            'provider' => fake()->randomElement(['Booking.com', 'Expedia', 'Hotels.com']),
            'provider_data' => [
                'hotel_name' => fake()->company().' Hotel',
                'room_type' => fake()->randomElement(['single', 'double', 'suite']),
                'nights' => fake()->numberBetween(1, 14),
            ],
        ]);
    }
}
