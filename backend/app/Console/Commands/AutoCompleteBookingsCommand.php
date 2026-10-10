<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Console\Command;

class AutoCompleteBookingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bookings:auto-complete';

    /**
     * The console command description.
     */
    protected $description = 'Auto-complete bookings where 72 hours have elapsed since date_fin without mutual confirmation, and reminded Paid bookings 7 days after the shoot day ends';

    public function __construct(
        private readonly BookingService $bookingService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subHours(72);

        $bookings = Booking::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($confirmed) use ($cutoff): void {
                    $confirmed->whereIn('status', [
                        BookingStatus::ConfirmedByFace->value,
                        BookingStatus::ConfirmedByProducer->value,
                    ])->where('date_fin', '<=', $cutoff);
                })->orWhere(function ($silent): void {
                    // Booking payé sans suite, relancé : présélection large, la règle exacte
                    // (échéance datée : jour J + 8, relance + 6 j) est revérifiée sous verrou
                    // par BookingService::autoComplete.
                    $silent->where('status', BookingStatus::Paid->value)
                        ->whereNotNull('completion_reminder_sent_at')
                        ->whereNull('settlement_due_at')
                        ->where('date_fin', '<=', now()->subDays(BookingService::SILENT_AUTO_COMPLETE_AFTER_DAYS - 1));
                });
            })
            ->get();

        $this->info("Found {$bookings->count()} booking(s) to auto-complete.");

        $completed = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            try {
                $this->bookingService->autoComplete($booking);
                $this->info("Auto-completed booking #{$booking->id}");
                $completed++;
            } catch (\Throwable $e) {
                $this->error("Failed to auto-complete booking #{$booking->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Completed: {$completed}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
