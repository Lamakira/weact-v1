<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\ErrorCodes;
use App\Enums\MissionStatus;
use App\Enums\MissionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\MissionResource;
use App\Http\Resources\UgcMissionTeaserResource;
use App\Models\Mission;
use App\Services\FaceEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UgcMissionDiscoveryController extends Controller
{
    public function __construct(
        private readonly FaceEntitlementService $entitlement,
    ) {}

    /**
     * Liste gated des missions UGC publiées (FR5, écran 6A).
     * Face éligible → MissionResource complet ; sinon teasers + meta paywall.
     */
    public function index(Request $request): JsonResponse
    {
        $face = $request->user()->userable;
        $canAccessUgc = $this->entitlement->canAccessUgc($face);

        $missions = Mission::where('status', MissionStatus::Published)
            ->where('type_mission', MissionType::Ugc->value)
            ->whereProducerActive()
            ->notExpired() // exclut les missions UGC dont la date limite de candidature est passée
            // Photos produit : chargées pour LES DEUX branches (décision PO — la
            // photo est l'argument d'upsell, la carte verrouillée la montre aussi).
            // Disque public côté mission : aucune URL signée, rien à protéger ici.
            ->with('productPhotos')
            // Le teaser n'expose jamais le producer — eager-load réservé à la branche éligible
            ->when($canAccessUgc, fn ($query) => $query->with('producer'))
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        if ($canAccessUgc) {
            $response = MissionResource::collection($missions)->response()->getData(true);
            $response['meta']['can_access_ugc'] = true;

            return response()->json($response);
        }

        $response = UgcMissionTeaserResource::collection($missions)->response()->getData(true);
        $response['meta']['can_access_ugc'] = false;
        $response['meta']['paywall'] = [
            'code' => ErrorCodes::UgcSubscriptionRequired->value,
            'message' => "L'accès aux missions UGC est réservé aux Faces abonnées (Starter et plus).",
            'pricing_url' => '/pricing',
        ];

        return response()->json($response);
    }
}
