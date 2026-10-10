<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Enums\EscrowStatus;
use App\Enums\MissionType;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Face;
use App\Models\User;
use App\ValueObjects\BookingPricing;
use App\ValueObjects\MissionPricing;

/**
 * Construit l'objet `context` d'une conversation (mission standard ou UGC), à partir
 * de la candidature à laquelle elle est liée.
 *
 * Une conversation n'est liée qu'à une candidature (Conversation::candidature) ; le
 * chat d'un booking vit dans le détail du booking, hors de ce contexte. Le type est
 * donc `mission` ou `ugc` (Mission::type_mission).
 *
 * Étapes : Demandé -> Accepté -> Payé -> Réalisé, dérivées du statut de candidature :
 * - pending => Demandé ;
 * - accepted => Accepté, ou Payé si l'escrow de la candidature est verrouillé/libéré
 *   (missions standard : le paiement Producteur précède l'acceptation ; UGC hybride :
 *   escrow à l'acceptation) ;
 * - confirmed / in_progress => Payé (engagement confirmé et financé) ;
 * - completed => Réalisé ;
 * - rejected / cancelled => clos, aucune étape courante.
 *
 * Montants selon le rôle (aucune fuite croisée) :
 * - Face : uniquement ce qu'elle reçoit (net d'escrow) + valeur du produit UGC ;
 * - Producteur : cachet, frais de service, total + valeur du produit UGC.
 *
 * Attend `candidature.mission`, `candidature.paymentEntry.missionPayment` chargés
 * (aucune requête supplémentaire en liste).
 */
class ConversationContextBuilder
{
    /** @var array<string, string> */
    private const STEP_LABELS = [
        'requested' => 'Demandé',
        'accepted' => 'Accepté',
        'paid' => 'Payé',
        'done' => 'Réalisé',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function build(Conversation $conversation, User $viewer, bool $detailed): ?array
    {
        $candidature = $conversation->candidature;
        $mission = $candidature?->mission;

        if ($candidature === null || $mission === null) {
            return null;
        }

        $isUgc = $mission->type_mission === MissionType::Ugc;
        $stepKey = $this->stepKey($candidature);

        $context = [
            'type' => $isUgc ? 'ugc' : 'mission',
            'type_label' => $isUgc ? 'UGC' : 'Mission',
            'title' => $mission->titre,
            'candidature_status' => $candidature->status->value,
            'step' => $stepKey === null ? null : ['key' => $stepKey, 'label' => self::STEP_LABELS[$stepKey]],
            'date_tournage' => $mission->date_tournage?->format('Y-m-d'),
        ];

        if (! $detailed) {
            return $context;
        }

        return $context + [
            'candidature_status_label' => $candidature->status->label(),
            'closed' => $stepKey === null,
            'steps' => $this->steps($stepKey),
            'lieu' => $mission->lieu,
            'mission_id' => $mission->uuid,
            'candidature_id' => $candidature->uuid,
            'amounts' => $viewer->userable_type === Face::class
                ? $this->faceAmounts($candidature)
                : $this->producerAmounts($candidature),
        ];
    }

    private function stepKey(Candidature $candidature): ?string
    {
        return match ($candidature->status) {
            CandidatureStatus::Pending => 'requested',
            CandidatureStatus::Accepted => $this->hasFundedEscrow($candidature) ? 'paid' : 'accepted',
            CandidatureStatus::Confirmed, CandidatureStatus::InProgress => 'paid',
            CandidatureStatus::Completed => 'done',
            CandidatureStatus::Rejected, CandidatureStatus::Cancelled => null,
        };
    }

    private function hasFundedEscrow(Candidature $candidature): bool
    {
        $entry = $candidature->paymentEntry;

        return $entry !== null
            && in_array($entry->escrow_status, [EscrowStatus::Locked, EscrowStatus::Released], true);
    }

    /**
     * @return list<array{key: string, label: string, state: string}>
     */
    private function steps(?string $currentKey): array
    {
        $keys = array_keys(self::STEP_LABELS);
        $currentIndex = $currentKey === null ? null : array_search($currentKey, $keys, true);

        $steps = [];
        foreach ($keys as $index => $key) {
            $state = match (true) {
                $currentIndex === false || $currentIndex === null => 'todo',
                $index < $currentIndex => 'done',
                $index === $currentIndex => 'current',
                default => 'todo',
            };
            $steps[] = ['key' => $key, 'label' => self::STEP_LABELS[$key], 'state' => $state];
        }

        return $steps;
    }

    /**
     * @return array{face_receives: int|null, product_value: int|null}
     */
    private function faceAmounts(Candidature $candidature): array
    {
        $entry = $this->hasFundedEscrow($candidature) ? $candidature->paymentEntry : null;

        return [
            'face_receives' => $entry !== null ? (int) $entry->montant_face_recoit : null,
            'product_value' => $this->productValue($candidature),
        ];
    }

    /**
     * @return array{cachet: int|null, service_fee: int|null, total: int|null, product_value: int|null}
     */
    private function producerAmounts(Candidature $candidature): array
    {
        $mission = $candidature->mission;
        $cachet = null;
        $fee = null;
        $total = null;

        if ($mission->type_mission === MissionType::Ugc) {
            if ($mission->type_compensation === CompensationType::Hybrid && $mission->montant_remuneration !== null) {
                $pricing = new BookingPricing((int) $mission->montant_remuneration, 0.0);
                $cachet = $pricing->baseTarif;
                $fee = $pricing->producerCommission;
                $total = $pricing->totalProducerPays;
            }
        } else {
            $payment = $this->hasFundedEscrow($candidature) ? $candidature->paymentEntry?->missionPayment : null;
            if ($payment !== null) {
                $pricing = new MissionPricing((int) $payment->budget_par_face, 1);
                $cachet = $pricing->budgetParFace;
                $fee = $pricing->commissionProducteur;
                $total = $pricing->montantTotalProducteur;
            }
        }

        return [
            'cachet' => $cachet,
            'service_fee' => $fee,
            'total' => $total,
            'product_value' => $this->productValue($candidature),
        ];
    }

    private function productValue(Candidature $candidature): ?int
    {
        $mission = $candidature->mission;

        return $mission->type_mission === MissionType::Ugc && $mission->valeur_produit !== null
            ? (int) $mission->valeur_produit
            : null;
    }
}
