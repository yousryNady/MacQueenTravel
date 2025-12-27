<?php

namespace Database\Factories;

use App\Domain\Employee\Models\Employee;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Travel\Models\TravelRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class TravelRequestFactory extends Factory
{
    protected $model = TravelRequest::class;

    public function definition(): array
    {
        $departureDate = fake()->dateTimeBetween('+1 week', '+2 months');
        $returnDate = fake()->dateTimeBetween($departureDate, '+3 months');

        return [
            'tenant_id' => Tenant::factory(),
            'employee_id' => Employee::factory(),
            'approved_by' => null,
            'type' => fake()->randomElement(['flight', 'hotel', 'both']),
            'status' => 'pending',
            'destination' => fake()->city().', '.fake()->country(),
            'departure_date' => $departureDate,
            'return_date' => $returnDate,
            'estimated_cost' => fake()->randomFloat(2, 500, 10000),
            'purpose' => fake()->sentence(),
            'notes' => fake()->optional()->paragraph(),
            'approved_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => Employee::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => Employee::factory(),
            'notes' => 'Budget exceeded',
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
        ]);
    }

    public function hotel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'hotel',
        ]);
    }
}
