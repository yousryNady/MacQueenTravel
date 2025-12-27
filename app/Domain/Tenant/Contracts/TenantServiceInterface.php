<?php

namespace App\Domain\Tenant\Contracts;

use App\Domain\Tenant\Models\Tenant;
use Illuminate\Pagination\LengthAwarePaginator;

interface TenantServiceInterface
{
    public function paginate(): LengthAwarePaginator;

    public function create(array $data): Tenant;

    public function update(Tenant $tenant, array $data): Tenant;

    public function activate(Tenant $tenant): Tenant;

    public function deactivate(Tenant $tenant): Tenant;

    public function findBySlug(string $slug): ?Tenant;
}
