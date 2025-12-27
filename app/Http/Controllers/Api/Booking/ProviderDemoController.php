<?php

namespace App\Http\Controllers\Api\Booking;

use App\Domain\Booking\Handlers\FlightBookingHandler;
use App\Domain\Booking\Handlers\HotelBookingHandler;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;

class ProviderDemoController extends BaseController
{
    public function __construct(
        private FlightBookingHandler $flightHandler,
        private HotelBookingHandler $hotelHandler
    ) {}


    public function demo(): JsonResponse
    {
        $flightResults = $this->flightHandler->searchFlights(
            origin: 'JFK',
            destination: 'LAX',
            date: '2025-03-15'
        );

        $hotelResults = $this->hotelHandler->searchHotels(
            destination: 'Los Angeles',
            checkIn: '2025-03-15',
            checkOut: '2025-03-20'
        );

        return $this->success([
            'contextual_binding_demo' => [
                'explanation' => 'Both handlers depend on ExternalProviderInterface, but receive different implementations',
                'flight_handler' => [
                    'class' => FlightBookingHandler::class,
                    'injected_provider' => $flightResults['provider'],
                    'results' => $flightResults,
                ],
                'hotel_handler' => [
                    'class' => HotelBookingHandler::class,
                    'injected_provider' => $hotelResults['provider'],
                    'results' => $hotelResults,
                ],
            ],
        ]);
    }
}
