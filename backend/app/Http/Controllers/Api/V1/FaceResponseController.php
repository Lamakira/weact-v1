<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Producer;
use App\Services\Messaging\FaceResponsiveness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Délai de réponse de la Face, affiché au Producteur dans la discussion (lecture seule).
 */
class FaceResponseController extends Controller
{
    public function __construct(private readonly FaceResponsiveness $responsiveness) {}

    /**
     * Chat de booking : réservé au Producteur du booking, une fois le chat lisible.
     */
    public function booking(Request $request, Booking $booking): JsonResponse
    {
        if ($request->user()?->id !== $booking->producer_id) {
            return $this->forbiddenResponse();
        }

        if (Gate::denies('viewMessages', $booking)) {
            return response()->json([
                'error' => [
                    'code' => 'CHAT_LOCKED',
                    'message' => 'Le chat n\'est pas encore débloqué',
                ],
            ], 403);
        }

        $booking->loadMissing('face.userable');

        return response()->json(['data' => $this->responsiveness->noticeForBooking($booking)]);
    }

    /**
     * Conversation de mission : réservée au Producteur de la mission.
     */
    public function conversation(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if ($user === null || Gate::forUser($user)->denies('view', $conversation)) {
            return $this->forbiddenResponse();
        }

        $conversation->loadMissing('candidature.face.user', 'candidature.mission');

        $mission = $conversation->candidature?->mission;
        if ($user->userable_type !== Producer::class || $mission === null || $mission->producer_id !== $user->userable_id) {
            return $this->forbiddenResponse();
        }

        return response()->json(['data' => $this->responsiveness->noticeForConversation($conversation)]);
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Accès refusé',
            ],
        ], 403);
    }
}
