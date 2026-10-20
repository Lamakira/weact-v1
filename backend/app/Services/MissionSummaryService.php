<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\DeliverableValidationStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use Illuminate\Support\Facades\DB;

/**
 * Lot C1 — résumé du cycle de vie d'une mission pour la page détail Producteur
 * (`GET /producer/missions/{mission}` uniquement, jamais sur les listes) : tout ce
 * qu'il faut pour une barre d'étapes en UN appel, sans appel FedaPay.
 *
 * Mapping `step` :
 *  Mission standard
 *   - draft          : brouillon (hors barre d'étapes, valeur ajoutée pour ne pas inventer d'étape)
 *   - recruiting     : publiée, ou clôturée à la main sans paiement (réouvrable)
 *   - payment        : pending_payment (sélection à régler)
 *   - confirmations  : payée, au moins une Face sélectionnée est encore `accepted`
 *   - ready          : payée, toutes confirmées, avant le jour de tournage
 *   - attendance     : jour de tournage atteint, ou pending_attendance_validation
 *   - completed      : terminée
 *  Mission UGC
 *   - draft          : brouillon
 *   - commission     : pending_payment (commission à régler)
 *   - recruiting     : publiée, recrutement encore ouvert ou aucune Face engagée
 *   - tunnel         : clôturée, ou recrutement fini avec des Faces engagées dans le tunnel
 *   - completed      : terminée
 */
class MissionSummaryService
{
    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(Mission $mission, int $freedPlacesCount): array
    {
        $isUgc = $mission->type_mission === MissionType::Ugc;

        $candidatureCounts = $this->candidatureCounts($mission);

        /** @var MissionPayment|null $payment */
        $payment = $mission->payment;
        $isPaid = $payment !== null && $payment->status === MissionPaymentStatus::Paid;

        $entries = $payment !== null ? $this->entryRows($payment) : [];

        $selected = array_filter($entries, fn (object $e): bool => $e->escrow_status !== EscrowStatus::Transferred->value);
        $engagedStatuses = [
            CandidatureStatus::Confirmed->value,
            CandidatureStatus::InProgress->value,
            CandidatureStatus::Completed->value,
        ];
        $confirmed = array_filter($selected, fn (object $e): bool => in_array($e->candidature_status, $engagedStatuses, true));
        $stillAccepted = array_filter($selected, fn (object $e): bool => $e->candidature_status === CandidatureStatus::Accepted->value);

        $blocker = $mission->completionBlocker();

        return [
            'step' => $this->step($mission, $isUgc, $isPaid, count($stillAccepted) > 0, $candidatureCounts),
            'candidatures' => $candidatureCounts,
            'selection' => [
                'faces_wanted' => (int) $mission->nombre_faces_voulu,
                'faces_selected' => count($selected),
                'faces_confirmed' => count($confirmed),
                'freed_places' => $freedPlacesCount,
            ],
            'payment' => [
                'status' => $payment?->status->value,
                'expires_at' => $payment !== null && $payment->status === MissionPaymentStatus::Pending
                    ? $this->missionPaymentService->selectionExpiresAt($payment)->toIso8601String()
                    : null,
                'paid_at' => $payment?->paid_at?->toIso8601String(),
                'montant_total_producteur' => $payment !== null ? (int) $payment->montant_total_producteur : null,
            ],
            'attendance' => $isPaid ? $this->attendance($mission, $entries) : null,
            'ugc_deliverables' => $isUgc ? $this->ugcDeliverables($mission) : null,
            'completion_blocker' => $blocker[1] ?? null,
            // Lot C8 : Faces terminées que le Producteur n'a pas (encore) notées — notation facultative.
            'faces_to_rate' => ($candidatureCounts[CandidatureStatus::Completed->value] ?? 0) > 0
                ? $this->facesToRate($mission)
                : 0,
            'can_edit' => ! $isUgc
                && in_array($mission->status, [MissionStatus::Draft, MissionStatus::Published], true),
        ];
    }

    /**
     * Candidatures `completed` sans note du Producteur propriétaire (1 requête). Les notes de la
     * Face sur le Producteur (même candidature) ne comptent pas : on filtre sur l'auteur.
     */
    private function facesToRate(Mission $mission): int
    {
        return DB::table('candidatures as c')
            ->where('c.mission_id', $mission->id)
            ->where('c.status', CandidatureStatus::Completed->value)
            ->whereNotExists(function ($query) use ($mission): void {
                $query->select(DB::raw(1))
                    ->from('ratings as r')
                    ->join('users as u', 'u.id', '=', 'r.rater_id')
                    ->whereColumn('r.candidature_id', 'c.id')
                    ->where('u.userable_type', (new Producer)->getMorphClass())
                    ->where('u.userable_id', $mission->producer_id);
            })
            ->count();
    }

    /**
     * @return array<string, int>
     */
    private function candidatureCounts(Mission $mission): array
    {
        $rows = DB::table('candidatures')
            ->where('mission_id', $mission->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];
        foreach (CandidatureStatus::cases() as $status) {
            $counts[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        return $counts;
    }

    /**
     * Entries du paiement avec le statut de leur candidature (1 requête).
     *
     * @return list<object{escrow_status: string, attendance_status: string, candidature_status: string}>
     */
    private function entryRows(MissionPayment $payment): array
    {
        return DB::table('mission_payment_candidatures as e')
            ->join('candidatures as c', 'c.id', '=', 'e.candidature_id')
            ->where('e.mission_payment_id', $payment->id)
            ->select(['e.escrow_status', 'e.attendance_status', 'c.status as candidature_status'])
            ->get()
            ->all();
    }

    /**
     * @param  list<object{escrow_status: string, attendance_status: string, candidature_status: string}>  $entries
     * @return array<string, mixed>
     */
    private function attendance(Mission $mission, array $entries): array
    {
        $counted = array_filter($entries, fn (object $e): bool => in_array($e->escrow_status, [
            EscrowStatus::Locked->value,
            EscrowStatus::Released->value,
            EscrowStatus::Refunded->value,
        ], true) && $e->candidature_status !== CandidatureStatus::Expired->value);

        $count = fn (AttendanceStatus $status): int => count(array_filter(
            $counted,
            fn (object $e): bool => $e->attendance_status === $status->value,
        ));

        return [
            'opens_at' => $mission->shootingDayStartsAt()?->toIso8601String(),
            // Même règle que le formulaire de présences (MissionAttendanceController::show).
            'can_mark' => in_array($mission->status, [MissionStatus::Closed, MissionStatus::PendingAttendanceValidation], true)
                && $mission->isAttendanceOpen(),
            'pending' => $count(AttendanceStatus::Pending),
            'present' => $count(AttendanceStatus::Present),
            'absent' => $count(AttendanceStatus::Absent),
            'disputed' => $count(AttendanceStatus::Disputed),
        ];
    }

    /**
     * @return array{to_review: int, validated: int, retouche_requested: int}
     */
    private function ugcDeliverables(Mission $mission): array
    {
        $rows = Deliverable::query()
            ->where('owner_type', (new Candidature)->getMorphClass())
            ->whereIn('owner_id', DB::table('candidatures')->where('mission_id', $mission->id)->select('id'))
            ->toBase()
            ->selectRaw('validation_status, COUNT(*) as total')
            ->groupBy('validation_status')
            ->pluck('total', 'validation_status');

        return [
            'to_review' => (int) ($rows[DeliverableValidationStatus::InReview->value] ?? 0),
            'validated' => (int) ($rows[DeliverableValidationStatus::Validated->value] ?? 0),
            'retouche_requested' => (int) ($rows[DeliverableValidationStatus::RetoucheRequested->value] ?? 0),
        ];
    }

    /**
     * @param  array<string, int>  $candidatureCounts
     */
    private function step(Mission $mission, bool $isUgc, bool $isPaid, bool $hasAcceptedSelected, array $candidatureCounts): string
    {
        $status = $mission->status;

        if ($status === MissionStatus::Draft) {
            return 'draft';
        }

        if ($status === MissionStatus::Completed) {
            return 'completed';
        }

        if ($isUgc) {
            if ($status === MissionStatus::PendingPayment) {
                return 'commission';
            }

            $engaged = $candidatureCounts[CandidatureStatus::Accepted->value]
                + $candidatureCounts[CandidatureStatus::Confirmed->value]
                + $candidatureCounts[CandidatureStatus::InProgress->value];

            if ($status === MissionStatus::Closed
                || ($engaged > 0 && ! $mission->isAcceptingCandidatures())) {
                return 'tunnel';
            }

            return 'recruiting';
        }

        if ($status === MissionStatus::PendingPayment) {
            return 'payment';
        }

        if ($status === MissionStatus::PendingAttendanceValidation) {
            return 'attendance';
        }

        if ($status === MissionStatus::Published || ! $isPaid) {
            return 'recruiting';
        }

        // Closed + payée.
        if ($mission->date_tournage !== null && $mission->isAttendanceOpen()) {
            return 'attendance';
        }

        return $hasAcceptedSelected ? 'confirmations' : 'ready';
    }
}
