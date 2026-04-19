<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Support\StaleSelectionReconcile\DryRunCompleted;
use App\Support\StaleSelectionReconcile\SkipPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReconcileStaleSelectionsCommand extends Command
{
    protected $signature = 'candidature:reconcile-stale-selections
        {--dry-run : Compute and print the impact summary without writing anything}
        {--apply : Execute the reconciliation (mutually exclusive with --dry-run)}
        {--notifications-per-second=20 : Cap on notification writes per second after a successful apply}';

    protected $description = 'Reconcile stale candidature selections left over by mission payments that never confirmed via FedaPay (FIX-20.6).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Specify exactly one of --dry-run or --apply.');

            return self::FAILURE;
        }

        $rate = max(1, (int) $this->option('notifications-per-second'));
        $mode = $apply ? 'apply' : 'dry-run';

        $paymentIds = $this->discoverInScopePaymentIds();

        if ($paymentIds === []) {
            $this->info('No stale selections to reconcile — audit returned zero in-scope payments.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Reconciling %d in-scope payment(s) in %s mode.', count($paymentIds), $mode));

        $totals = [
            'payments_processed' => 0,
            'payments_skipped' => 0,
            'accepted_reset' => 0,
            'rejected_reset' => 0,
            'entries_deleted' => 0,
            'missions_reset' => 0,
            'notifications_queued' => 0,
            'notifications_failed' => 0,
        ];

        foreach ($paymentIds as $paymentId) {
            $outcome = $this->processPayment($paymentId, $apply);

            if ($outcome === null) {
                $totals['payments_skipped']++;

                continue;
            }

            $totals['payments_processed']++;
            $totals['accepted_reset'] += $outcome['accepted_reset_count'];
            $totals['rejected_reset'] += $outcome['rejected_reset_count'];
            $totals['entries_deleted'] += $outcome['entries_deleted'];

            if ($outcome['mission_reset']) {
                $totals['missions_reset']++;
            }

            $this->renderPaymentSummary($outcome, $mode);

            if ($apply) {
                // Notifications are queued AFTER the per-payment transaction commits
                // (notifySafely + naïve usleep pacing). A notification failure logs a
                // warning and never aborts the data fix or the rest of the run.
                $sent = $this->dispatchNotifications($outcome['notifications'], $rate);
                $totals['notifications_queued'] += $sent['queued'];
                $totals['notifications_failed'] += $sent['failed'];
            }
        }

        $this->info(sprintf(
            'Done. payments_processed=%d, accepted_reset=%d, rejected_reset=%d, entries_deleted=%d, missions_reset=%d, notifications_queued=%d, notifications_failed=%d, payments_skipped=%d',
            $totals['payments_processed'],
            $totals['accepted_reset'],
            $totals['rejected_reset'],
            $totals['entries_deleted'],
            $totals['missions_reset'],
            $totals['notifications_queued'],
            $totals['notifications_failed'],
            $totals['payments_skipped'],
        ));

        return self::SUCCESS;
    }

    /**
     * Discover in-scope payment IDs via the audit queries (Requête 1 + Requête 2 from the epic).
     *
     * Idempotence note: once an --apply run flips the candidature statuses back to `pending`,
     * neither query matches anything for those payments, so a re-run is naturally a no-op.
     *
     * @return list<int>
     */
    private function discoverInScopePaymentIds(): array
    {
        // Requête 1 — payment IDs reached via accepted candidatures whose payment is not paid
        // and has no FedaPay transaction id. The fedapay_transaction_id IS NULL guard mirrors
        // the production audit predicate (the 4 orphan payments share that signature).
        $acceptedPaymentIds = DB::table('candidatures as c')
            ->join('mission_payment_candidatures as mpc', 'mpc.candidature_id', '=', 'c.id')
            ->join('mission_payments as mp', 'mp.id', '=', 'mpc.mission_payment_id')
            ->where('c.status', CandidatureStatus::Accepted->value)
            ->where('mp.status', '<>', MissionPaymentStatus::Paid->value)
            ->whereNull('mp.fedapay_transaction_id')
            ->distinct()
            ->pluck('mp.id');

        // Requête 2 — payment IDs reached via rejected candidatures issued by a mass-reject
        // on a mission whose payment never confirmed. NOT EXISTS is broader than the epic's
        // Requête 2 (unscoped to mp.id) as defence-in-depth: if the `mission_payments.mission_id`
        // UNIQUE constraint is ever relaxed, a rejected candidature whose mpc row points to a
        // paid retry on the same mission would otherwise match the original scoped predicate.
        // Today the two filters are logically equivalent, but the broader form makes the
        // command robust to future schema evolution.
        $rejectedPaymentIds = DB::table('candidatures as c')
            ->join('missions as m', 'm.id', '=', 'c.mission_id')
            ->join('mission_payments as mp', 'mp.mission_id', '=', 'm.id')
            ->where('c.status', CandidatureStatus::Rejected->value)
            ->where('mp.status', '<>', MissionPaymentStatus::Paid->value)
            ->whereNull('mp.fedapay_transaction_id')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('mission_payment_candidatures as mpc')
                    ->whereColumn('mpc.candidature_id', 'c.id');
            })
            ->distinct()
            ->pluck('mp.id');

        $merged = $acceptedPaymentIds
            ->merge($rejectedPaymentIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        sort($merged);

        return $merged;
    }

    /**
     * Process a single payment in its own transaction.
     *
     * @return array{
     *     payment_id: int,
     *     mission_id: int,
     *     mission_titre: string,
     *     mission_uuid: string,
     *     producer_id: int,
     *     accepted_reset_count: int,
     *     rejected_reset_count: int,
     *     entries_deleted: int,
     *     mission_reset: bool,
     *     mission_reset_skipped_reason: ?string,
     *     notifications: list<array{userId: ?int, type: string, data: array<string, mixed>}>,
     *     mode: string,
     * }|null
     */
    private function processPayment(int $paymentId, bool $apply): ?array
    {
        $marker = new DryRunCompleted;

        try {
            return DB::transaction(function () use ($paymentId, $apply, $marker): array {
                /** @var MissionPayment|null $payment */
                $payment = MissionPayment::query()->lockForUpdate()->find($paymentId);

                if ($payment === null) {
                    throw new RuntimeException("Payment {$paymentId} disappeared between discovery and processing.");
                }

                if (
                    $payment->fedapay_transaction_id !== null
                    || $payment->status === MissionPaymentStatus::Paid
                ) {
                    Log::info('Stale selection reconcile: payment moved out of scope between discovery and lock', [
                        'payment_id' => $payment->id,
                        'mission_id' => $payment->mission_id,
                        'fedapay_transaction_id' => $payment->fedapay_transaction_id,
                        'status' => $payment->status->value,
                    ]);

                    throw new SkipPayment;
                }

                /** @var Mission|null $mission */
                $mission = Mission::query()->lockForUpdate()->find($payment->mission_id);

                if ($mission === null) {
                    throw new RuntimeException("Mission {$payment->mission_id} for payment {$payment->id} not found.");
                }

                $missionTitre = (string) $mission->titre;
                $missionUuid = (string) $mission->uuid;

                $acceptedIds = Candidature::query()
                    ->where('mission_id', $payment->mission_id)
                    ->where('status', CandidatureStatus::Accepted->value)
                    ->whereIn('id', function ($query) use ($payment): void {
                        $query->select('candidature_id')
                            ->from('mission_payment_candidatures')
                            ->where('mission_payment_id', $payment->id);
                    })
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                // Matches the broadened NOT EXISTS in discoverInScopePaymentIds (defence-in-depth;
                // equivalent to the scoped form today because `mission_payments.mission_id` is
                // UNIQUE, but robust if that constraint is ever relaxed).
                $rejectedIds = Candidature::query()
                    ->where('mission_id', $payment->mission_id)
                    ->where('status', CandidatureStatus::Rejected->value)
                    ->whereNotExists(function ($query): void {
                        $query->select(DB::raw(1))
                            ->from('mission_payment_candidatures as mpc')
                            ->whereColumn('mpc.candidature_id', 'candidatures.id');
                    })
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                $faceIdsToNotify = Candidature::query()
                    ->whereIn('id', array_merge($acceptedIds, $rejectedIds))
                    ->get(['id', 'face_id', 'status']);

                $acceptedFaceIds = $faceIdsToNotify->where('status', CandidatureStatus::Accepted)->pluck('face_id')->all();
                $rejectedFaceIds = $faceIdsToNotify->where('status', CandidatureStatus::Rejected)->pluck('face_id')->all();

                $acceptedResetCount = count($acceptedIds);
                $rejectedResetCount = count($rejectedIds);
                $entriesDeleted = 0;
                $missionReset = false;
                $missionResetSkippedReason = null;

                if ($acceptedResetCount > 0) {
                    Candidature::query()
                        ->whereIn('id', $acceptedIds)
                        ->update(['status' => CandidatureStatus::Pending->value]);
                }

                if ($rejectedResetCount > 0) {
                    Candidature::query()
                        ->whereIn('id', $rejectedIds)
                        ->update(['status' => CandidatureStatus::Pending->value]);
                }

                // Requête 2's NOT EXISTS guarantees the rejected scope has no mpc rows;
                // the only entries on this payment correspond to the accepted candidatures.
                // We delete by mission_payment_id rather than by candidature_id so a future
                // shape change (e.g. partial mpc backfill) does not silently leave orphans.
                $entriesDeleted = MissionPaymentCandidature::query()
                    ->where('mission_payment_id', $payment->id)
                    ->delete();

                $payment->update(['status' => MissionPaymentStatus::Failed->value]);

                if ($mission->status === MissionStatus::PendingPayment) {
                    $mission->update(['status' => MissionStatus::Published->value]);
                    $missionReset = true;
                } else {
                    $missionResetSkippedReason = 'already_in_expected_state';
                    Log::info('Stale selection reconcile: mission_status_reset_skipped', [
                        'payment_id' => $payment->id,
                        'mission_id' => $mission->id,
                        'mission_status' => $mission->status->value,
                        'reason' => $missionResetSkippedReason,
                    ]);
                }

                $notifications = $this->buildNotificationPayloads(
                    missionId: $mission->id,
                    missionTitre: $missionTitre,
                    missionUuid: $missionUuid,
                    producerId: $payment->producer_id,
                    acceptedFaceIds: $acceptedFaceIds,
                    rejectedFaceIds: $rejectedFaceIds,
                );

                Log::info(
                    $apply
                        ? 'Stale selection reconcile: payment processed'
                        : 'Stale selection reconcile: payment previewed',
                    [
                        'payment_id' => $payment->id,
                        'mission_id' => $mission->id,
                        'producer_id' => $payment->producer_id,
                        'accepted_reset_count' => $acceptedResetCount,
                        'rejected_reset_count' => $rejectedResetCount,
                        'entries_deleted' => $entriesDeleted,
                        'mission_reset' => $missionReset,
                        'mission_reset_skipped_reason' => $missionResetSkippedReason,
                        'notifications_planned' => count($notifications),
                        'mode' => $apply ? 'apply' : 'dry-run',
                    ]
                );

                $outcome = [
                    'payment_id' => $payment->id,
                    'mission_id' => $mission->id,
                    'mission_titre' => $missionTitre,
                    'mission_uuid' => $missionUuid,
                    'producer_id' => $payment->producer_id,
                    'accepted_reset_count' => $acceptedResetCount,
                    'rejected_reset_count' => $rejectedResetCount,
                    'entries_deleted' => $entriesDeleted,
                    'mission_reset' => $missionReset,
                    'mission_reset_skipped_reason' => $missionResetSkippedReason,
                    'notifications' => $notifications,
                    'mode' => $apply ? 'apply' : 'dry-run',
                ];

                if (! $apply) {
                    // Marker exception: rolls back the whole transaction so dry-run
                    // is bit-for-bit non-mutating. The marker carries the outcome
                    // payload so the outer caller can still print the summary.
                    $marker->outcome = $outcome;
                    throw $marker;
                }

                return $outcome;
            });
        } catch (DryRunCompleted $completed) {
            return $completed->outcome;
        } catch (SkipPayment) {
            return null;
        }
    }

    /**
     * @param  list<int>  $acceptedFaceIds
     * @param  list<int>  $rejectedFaceIds
     * @return list<array{userId: ?int, type: string, data: array<string, mixed>}>
     */
    private function buildNotificationPayloads(
        int $missionId,
        string $missionTitre,
        string $missionUuid,
        int $producerId,
        array $acceptedFaceIds,
        array $rejectedFaceIds,
    ): array {
        $payloads = [];

        foreach ($acceptedFaceIds as $faceId) {
            $payloads[] = [
                'userId' => $this->resolveFaceUserId((int) $faceId),
                'type' => 'candidature_reset_from_accepted',
                'data' => [
                    'message' => 'Le paiement du producteur pour la mission « '.$missionTitre.' » n\'a pas été finalisé. Votre candidature a été remise en attente et pourra à nouveau être sélectionnée si le producteur relance la sélection.',
                    'mission_id' => $missionId,
                    'mission_titre' => $missionTitre,
                    'url' => '/face/candidatures',
                ],
            ];
        }

        foreach ($rejectedFaceIds as $faceId) {
            $payloads[] = [
                'userId' => $this->resolveFaceUserId((int) $faceId),
                'type' => 'candidature_reset_from_rejected',
                'data' => [
                    'message' => 'La sélection précédente pour la mission « '.$missionTitre.' » n\'a pas été finalisée. Votre candidature est de nouveau en attente et peut être retenue si le producteur relance la sélection.',
                    'mission_id' => $missionId,
                    'mission_titre' => $missionTitre,
                    'url' => '/face/candidatures',
                ],
            ];
        }

        $payloads[] = [
            'userId' => $this->resolveProducerUserId($producerId),
            'type' => 'mission_selection_reset_producer',
            'data' => [
                'message' => 'Le paiement de votre sélection pour la mission « '.$missionTitre.' » n\'a pas abouti. La mission est de nouveau ouverte aux candidatures — vous pouvez relancer votre sélection depuis la page de la mission.',
                'mission_id' => $missionId,
                'mission_titre' => $missionTitre,
                'url' => '/producer/missions/'.$missionUuid.'/candidatures',
            ],
        ];

        return $payloads;
    }

    /**
     * @param  list<array{userId: ?int, type: string, data: array<string, mixed>}>  $notifications
     * @return array{queued: int, failed: int}
     */
    private function dispatchNotifications(array $notifications, int $rate): array
    {
        $queued = 0;
        $failed = 0;
        $sleepMicros = (int) (1_000_000 / max(1, $rate));

        foreach ($notifications as $notification) {
            if ($notification['userId'] === null) {
                Log::warning('Stale selection reconcile: notification skipped — user not found', [
                    'type' => $notification['type'],
                    'data' => $notification['data'],
                ]);
                $failed++;

                continue;
            }

            try {
                Notification::create([
                    'user_id' => $notification['userId'],
                    'type' => $notification['type'],
                    'data' => $notification['data'],
                ]);
                $queued++;
            } catch (\Throwable $e) {
                Log::warning('Stale selection reconcile: notification create failed', [
                    'user_id' => $notification['userId'],
                    'type' => $notification['type'],
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }

            if ($sleepMicros > 0) {
                usleep($sleepMicros);
            }
        }

        return ['queued' => $queued, 'failed' => $failed];
    }

    private function resolveFaceUserId(int $faceId): ?int
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('userable_type', Face::class)
            ->where('userable_id', $faceId)
            ->first();

        return $user?->id;
    }

    private function resolveProducerUserId(int $producerId): ?int
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('userable_type', Producer::class)
            ->where('userable_id', $producerId)
            ->first();

        return $user?->id;
    }

    /**
     * @param  array{payment_id:int, mission_id:int, accepted_reset_count:int, rejected_reset_count:int, entries_deleted:int, notifications:list<mixed>, mission_reset:bool, mission_reset_skipped_reason:?string}  $outcome
     */
    private function renderPaymentSummary(array $outcome, string $mode): void
    {
        $missionResetLabel = $outcome['mission_reset']
            ? 'reset_to_published'
            : ('skipped:'.($outcome['mission_reset_skipped_reason'] ?? 'unknown'));

        // The count below is the number of notifications this payment WOULD queue; the
        // actual queued count is aggregated post-dispatch in the final summary line
        // (`notifications_queued=…`). Keeping the two names distinct avoids confusion
        // between "planned" (printed per-payment) and "queued" (reported at the end).
        $this->line(sprintf(
            '[%s] payment_id=%d mission_id=%d accepted_reset=%d rejected_reset=%d entries_deleted=%d mission=%s notifications_planned=%d',
            $mode,
            $outcome['payment_id'],
            $outcome['mission_id'],
            $outcome['accepted_reset_count'],
            $outcome['rejected_reset_count'],
            $outcome['entries_deleted'],
            $missionResetLabel,
            count($outcome['notifications']),
        ));
    }
}
