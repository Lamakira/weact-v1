<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Booking;
use App\Models\Mission;
use App\Services\Ugc\UgcRefundService;
use Illuminate\Console\Command;

class ExpireUnacceptedUgcDealsCommand extends Command
{
    protected $signature = 'ugc:expire-unaccepted-deals';

    protected $description = 'Expire UGC deals (paid bookings past the acceptance window, paid missions past deadline with no engagement) and request the producer commission refund';

    public function __construct(
        private readonly UgcRefundService $refundService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // --- Bookings : commission payée, fenêtre d'acceptation dépassée (D-2.5.c) ---
        // BINARY : aligne SQL (collation _ci) sur le PHP `=== 'UGC'` (leçon 2.4) —
        // ici en match POSITIF : seul un vrai 'UGC' entre dans le chemin refund.
        // Fallback 7 j en dur : un cache config stale (clé absente) donnerait
        // subDays(0) → cutoff = now() → expiration massive de tout l'encours.
        $cutoff = now()->subDays((int) config('ugc.acceptance_window_days', 7));

        $bookings = Booking::where('status', BookingStatus::CommissionPaid->value)
            ->whereRaw("BINARY type_contenu = 'UGC'")
            ->whereNotNull('commission_paid_at')
            ->where('commission_paid_at', '<=', $cutoff)
            ->whereNull('commission_refunded_at') // refund hors-procédure : deal mort, rien à expirer (D-2.5.h)
            ->get();

        $this->info("Found {$bookings->count()} UGC booking(s) past the acceptance window.");

        $expired = 0;

        foreach ($bookings as $booking) {
            if ($this->refundService->expireBookingPastAcceptanceWindow($booking)) {
                $this->info("Expired UGC booking #{$booking->id} — refund requested.");
                $expired++;
            }
        }

        // --- Missions : publiées+payées, deadline passée, zéro engagement (D-2.5.d) ---
        $missions = Mission::where('status', MissionStatus::Published->value)
            ->where('type_mission', MissionType::Ugc->value)
            ->whereNotNull('commission_paid_at')
            ->whereDate('date_limite_candidature', '<', now()->toDateString())
            ->whereDoesntHave('candidatures', fn ($q) => $q->whereIn('status', [
                CandidatureStatus::Confirmed->value,
                CandidatureStatus::InProgress->value,
                CandidatureStatus::Completed->value,
            ]))
            ->get();

        $this->info("Found {$missions->count()} UGC mission(s) past deadline with no engagement.");

        $closed = 0;

        foreach ($missions as $mission) {
            if ($this->refundService->closeMissionPastDeadlineWithoutEngagement($mission)) {
                $this->info("Closed UGC mission #{$mission->id} — refund requested.");
                $closed++;
            }
        }

        $this->info("Done. Bookings expired: {$expired}, missions closed: {$closed}.");

        return self::SUCCESS;
    }
}
