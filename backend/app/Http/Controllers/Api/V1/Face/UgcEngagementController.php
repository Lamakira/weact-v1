<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\CandidatureStatus;
use App\Enums\ErrorCodes;
use App\Enums\MissionGender;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Events\UgcMissionDealAccepted;
use App\Http\Controllers\Controller;
use App\Http\Resources\CandidatureResource;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\Mission;
use App\Services\FaceEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UgcEngagementController extends Controller
{
    public function __construct(
        private readonly FaceEntitlementService $entitlement,
    ) {}

    /**
     * Acceptation directe d'une mission UGC (FR6 étape 2, D-2.4.a) :
     * la candidature de la Face atterrit `confirmed` (créée ou transitionnée
     * depuis `pending`), la capacité est garantie sous lock, la mission se
     * clôt à capacité atteinte. « Produit reçu » rejoindra ce contrôleur en 3.3.
     */
    public function accept(Request $request, Mission $mission): JsonResponse
    {
        $face = $request->user()->userable;

        if ($mission->type_mission !== MissionType::Ugc) {
            return response()->json(
                ErrorCodes::InvalidStatus->envelope("L'acceptation directe est réservée aux missions UGC."),
                422
            );
        }

        $candidature = Candidature::where('face_id', $face->id)
            ->where('mission_id', $mission->id)
            ->first();

        // Parité show/store : une mission non publiée n'existe pas pour une Face sans candidature.
        if ($mission->status !== MissionStatus::Published && ! $candidature) {
            abort(404);
        }

        // Précédence des états de candidature sur le paywall (parité décision review 2.1).
        if ($candidature && in_array($candidature->status, [
            CandidatureStatus::Confirmed, CandidatureStatus::InProgress, CandidatureStatus::Completed,
        ], true)) {
            return response()->json(
                ErrorCodes::AlreadyAccepted->envelope('Vous avez déjà accepté cette mission.'),
                422
            );
        }

        if ($candidature && in_array($candidature->status, [
            CandidatureStatus::Cancelled, CandidatureStatus::Rejected,
        ], true)) {
            return response()->json(
                ErrorCodes::InvalidStatus->envelope('Votre candidature pour cette mission a été retirée ou refusée.'),
                422
            );
        }

        if (! $mission->isAcceptingCandidatures()) {
            return response()->json([
                'error' => [
                    'code' => 'MISSION_CLOSED',
                    'message' => "Cette mission n'accepte plus de candidatures",
                ],
            ], 422);
        }

        // Gate FR5 : l'engagement exige l'éligibilité ACTUELLE — pas d'exception candidature ici (D-2.4.c).
        if (! $this->entitlement->canAccessUgc($face)) {
            return response()->json(
                ErrorCodes::UgcSubscriptionRequired->envelope(
                    "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus)."
                ),
                403
            );
        }

        // Genre : mêmes règles et messages que le apply (CandidatureController::store).
        if ($mission->genre_voulu !== MissionGender::Tous) {
            $faceSexe = $face->sexe;

            if ($faceSexe === null) {
                return response()->json([
                    'error' => [
                        'code' => 'gender_mismatch',
                        'message' => 'Veuillez compléter votre profil (genre) avant de postuler.',
                    ],
                ], 422);
            }

            if ($faceSexe->value !== $mission->genre_voulu->value) {
                return response()->json([
                    'error' => [
                        'code' => 'gender_mismatch',
                        'message' => "Cette mission recherche un profil {$mission->genre_voulu->label()}. Votre profil ne correspond pas au genre requis.",
                    ],
                ], 422);
            }
        }

        // Cœur transactionnel : capacité + écriture + auto-clôture, sérialisés par le lock mission (D-2.4.d).
        $result = DB::transaction(function () use ($mission, $face): array {
            /** @var Mission $lockedMission */
            $lockedMission = Mission::query()->lockForUpdate()->findOrFail($mission->id);

            if (! $lockedMission->isAcceptingCandidatures()) {
                return ['outcome' => 'closed'];
            }

            $engagedCount = Candidature::where('mission_id', $lockedMission->id)
                ->whereIn('status', [
                    CandidatureStatus::Confirmed->value,
                    CandidatureStatus::InProgress->value,
                    CandidatureStatus::Completed->value,
                ])
                ->count();

            if ($engagedCount >= $lockedMission->nombre_faces_voulu) {
                return ['outcome' => 'full'];
            }

            $candidature = Candidature::where('face_id', $face->id)
                ->where('mission_id', $lockedMission->id)
                ->lockForUpdate()
                ->first();

            // Re-check sous lock : une requête concurrente a pu déjà engager cette candidature.
            if ($candidature && $candidature->status !== CandidatureStatus::Pending) {
                return ['outcome' => 'already'];
            }

            if ($candidature) {
                $candidature->update(['status' => CandidatureStatus::Confirmed]);
            } else {
                $candidature = Candidature::create([
                    'face_id' => $face->id,
                    'mission_id' => $lockedMission->id,
                    'status' => CandidatureStatus::Confirmed,
                ]);
            }

            Conversation::firstOrCreate(['candidature_id' => $candidature->id]);

            if ($engagedCount + 1 >= $lockedMission->nombre_faces_voulu) {
                $lockedMission->update(['status' => MissionStatus::Closed]);
            }

            return ['outcome' => 'accepted', 'candidature' => $candidature];
        });

        if ($result['outcome'] === 'accepted') {
            // Dispatch hors transaction : un rollback ne doit pas notifier (D-2.4.f).
            UgcMissionDealAccepted::dispatch($result['candidature']);

            return response()->json([
                'data' => new CandidatureResource($result['candidature']),
                'message' => 'Mission acceptée — votre engagement est enregistré',
            ]);
        }

        return match ($result['outcome']) {
            'closed' => response()->json([
                'error' => ['code' => 'MISSION_CLOSED', 'message' => "Cette mission n'accepte plus de candidatures"],
            ], 422),
            'full' => response()->json(
                ErrorCodes::MissionFull->envelope('Toutes les places de cette mission sont déjà pourvues.'),
                422
            ),
            'already' => response()->json(
                ErrorCodes::AlreadyAccepted->envelope('Vous avez déjà accepté cette mission.'),
                422
            ),
        };
    }
}
