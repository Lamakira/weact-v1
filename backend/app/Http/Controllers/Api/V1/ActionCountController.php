<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Deliverable;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Services\Messaging\ConversationUnreadCounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Compteurs des badges de navigation (lot D3) : chaque valeur est le nombre d'éléments du filtre
 * « À traiter » de la page qu'elle désigne (mêmes scopes que les listes), calculé par COUNT sans
 * charger de liste. Nombre de requêtes constant.
 */
class ActionCountController extends Controller
{
    public function __invoke(Request $request, ConversationUnreadCounter $counter): JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type === Face::class) {
            $counts = [
                'bookings' => Booking::query()->toProcess($user)->count(),
                'candidatures' => Candidature::query()->where('face_id', $user->userable_id)->toProcess()->count(),
                // Vidéos UGC à envoyer : bookings (face_id = users.id) + candidatures (face_id = faces.id).
                'livrables' => Booking::query()->where('face_id', $user->id)->awaitingFaceVideo()->count()
                    + Candidature::query()->where('face_id', $user->userable_id)->awaitingFaceVideo()->count(),
            ];
        } elseif ($user->userable_type === Producer::class) {
            $counts = [
                'bookings' => Booking::query()->toProcess($user)->count(),
                'missions' => Mission::query()->toProcessForProducer($user->userable_id)->count(),
                'livrables' => Deliverable::query()->inReviewForProducer($user)->count(),
            ];
        } else {
            abort(403);
        }

        $counts['messages'] = $counter->countFor($user);

        return response()->json(['data' => $counts]);
    }
}
