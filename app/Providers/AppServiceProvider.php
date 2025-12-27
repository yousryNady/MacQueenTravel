<?php

namespace App\Providers;

use App\Domain\Booking\Models\Booking;
use App\Domain\Employee\Models\Employee;
use App\Domain\Travel\Models\TravelRequest;
use App\Domain\Wallet\Models\Wallet;
use App\Policies\BookingPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\TravelRequestPolicy;
use App\Policies\WalletPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurePolicies();
        $this->configureRateLimiting();
    }

    private function configurePolicies(): void
    {
        Gate::policy(TravelRequest::class, TravelRequestPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Wallet::class, WalletPolicy::class);

        Gate::define('manage-tenant', function ($user) {
            return $user->isAdmin();
        });

        Gate::define('manage-employees', function ($user) {
            return $user->isManager();
        });

        Gate::define('approve-requests', function ($user) {
            return $user->isManager();
        });

        Gate::define('manage-wallet', function ($user) {
            return $user->isAdmin();
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('wallet', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('booking', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }
}
