<?php

namespace App\Providers;

use App\Domain\Booking\Contracts\BookingServiceInterface;
use App\Domain\Booking\Contracts\ExternalProviderInterface;
use App\Domain\Booking\Handlers\FlightBookingHandler;
use App\Domain\Booking\Handlers\HotelBookingHandler;
use App\Domain\Booking\Providers\AmadeusFlightProvider;
use App\Domain\Booking\Providers\BookingComHotelProvider;
use App\Domain\Booking\Services\BookingService;
use App\Domain\Employee\Contracts\EmployeeServiceInterface;
use App\Domain\Employee\Services\EmployeeService;
use App\Domain\Shared\Services\LockService;
use App\Domain\Tenant\Contracts\TenantServiceInterface;
use App\Domain\Tenant\Services\TenantService;
use App\Domain\Travel\Contracts\TravelRequestServiceInterface;
use App\Domain\Travel\Services\TravelRequestService;
use App\Domain\Wallet\Contracts\WalletServiceInterface;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public array $bindings = [
        WalletServiceInterface::class => WalletService::class,
        TravelRequestServiceInterface::class => TravelRequestService::class,
        BookingServiceInterface::class => BookingService::class,
        TenantServiceInterface::class => TenantService::class,
        EmployeeServiceInterface::class => EmployeeService::class,
    ];

    public function register(): void
    {
        $this->app->singleton(LockService::class, function () {
            return new LockService();
        });

        $this->app->bind(AmadeusFlightProvider::class, function () {
            return new AmadeusFlightProvider(
                apiKey: config('services.amadeus.key', 'demo-key'),
                apiSecret: config('services.amadeus.secret', 'demo-secret')
            );
        });

        $this->app->bind(BookingComHotelProvider::class, function () {
            return new BookingComHotelProvider(
                apiKey: config('services.booking_com.key', 'demo-key')
            );
        });

        $this->app->when(FlightBookingHandler::class)
            ->needs(ExternalProviderInterface::class)
            ->give(AmadeusFlightProvider::class);

        $this->app->when(HotelBookingHandler::class)
            ->needs(ExternalProviderInterface::class)
            ->give(BookingComHotelProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
