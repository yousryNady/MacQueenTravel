<?php

namespace App\Domain\Employee\Services;

use App\Domain\Employee\Contracts\EmployeeServiceInterface;
use App\Domain\Employee\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

class EmployeeService implements EmployeeServiceInterface
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Employee::paginate($perPage);
    }

    public function create(array $data): Employee
    {
        return Employee::create($data);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee->fresh();
    }

    public function activate(Employee $employee): Employee
    {
        $employee->update(['is_active' => true]);

        return $employee->fresh();
    }

    public function deactivate(Employee $employee): Employee
    {
        $employee->update(['is_active' => false]);

        return $employee->fresh();
    }

    public function findByEmail(string $email): ?Employee
    {
        return Employee::where('email', $email)->first();
    }
}
