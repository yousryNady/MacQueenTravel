<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Booking\BookingController;
use App\Http\Controllers\Api\Employee\EmployeeController;
use App\Http\Controllers\Api\Tenant\TenantController;
use App\Http\Controllers\Api\TravelRequest\TravelRequestController;
use App\Http\Controllers\Api\Wallet\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum'])->prefix('tenants')->group(function () {
    Route::get('/', [TenantController::class, 'index']);
    Route::post('/', [TenantController::class, 'store']);
    Route::get('/{tenant}', [TenantController::class, 'show']);
    Route::put('/{tenant}', [TenantController::class, 'update']);
    Route::post('/{tenant}/activate', [TenantController::class, 'activate']);
    Route::post('/{tenant}/deactivate', [TenantController::class, 'deactivate']);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware(['tenant', 'tenant.ownership'])->group(function () {
        Route::prefix('employees')->middleware('prevent.escalation')->group(function () {
            Route::get('/', [EmployeeController::class, 'index']);
            Route::post('/', [EmployeeController::class, 'store']);
            Route::get('/{employee}', [EmployeeController::class, 'show']);
            Route::put('/{employee}', [EmployeeController::class, 'update']);
            Route::post('/{employee}/activate', [EmployeeController::class, 'activate']);
            Route::post('/{employee}/deactivate', [EmployeeController::class, 'deactivate']);
        });

        Route::prefix('wallet')->middleware('throttle:wallet')->group(function () {
            Route::get('/', [WalletController::class, 'show']);
            Route::post('/credit', [WalletController::class, 'credit']);
            Route::post('/debit', [WalletController::class, 'debit']);
        });

        Route::prefix('travel-requests')->group(function () {
            Route::get('/', [TravelRequestController::class, 'index']);
            Route::post('/', [TravelRequestController::class, 'store']);
            Route::get('/{travelRequest}', [TravelRequestController::class, 'show']);
            Route::post('/{travelRequest}/approve', [TravelRequestController::class, 'approve']);
            Route::post('/{travelRequest}/reject', [TravelRequestController::class, 'reject']);
            Route::post('/{travelRequest}/cancel', [TravelRequestController::class, 'cancel']);
        });

        Route::prefix('bookings')->middleware('throttle:booking')->group(function () {
            Route::get('/', [BookingController::class, 'index']);
            Route::post('/', [BookingController::class, 'store']);
            Route::get('/{booking}', [BookingController::class, 'show']);
            Route::post('/{booking}/confirm', [BookingController::class, 'confirm']);
            Route::post('/{booking}/cancel', [BookingController::class, 'cancel']);
        });
    });
});
