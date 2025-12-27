<?php

namespace App\Jobs\Booking;

use App\Domain\Booking\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private Booking $booking
    ) {}

    public function handle(): void
    {
        Log::info("Processing booking #{$this->booking->id}");

        $this->booking->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Booking #{$this->booking->id} failed: {$exception->getMessage()}");

        $this->booking->update(['status' => 'failed']);
    }
}
