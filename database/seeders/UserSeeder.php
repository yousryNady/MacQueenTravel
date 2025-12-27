<?php

namespace Database\Seeders;

use App\Domain\Tenant\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            User::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Admin '.$tenant->name,
                'email' => 'admin@'.$tenant->slug.'.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]);

            User::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Manager '.$tenant->name,
                'email' => 'manager@'.$tenant->slug.'.com',
                'password' => Hash::make('password'),
                'role' => 'manager',
            ]);

            User::factory()->count(3)->create([
                'tenant_id' => $tenant->id,
                'role' => 'employee',
            ]);
        }
    }
}
