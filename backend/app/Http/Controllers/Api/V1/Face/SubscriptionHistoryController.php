<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaceSubscriptionHistoryResource;
use App\Models\Face;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only subscription history for the authenticated Face — backs the
 * "Historique des abonnements" section of the Face billing tab (FaceBillingPage).
 *
 * Returns ALL of the Face's own FaceSubscription rows, newest first. Presentation
 * (which rows land in the "history" list vs. the highlighted current card) is the
 * frontend's concern. Admin audit data is intentionally excluded — see
 * FaceSubscriptionHistoryResource.
 */
class SubscriptionHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ], 403);
        }

        /** @var Face $face */
        $face = Face::query()->findOrFail($user->userable_id);

        $subscriptions = $face->subscriptions()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => FaceSubscriptionHistoryResource::collection($subscriptions),
        ]);
    }
}
