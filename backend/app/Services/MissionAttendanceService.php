<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Admin;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MissionAttendanceService
{
    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
        private readonly MissionService $missionService,
    ) {}

    /**
     * Apply a Producer batch of presence/absence decisions on a mission.
     * Present entries are released to Face wallets immediately. Absent entries
     * stay Locked, awaiting the 72h dispute window (FIX-26.5) or auto-settle cron (FIX-26.6).
     *
     * @param  array<array-key, 'present'|'absent'>  $entryIdToStatus
     */
    public function markAttendance(Mission $mission, array $entryIdToStatus, User $actor): Mission
    {
        if (! $this->isProducerMissionOwner($actor, $mission)) {
            throw ValidationException::withMessages([
                'actor' => ['Seul le producteur de la mission peut valider les présences.'],
            ]);
        }

        if (! in_array($mission->status, [MissionStatus::Closed, MissionStatus::PendingAttendanceValidation, MissionStatus::Completed], true)) {
            throw ValidationException::withMessages([
                'mission' => ['La validation des présences n\'est possible que sur une mission clôturée ou en attente de validation.'],
            ]);
        }

        if ($entryIdToStatus === []) {
            throw ValidationException::withMessages([
                'entries' => ['Au moins une entry doit être fournie.'],
            ]);
        }

        foreach ($entryIdToStatus as $entryId => $status) {
            if (! is_int($entryId) || $entryId <= 0) {
                throw ValidationException::withMessages([
                    'entries' => ['Les identifiants des entries doivent être des entiers positifs.'],
                ]);
            }

            if (! in_array($status, ['present', 'absent'], true)) {
                throw ValidationException::withMessages([
                    'entries' => ['Les statuts acceptés sont : present, absent.'],
                ]);
            }
        }

        ksort($entryIdToStatus, SORT_NUMERIC);

        $mission->loadMissing('payment');
        if (
            ! $mission->payment instanceof MissionPayment
            || $mission->payment->status !== MissionPaymentStatus::Paid
        ) {
            throw ValidationException::withMessages([
                'mission' => ['La validation des présences requiert un paiement confirmé sur la mission.'],
            ]);
        }

        return DB::transaction(function () use ($mission, $entryIdToStatus, $actor): Mission {
            /** @var Mission $lockedMission */
            $lockedMission = Mission::lockForUpdate()->findOrFail($mission->id);
            $lockedMission->loadMissing('payment');

            if (! $this->isProducerMissionOwner($actor, $lockedMission)) {
                throw ValidationException::withMessages([
                    'actor' => ['Seul le producteur de la mission peut valider les présences.'],
                ]);
            }

            if (! in_array($lockedMission->status, [MissionStatus::Closed, MissionStatus::PendingAttendanceValidation, MissionStatus::Completed], true)) {
                throw ValidationException::withMessages([
                    'mission' => ['La validation des présences n\'est possible que sur une mission clôturée ou en attente de validation.'],
                ]);
            }

            if (
                ! $lockedMission->payment instanceof MissionPayment
                || $lockedMission->payment->status !== MissionPaymentStatus::Paid
            ) {
                throw ValidationException::withMessages([
                    'mission' => ['La validation des présences requiert un paiement confirmé sur la mission.'],
                ]);
            }

            $entriesToRelease = [];

            foreach ($entryIdToStatus as $entryId => $status) {
                /** @var MissionPaymentCandidature|null $entry */
                $entry = MissionPaymentCandidature::lockForUpdate()->find((int) $entryId);

                if (! $entry) {
                    throw new \RuntimeException("MissionAttendanceService::markAttendance — entry {$entryId} not found.");
                }

                $entry->loadMissing('missionPayment');

                if (! $entry->missionPayment || $entry->missionPayment->mission_id !== $lockedMission->id) {
                    throw new \RuntimeException(
                        "Entry {$entry->id} does not belong to mission {$lockedMission->id}."
                    );
                }

                if (
                    $entry->escrow_status !== EscrowStatus::Locked
                    || $entry->attendance_status !== AttendanceStatus::Pending
                ) {
                    continue;
                }

                $targetStatus = AttendanceStatus::from($status);
                $updatePayload = ['attendance_status' => $targetStatus];

                if ($targetStatus === AttendanceStatus::Absent && $entry->notified_at === null) {
                    $updatePayload['notified_at'] = now();
                }

                $entry->update($updatePayload);

                if ($targetStatus === AttendanceStatus::Present) {
                    $entriesToRelease[] = $entry->refresh();
                }
            }

            if ($lockedMission->status === MissionStatus::Closed) {
                $lockedMission->update(['status' => MissionStatus::PendingAttendanceValidation]);
            }

            foreach ($entriesToRelease as $entry) {
                $this->missionPaymentService->releaseToFace($entry, $lockedMission, 'attendance_present');
            }

            $this->tryCompleteIfReady($lockedMission->refresh());

            /** @var Mission $freshMission */
            $freshMission = $lockedMission->fresh();

            return $freshMission;
        });
    }

    public function disputeAttendance(MissionPaymentCandidature $entry, User $actor): MissionPaymentCandidature
    {
        if (! $this->isFaceEntryOwner($actor, $entry)) {
            throw ValidationException::withMessages([
                'actor' => ['Seule la Face concernée peut contester sa propre absence.'],
            ]);
        }

        return DB::transaction(function () use ($entry, $actor): MissionPaymentCandidature {
            /** @var MissionPaymentCandidature $lockedEntry */
            $lockedEntry = MissionPaymentCandidature::lockForUpdate()->findOrFail($entry->id);

            if (! $this->isFaceEntryOwner($actor, $lockedEntry)) {
                throw ValidationException::withMessages([
                    'actor' => ['Seule la Face concernée peut contester sa propre absence.'],
                ]);
            }

            if (
                $lockedEntry->escrow_status !== EscrowStatus::Locked
                || $lockedEntry->attendance_status !== AttendanceStatus::Absent
            ) {
                throw ValidationException::withMessages([
                    'entry' => ['La contestation n\'est possible que sur une absence non encore tranchée.'],
                ]);
            }

            $lockedEntry->update(['attendance_status' => AttendanceStatus::Disputed]);
            $lockedEntry->loadMissing('missionPayment');

            Log::info('MissionAttendanceService::disputeAttendance — entry disputed by Face', [
                'entry_id' => $lockedEntry->id,
                'face_id' => $lockedEntry->face_id,
                'mission_id' => $lockedEntry->missionPayment?->mission_id,
            ]);

            /** @var MissionPaymentCandidature $freshEntry */
            $freshEntry = $lockedEntry->fresh();

            return $freshEntry;
        });
    }

    public function resolveDispute(
        MissionPaymentCandidature $entry,
        DisputeResolutionOutcome $outcome,
        User $admin,
    ): MissionPaymentCandidature {
        if ($admin->userable_type !== Admin::class) {
            throw ValidationException::withMessages([
                'admin' => ['Seul un administrateur peut résoudre un litige.'],
            ]);
        }

        return DB::transaction(function () use ($entry, $outcome): MissionPaymentCandidature {
            /** @var MissionPaymentCandidature $lockedEntry */
            $lockedEntry = MissionPaymentCandidature::lockForUpdate()->findOrFail($entry->id);

            if (
                $lockedEntry->escrow_status !== EscrowStatus::Locked
                || $lockedEntry->attendance_status !== AttendanceStatus::Disputed
            ) {
                throw ValidationException::withMessages([
                    'entry' => ['La résolution de litige n\'est possible que sur une entry contestée non encore tranchée.'],
                ]);
            }

            $lockedEntry->loadMissing('missionPayment.mission');
            /** @var Mission|null $mission */
            $mission = $lockedEntry->missionPayment?->mission;

            if (! $mission) {
                throw new \RuntimeException("MissionAttendanceService::resolveDispute — mission not found for entry {$lockedEntry->id}.");
            }

            match ($outcome) {
                DisputeResolutionOutcome::FavorFace => $this->missionPaymentService->releaseToFace(
                    $lockedEntry,
                    $mission,
                    'disputed_resolved_face',
                ),
                DisputeResolutionOutcome::FavorProducer => $this->missionPaymentService->refundToProducer(
                    $lockedEntry,
                    $mission,
                    'disputed_resolved_producer',
                ),
            };

            $this->tryCompleteIfReady($mission->refresh());

            /** @var MissionPaymentCandidature $freshEntry */
            $freshEntry = $lockedEntry->fresh();

            return $freshEntry;
        });
    }

    private function tryCompleteIfReady(Mission $mission): void
    {
        if ($mission->status === MissionStatus::Completed) {
            return;
        }

        $mission->loadMissing('payment');

        if (
            ! $mission->payment instanceof MissionPayment
            || $mission->payment->status !== MissionPaymentStatus::Paid
        ) {
            return;
        }

        if (! $mission->payment->entries()->exists()) {
            return;
        }

        $hasUnsettled = $mission->payment->entries()
            ->where('escrow_status', EscrowStatus::Locked)
            ->whereIn('attendance_status', [AttendanceStatus::Pending, AttendanceStatus::Absent])
            ->exists();

        if ($hasUnsettled) {
            return;
        }

        $mission->update(['status' => MissionStatus::Completed]);
        /** @var Mission $freshMission */
        $freshMission = $mission->fresh();
        $this->missionService->notifyProducerOnCompletion($freshMission);
    }

    private function isProducerMissionOwner(User $actor, Mission $mission): bool
    {
        return $actor->userable_type === Producer::class
            && $actor->userable_id === $mission->producer_id;
    }

    private function isFaceEntryOwner(User $actor, MissionPaymentCandidature $entry): bool
    {
        return $actor->userable_type === Face::class
            && $actor->userable_id === $entry->face_id;
    }
}
