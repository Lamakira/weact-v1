<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\MissionPayment;
use App\Services\MissionPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireUnpaidMissionSelectionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'missions:expire-unpaid-selections';

    /**
     * The console command description.
     */
    protected $description = 'Cancel mission selections whose payment was not completed within 24 hours of the confirmation.';

    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $payments = MissionPayment::query()
            ->where('status', MissionPaymentStatus::Pending->value)
            ->where('created_at', '<=', now()->subHours(MissionPaymentService::SELECTION_PAYMENT_WINDOW_HOURS))
            ->whereHas('mission', fn ($query) => $query->where('status', MissionStatus::PendingPayment->value))
            ->get();

        $this->info("Found {$payments->count()} unpaid mission selection(s) past the deadline.");

        $counts = [];
        $hadError = false;

        foreach ($payments as $payment) {
            try {
                $outcome = $this->missionPaymentService->settleOrResetPendingSelection(
                    $payment,
                    'selection_payment_expired',
                    respectOpenCheckout: true,
                );
                $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            } catch (\Throwable $e) {
                $hadError = true;
                Log::critical('Unpaid mission selection expiry failed', [
                    'mission_payment_id' => $payment->id,
                    'mission_id' => $payment->mission_id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed for mission payment #{$payment->id}: {$e->getMessage()}");
            }
        }

        foreach ([
            MissionPaymentService::SELECTION_RESET,
            MissionPaymentService::SELECTION_SETTLED,
            MissionPaymentService::SELECTION_IN_FLIGHT,
            MissionPaymentService::SELECTION_UNVERIFIABLE,
            MissionPaymentService::SELECTION_NOOP,
        ] as $outcome) {
            $this->info(sprintf('%s: %d', $outcome, $counts[$outcome] ?? 0));
        }

        return $hadError ? self::FAILURE : self::SUCCESS;
    }
}
