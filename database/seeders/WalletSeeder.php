<?php

namespace Database\Seeders;

use App\Domain\Tenant\Models\Tenant;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $wallet = Wallet::factory()->create([
                'tenant_id' => $tenant->id,
                'balance' => 50000.00,
            ]);

            WalletTransaction::factory()->count(5)->create([
                'wallet_id' => $wallet->id,
                'tenant_id' => $tenant->id,
                'type' => 'credit',
                'balance_before' => 40000.00,
                'balance_after' => 50000.00,
            ]);
        }
    }
}
