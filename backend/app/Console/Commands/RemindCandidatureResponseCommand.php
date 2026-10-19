<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Services\CandidatureResponseDeadlineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lot B6 (D3) : relance unique du Producer quand il lui reste 24 h pour répondre aux
 * candidatures en attente (48 h après leur clôture pour une fenêtre pleine).
 */
class RemindCandidatureResponseCommand extends Command
{
    protected $signature = 'missions:remind-candidature-response';

    protected $description = 'Remind the Producer once when 24 h or less remain to answer pending candidatures.';

    public function __construct(
        private readonly CandidatureResponseDeadlineService $service,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Sur-ensemble SQL borné (date limite d'ici deux jours ou passée), échéance exacte en PHP.
        $missions = Mission::query()
            ->whereNotNull('date_limite_candidature')
            ->where('date_limite_candidature', '<=', now()->addDays(2)->toDateString())
            ->whereNull('candidature_response_reminder_sent_at')
            ->where('status', '!=', MissionStatus::PendingPayment->value)
            ->whereHas('candidatures', fn ($query) => $query->where('status', CandidatureStatus::Pending->value))
            ->with('producer.user')
            ->get();

        $sent = 0;

        foreach ($missions as $mission) {
            try {
                if ($this->service->remindProducer($mission)) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                Log::warning('Candidature response reminder failed', [
                    'mission_id' => $mission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
