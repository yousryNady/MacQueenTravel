<?php

namespace App\Http\Controllers\Api\TravelRequest;

use App\Domain\Travel\Contracts\TravelRequestServiceInterface;
use App\Domain\Travel\Models\TravelRequest;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\TravelRequest\RejectTravelRequestRequest;
use App\Http\Requests\TravelRequest\StoreTravelRequestRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelRequestController extends BaseController
{
    public function __construct(
        private TravelRequestServiceInterface $travelRequestService
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(
            $this->travelRequestService->paginate()
        );
    }

    public function store(StoreTravelRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = tenant_id();

        $travelRequest = $this->travelRequestService->create($validated);

        return $this->created($travelRequest, 'Travel request created');
    }

    public function show(TravelRequest $travelRequest): JsonResponse
    {
        $this->authorize('view', $travelRequest);

        return $this->success(
            $this->travelRequestService->findById($travelRequest->id)
        );
    }

    public function approve(Request $request, TravelRequest $travelRequest): JsonResponse
    {
        $this->authorize('approve', $travelRequest);

        $travelRequest = $this->travelRequestService->approve(
            $travelRequest,
            $request->user()->id
        );

        return $this->success($travelRequest, 'Travel request approved');
    }

    public function reject(RejectTravelRequestRequest $request, TravelRequest $travelRequest): JsonResponse
    {
        $this->authorize('reject', $travelRequest);

        $travelRequest = $this->travelRequestService->reject(
            $travelRequest,
            $request->user()->id,
            $request->reason
        );

        return $this->success($travelRequest, 'Travel request rejected');
    }

    public function cancel(TravelRequest $travelRequest): JsonResponse
    {
        $this->authorize('cancel', $travelRequest);

        $travelRequest = $this->travelRequestService->cancel($travelRequest);

        return $this->success($travelRequest, 'Travel request cancelled');
    }
}
