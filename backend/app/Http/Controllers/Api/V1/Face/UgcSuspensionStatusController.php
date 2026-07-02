<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Resources\UgcSuspensionStatusResource;
use App\Models\UgcSuspension;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * État de suspension douce UGC de la Face (écran 10A, story 5.2). Source serveur
 * autoritative + suspension-aware : query directe sur ugc_suspensions (la table EST
 * la vérité de FaceEntitlementService::isUgcSuspended), JAMAIS capabilities.ugc_access
 * (tier-driven + caché 60 s → pas suspension-aware, anti-pattern D-2.2.b).
 *
 * Le middleware `face` garantit une Face authentifiée → pas de garde 403 manuelle
 * (calque UgcMissionDiscoveryController).
 */
class UgcSuspensionStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $face = $request->user()->userable;

        /** @var \App\Models\UgcSuspension|null $suspension */
        $suspension = UgcSuspension::query()
            ->where('face_id', $face->getKey())
            ->whereNull('reactivated_at')
            ->with('shipment.owner')
            ->first();

        return response()->json([
            'data' => [
                'is_suspended' => $suspension !== null,
                'suspension' => $suspension === null
                    ? null
                    : (new UgcSuspensionStatusResource($suspension))->resolve($request),
            ],
        ]);
    }
}
