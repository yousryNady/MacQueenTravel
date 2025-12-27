<?php

namespace Database\Seeders;

use App\Domain\Employee\Models\Employee;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            Employee::factory()->count(10)->create([
                'tenant_id' => $tenant->id,
            ]);

            Employee::factory()->count(2)->inactive()->create([
                'tenant_id' => $tenant->id,
            ]);
        }
    }
}
