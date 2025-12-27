<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Domain\Wallet\Contracts\WalletServiceInterface;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Wallet\WalletTransactionRequest;
use Illuminate\Http\JsonResponse;

class WalletController extends BaseController
{
    public function __construct(
        private WalletServiceInterface $walletService
    ) {}

    public function show(): JsonResponse
    {
        $wallet = $this->walletService->getWallet(tenant_id());

        $this->authorize('view', $wallet);

        return $this->success($wallet);
    }

    public function credit(WalletTransactionRequest $request): JsonResponse
    {
        try {
            $wallet = $this->walletService->getWallet(tenant_id());

            $this->authorize('credit', $wallet);

            $transaction = $this->walletService->credit(
                $wallet,
                $request->amount,
                $request->description,
                $request->idempotency_key
            );

            return $this->created($transaction, 'Credit successful');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'System is busy')) {
                return $this->error($e->getMessage(), 503);
            }

            throw $e;
        }
    }

    public function debit(WalletTransactionRequest $request): JsonResponse
    {
        try {
            $wallet = $this->walletService->getWallet(tenant_id());

            $this->authorize('debit', $wallet);

            $transaction = $this->walletService->debit(
                $wallet,
                $request->amount,
                $request->description,
                $request->idempotency_key
            );

            return $this->created($transaction, 'Debit successful');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Insufficient balance')) {
                return $this->error($e->getMessage(), 422);
            }

            if (str_contains($e->getMessage(), 'System is busy')) {
                return $this->error($e->getMessage(), 503);
            }

            throw $e;
        }
    }
}
