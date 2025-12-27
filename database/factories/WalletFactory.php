<?php

namespace Database\Factories;

use App\Domain\Tenant\Models\Tenant;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'balance' => fake()->randomFloat(2, 1000, 100000),
            'currency' => 'USD',
        ];
    }

    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 0,
        ]);
    }

    public function lowBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => fake()->randomFloat(2, 10, 100),
        ]);
    }
}
