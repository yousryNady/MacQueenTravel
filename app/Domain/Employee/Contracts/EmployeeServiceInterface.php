<?php

namespace App\Domain\Employee\Contracts;

use App\Domain\Employee\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

interface EmployeeServiceInterface
{
    public function list(int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Employee;

    public function update(Employee $employee, array $data): Employee;

    public function activate(Employee $employee): Employee;

    public function deactivate(Employee $employee): Employee;

    public function findByEmail(string $email): ?Employee;
}
