<?php

namespace App\Domain\Travel\Services;

use App\Domain\Travel\Contracts\TravelRequestServiceInterface;
use App\Domain\Travel\Models\TravelRequest;
use App\Domain\Wallet\Contracts\WalletServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TravelRequestService implements TravelRequestServiceInterface
{
    public function __construct(
        private WalletServiceInterface $walletService
    ) {}

    public function paginate(): LengthAwarePaginator
    {
        return TravelRequest::query()
            ->with(['employee', 'approver'])
            ->latest()
            ->paginate(15);
    }

    public function findById(int|string $id): TravelRequest
    {
        return TravelRequest::with(['employee', 'approver'])
            ->findOrFail($id);
    }

    public function create(array $data): TravelRequest
    {
        return TravelRequest::create($data);
    }

    public function approve(TravelRequest $travelRequest, int $approverId): TravelRequest
    {
        if (! $travelRequest->isPending()) {
            throw new \Exception('Only pending requests can be approved');
        }

        return DB::transaction(function () use ($travelRequest, $approverId) {
            $wallet = $this->walletService->getWallet($travelRequest->tenant_id);

            if (! $this->walletService->hasBalance($wallet, $travelRequest->estimated_cost)) {
                throw new \Exception('Insufficient wallet balance');
            }

            $travelRequest->update([
                'status' => 'approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            return $travelRequest->fresh();
        });
    }

    public function reject(TravelRequest $travelRequest, int $approverId, ?string $reason = null): TravelRequest
    {
        if (! $travelRequest->isPending()) {
            throw new \Exception('Only pending requests can be rejected');
        }

        $travelRequest->update([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'notes' => $reason,
        ]);

        return $travelRequest->fresh();
    }

    public function cancel(TravelRequest $travelRequest): TravelRequest
    {
        if ($travelRequest->status === 'cancelled') {
            throw new \Exception('Request is already cancelled');
        }

        $travelRequest->update(['status' => 'cancelled']);

        return $travelRequest->fresh();
    }
}
