<?php

namespace Database\Factories;

use App\Domain\Tenant\Models\Tenant;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['credit', 'debit']);
        $amount = fake()->randomFloat(2, 100, 5000);
        $balanceBefore = fake()->randomFloat(2, 5000, 50000);
        $balanceAfter = $type === 'credit'
            ? $balanceBefore + $amount
            : $balanceBefore - $amount;

        return [
            'wallet_id' => Wallet::factory(),
            'tenant_id' => Tenant::factory(),
            'idempotency_key' => Str::uuid()->toString(),
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => fake()->sentence(),
            'reference' => fake()->optional()->uuid(),
        ];
    }

    public function credit(): static
    {
        return $this->state(function (array $attributes) {
            $amount = fake()->randomFloat(2, 100, 5000);

            return [
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $attributes['balance_before'] + $amount,
                'description' => 'Wallet top-up',
            ];
        });
    }

    public function debit(): static
    {
        return $this->state(function (array $attributes) {
            $amount = fake()->randomFloat(2, 100, 2000);

            return [
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $attributes['balance_before'] - $amount,
                'description' => 'Booking payment',
            ];
        });
    }
}
