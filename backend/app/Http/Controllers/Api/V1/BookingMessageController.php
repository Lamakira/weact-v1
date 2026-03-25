<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\BookingMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\SendBookingMessageRequest;
use App\Http\Resources\BookingMessageResource;
use App\Models\Booking;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BookingMessageController extends Controller
{
    /**
     * List messages for a booking (oldest first, paginated).
     *
     * Requires: booking is in a chat-eligible status (paid or beyond).
     * Both parties (face and producer) can read messages.
     */
    public function index(Booking $booking): AnonymousResourceCollection|JsonResponse
    {
        try {
            Gate::authorize('viewMessages', $booking);
        } catch (AuthorizationException) {
            return $this->chatLockedResponse();
        }

        $messages = $booking->messages()
            ->with('sender.userable')
            ->paginate(30);

        return BookingMessageResource::collection($messages);
    }

    /**
     * Send a new message in a booking chat.
     *
     * Requires: booking is in an active chat status (paid, confirmed_*).
     * Broadcasts to the other party via Reverb.
     */
    public function store(SendBookingMessageRequest $request, Booking $booking): JsonResponse
    {
        try {
            Gate::authorize('sendMessage', $booking);
        } catch (AuthorizationException) {
            return $this->chatLockedResponse();
        }

        $message = $booking->messages()->create([
            'sender_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        $message->load('sender.userable');

        broadcast(new BookingMessageSent($message))->toOthers();

        return response()->json([
            'data' => new BookingMessageResource($message),
            'message' => 'Message envoyé avec succès',
        ], 201);
    }

    /**
     * Standard 403 response for chat-locked bookings (AC1 spec).
     */
    private function chatLockedResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CHAT_LOCKED',
                'message' => 'Le chat n\'est pas encore débloqué',
            ],
        ], 403);
    }
}
