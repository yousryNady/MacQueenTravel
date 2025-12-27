<?php

namespace Database\Seeders;

use App\Domain\Employee\Models\Employee;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Travel\Models\TravelRequest;
use Illuminate\Database\Seeder;

class TravelRequestSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $employees = Employee::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->get();

            if ($employees->isEmpty()) {
                continue;
            }

            foreach ($employees->take(5) as $employee) {
                TravelRequest::factory()->pending()->create([
                    'tenant_id' => $tenant->id,
                    'employee_id' => $employee->id,
                ]);
            }

            $approver = $employees->first();

            foreach ($employees->skip(5)->take(3) as $employee) {
                TravelRequest::factory()->approved()->create([
                    'tenant_id' => $tenant->id,
                    'employee_id' => $employee->id,
                    'approved_by' => $approver->id,
                ]);
            }

            TravelRequest::factory()->rejected()->create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employees->random()->id,
                'approved_by' => $approver->id,
            ]);

            TravelRequest::factory()->cancelled()->create([
                'tenant_id' => $tenant->id,
                'employee_id' => $employees->random()->id,
            ]);
        }
    }
}
