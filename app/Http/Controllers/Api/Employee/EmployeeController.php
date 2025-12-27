<?php

namespace App\Http\Controllers\Api\Employee;

use App\Domain\Employee\Contracts\EmployeeServiceInterface;
use App\Domain\Employee\Models\Employee;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use Illuminate\Http\JsonResponse;

class EmployeeController extends BaseController
{
    public function __construct(
        private EmployeeServiceInterface $employeeService
    ) {}

    public function index(): JsonResponse
    {
        $employees = $this->employeeService->list();

        return $this->success($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = tenant_id();

        $employee = $this->employeeService->create($validated);

        return $this->created($employee, 'Employee created');
    }

    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        return $this->success($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        $employee = $this->employeeService->update($employee, $request->validated());

        return $this->success($employee, 'Employee updated');
    }

    public function activate(Employee $employee): JsonResponse
    {
        $this->authorize('activate', $employee);

        $employee = $this->employeeService->activate($employee);

        return $this->success($employee, 'Employee activated');
    }

    public function deactivate(Employee $employee): JsonResponse
    {
        $this->authorize('deactivate', $employee);

        $employee = $this->employeeService->deactivate($employee);

        return $this->success($employee, 'Employee deactivated');
    }
}
