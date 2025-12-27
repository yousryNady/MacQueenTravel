<?php

namespace Database\Seeders;

use App\Domain\Booking\Models\Booking;
use App\Domain\Tenant\Models\Tenant;
use App\Domain\Travel\Models\TravelRequest;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::where('is_active', true)->get();

        foreach ($tenants as $tenant) {
            $approvedRequests = TravelRequest::where('tenant_id', $tenant->id)
                ->where('status', 'approved')
                ->get();

            foreach ($approvedRequests as $request) {
                if ($request->type === 'flight' || $request->type === 'both') {
                    Booking::factory()->flight()->confirmed()->create([
                        'tenant_id' => $tenant->id,
                        'travel_request_id' => $request->id,
                        'employee_id' => $request->employee_id,
                    ]);
                }

                if ($request->type === 'hotel' || $request->type === 'both') {
                    Booking::factory()->hotel()->pending()->create([
                        'tenant_id' => $tenant->id,
                        'travel_request_id' => $request->id,
                        'employee_id' => $request->employee_id,
                    ]);
                }
            }
        }
    }
}
