<?php

namespace App\Domain\Wallet\Contracts;

use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Models\WalletTransaction;

interface WalletServiceInterface
{
    public function getWallet(int $tenantId): Wallet;

    public function credit(Wallet $wallet, float $amount, string $description, ?string $idempotencyKey = null): WalletTransaction;

    public function debit(Wallet $wallet, float $amount, string $description, ?string $idempotencyKey = null): WalletTransaction;

    public function hasBalance(Wallet $wallet, float $amount): bool;
}
