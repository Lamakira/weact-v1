<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DateChange\BookingDateChangeService;
use App\Services\DateChange\MissionDateChangeService;
use Illuminate\Console\Command;

/**
 * Changements de date en attente (missions payées et bookings) : relance unique à mi-parcours,
 * puis expiration à l'échéance (mission : retrait des Faces sans réponse ; booking : la date reste).
 * Idempotent : une exécution répétée ne relance ni n'expire deux fois.
 */
class ProcessDateChangesCommand extends Command
{
    protected $signature = 'date-changes:process';

    protected $description = 'Remind and expire pending mission and booking date change requests.';

    public function __construct(
        private readonly MissionDateChangeService $missions,
        private readonly BookingDateChangeService $bookings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Expiration d'abord : une demande échue ne doit jamais être relancée.
        $missionExpiry = $this->missions->expireDue();
        $bookingExpiry = $this->bookings->expireDue();
        $missionReminders = $this->missions->sendReminders();
        $bookingReminders = $this->bookings->sendReminders();

        $this->info("Mission date changes expired: {$missionExpiry['processed']}, reminders: {$missionReminders}.");
        $this->info("Booking date changes expired: {$bookingExpiry['processed']}, reminders: {$bookingReminders}.");

        $failures = $missionExpiry['failures'] + $bookingExpiry['failures'];

        if ($failures > 0) {
            $this->error("Failures: {$failures}.");
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
