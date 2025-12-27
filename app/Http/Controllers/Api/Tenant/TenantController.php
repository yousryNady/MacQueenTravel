<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Domain\Tenant\Contracts\TenantServiceInterface;
use App\Domain\Tenant\Models\Tenant;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use Illuminate\Http\JsonResponse;

class TenantController extends BaseController
{
    public function __construct(
        private TenantServiceInterface $tenantService
    ) {}

    public function index(): JsonResponse
    {
        $tenants = Tenant::paginate(15);

        return $this->success($tenants);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->create($request->validated());

        return $this->created($tenant, 'Tenant created');
    }

    public function show(Tenant $tenant): JsonResponse
    {
        return $this->success($tenant);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantService->update($tenant, $request->validated());

        return $this->success($tenant, 'Tenant updated');
    }

    public function activate(Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantService->activate($tenant);

        return $this->success($tenant, 'Tenant activated');
    }

    public function deactivate(Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantService->deactivate($tenant);

        return $this->success($tenant, 'Tenant deactivated');
    }
}
