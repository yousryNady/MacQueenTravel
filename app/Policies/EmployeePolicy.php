<?php

namespace App\Policies;

use App\Domain\Employee\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->tenant_id === $employee->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->tenant_id === $employee->tenant_id
            && $user->isManager();
    }

    public function activate(User $user, Employee $employee): bool
    {
        return $user->tenant_id === $employee->tenant_id
            && $user->isAdmin();
    }

    public function deactivate(User $user, Employee $employee): bool
    {
        return $user->tenant_id === $employee->tenant_id
            && $user->isAdmin();
    }
}
