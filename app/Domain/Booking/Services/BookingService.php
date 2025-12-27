<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\BookingServiceInterface;
use App\Domain\Booking\Models\Booking;
use App\Domain\Shared\Services\LockService;
use App\Domain\Travel\Models\TravelRequest;
use App\Domain\Wallet\Contracts\WalletServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingService implements BookingServiceInterface
{
    private const BOOKING_LOCK_TTL = 30;

    public function __construct(
        private WalletServiceInterface $walletService,
        private LockService $lockService
    ) {}

    public function paginate(): LengthAwarePaginator
    {
        return Booking::query()
            ->with(['travelRequest', 'employee'])
            ->latest()
            ->paginate(15);
    }

    public function findById(int|string $id): Booking
    {
        return Booking::with(['travelRequest', 'employee'])
            ->findOrFail($id);
    }

    public function getTravelRequest(int|string $id): TravelRequest
    {
        return TravelRequest::findOrFail($id);
    }

    public function createFromTravelRequest(TravelRequest $travelRequest, string $type, string $provider, array $providerData): Booking
    {
        Log::info('Creating booking from travel request', [
            'travel_request_id' => $travelRequest->id,
            'type' => $type,
            'provider' => $provider,
        ]);

        if (! $travelRequest->isApproved()) {
            Log::warning('Attempted to create booking for non-approved request', [
                'travel_request_id' => $travelRequest->id,
                'status' => $travelRequest->status,
            ]);

            throw new \Exception('Travel request must be approved before booking');
        }

        return $this->lockService->executeWithLock(
            $travelRequest,
            'booking',
            function () use ($travelRequest, $type, $provider, $providerData) {
                return DB::transaction(function () use ($travelRequest, $type, $provider, $providerData) {
                    $existingBooking = Booking::where('travel_request_id', $travelRequest->id)
                        ->where('type', $type)
                        ->whereIn('status', ['pending', 'confirmed'])
                        ->first();

                    if ($existingBooking) {
                        Log::warning('Duplicate booking attempt blocked', [
                            'travel_request_id' => $travelRequest->id,
                            'type' => $type,
                            'existing_booking_id' => $existingBooking->id,
                        ]);

                        throw new \Exception("A {$type} booking already exists for this travel request");
                    }

                    $amount = $providerData['amount'] ?? $travelRequest->estimated_cost;

                    $wallet = $this->walletService->getWallet($travelRequest->tenant_id);

                    $idempotencyKey = 'booking-' . Str::uuid()->toString();

                    $this->walletService->debit(
                        $wallet,
                        $amount,
                        "Booking for travel request #{$travelRequest->id}",
                        $idempotencyKey
                    );

                    $booking = Booking::create([
                        'tenant_id' => $travelRequest->tenant_id,
                        'travel_request_id' => $travelRequest->id,
                        'employee_id' => $travelRequest->employee_id,
                        'type' => $type,
                        'status' => 'pending',
                        'provider' => $provider,
                        'amount' => $amount,
                        'provider_data' => $providerData,
                        'booked_at' => now(),
                    ]);

                    Log::info('Booking created successfully', [
                        'booking_id' => $booking->id,
                        'travel_request_id' => $travelRequest->id,
                        'amount' => $amount,
                    ]);

                    return $booking;
                });
            },
            self::BOOKING_LOCK_TTL
        );
    }

    public function confirm(Booking $booking, string $providerReference): Booking
    {
        Log::info('Confirming booking', [
            'booking_id' => $booking->id,
            'provider_reference' => $providerReference,
        ]);

        if ($booking->status !== 'pending') {
            Log::warning('Attempted to confirm non-pending booking', [
                'booking_id' => $booking->id,
                'current_status' => $booking->status,
            ]);

            throw new \Exception('Only pending bookings can be confirmed');
        }

        return $this->lockService->executeWithLock(
            $booking,
            'confirm',
            function () use ($booking, $providerReference) {
                $booking->update([
                    'status' => 'confirmed',
                    'provider_reference' => $providerReference,
                    'confirmed_at' => now(),
                ]);

                Log::info('Booking confirmed', [
                    'booking_id' => $booking->id,
                    'provider_reference' => $providerReference,
                ]);

                return $booking->fresh();
            }
        );
    }

    public function cancel(Booking $booking): Booking
    {
        Log::info('Cancelling booking', ['booking_id' => $booking->id]);

        if ($booking->status === 'cancelled') {
            Log::warning('Attempted to cancel already cancelled booking', [
                'booking_id' => $booking->id,
            ]);

            throw new \Exception('Booking is already cancelled');
        }

        return $this->lockService->executeWithLock(
            $booking,
            'cancel',
            function () use ($booking) {
                return DB::transaction(function () use ($booking) {
                    $wallet = $this->walletService->getWallet($booking->tenant_id);

                    $idempotencyKey = "refund-booking-{$booking->id}";

                    $this->walletService->credit(
                        $wallet,
                        $booking->amount,
                        "Refund for cancelled booking #{$booking->id}",
                        $idempotencyKey
                    );

                    $booking->update(['status' => 'cancelled']);

                    Log::info('Booking cancelled and refunded', [
                        'booking_id' => $booking->id,
                        'refund_amount' => $booking->amount,
                    ]);

                    return $booking->fresh();
                });
            }
        );
    }
}
