<?php

namespace App\Policies;

use App\Domain\Wallet\Models\Wallet;
use App\Models\User;

class WalletPolicy
{
    public function view(User $user, Wallet $wallet): bool
    {
        return $user->tenant_id === $wallet->tenant_id;
    }

    public function credit(User $user, Wallet $wallet): bool
    {
        return $user->tenant_id === $wallet->tenant_id
            && $user->isAdmin();
    }

    public function debit(User $user, Wallet $wallet): bool
    {
        return $user->tenant_id === $wallet->tenant_id
            && $user->isManager();
    }
}
