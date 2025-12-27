<?php

namespace Database\Seeders;

use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::factory()->create([
            'name' => 'Acme Corporation',
            'slug' => 'acme',
        ]);

        Tenant::factory()->create([
            'name' => 'Globex Inc',
            'slug' => 'globex',
        ]);

        Tenant::factory()->create([
            'name' => 'Wayne Enterprises',
            'slug' => 'wayne',
        ]);

        Tenant::factory()->count(2)->inactive()->create();
    }
}
