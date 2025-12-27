<?php

namespace App\Http\Controllers\Api\Booking;

use App\Domain\Booking\Contracts\BookingServiceInterface;
use App\Domain\Booking\Models\Booking;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Booking\ConfirmBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use Illuminate\Http\JsonResponse;

class BookingController extends BaseController
{
    public function __construct(
        private BookingServiceInterface $bookingService
    ) {}

    public function index(): JsonResponse
    {
        $bookings = $this->bookingService->paginate();

        return $this->success($bookings);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $travelRequest = $this->bookingService->getTravelRequest($request->travel_request_id);

            $booking = $this->bookingService->createFromTravelRequest(
                $travelRequest,
                $request->type,
                $request->provider,
                $request->provider_data ?? []
            );

            return $this->created($booking, 'Booking created');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                return $this->error($e->getMessage(), 409);
            }

            if (str_contains($e->getMessage(), 'must be approved')) {
                return $this->error($e->getMessage(), 422);
            }

            if (str_contains($e->getMessage(), 'Insufficient balance')) {
                return $this->error($e->getMessage(), 422);
            }

            if (str_contains($e->getMessage(), 'Could not acquire lock') || str_contains($e->getMessage(), 'System is busy')) {
                return $this->error('System is busy processing another request. Please try again shortly.', 503);
            }

            throw $e;
        }
    }

    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);

        $booking = $this->bookingService->findById($booking->id);

        return $this->success($booking);
    }

    public function confirm(ConfirmBookingRequest $request, Booking $booking): JsonResponse
    {
        try {
            $this->authorize('confirm', $booking);

            $booking = $this->bookingService->confirm($booking, $request->provider_reference);

            return $this->success($booking, 'Booking confirmed');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Only pending')) {
                return $this->error($e->getMessage(), 422);
            }

            if (str_contains($e->getMessage(), 'Could not acquire lock')) {
                return $this->error('System is busy. Please try again shortly.', 503);
            }

            throw $e;
        }
    }

    public function cancel(Booking $booking): JsonResponse
    {
        try {
            $this->authorize('cancel', $booking);

            $booking = $this->bookingService->cancel($booking);

            return $this->success($booking, 'Booking cancelled');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already cancelled')) {
                return $this->error($e->getMessage(), 422);
            }

            if (str_contains($e->getMessage(), 'Could not acquire lock')) {
                return $this->error('System is busy. Please try again shortly.', 503);
            }

            throw $e;
        }
    }
}
