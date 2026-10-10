<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\BookingMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\SendBookingMessageRequest;
use App\Http\Resources\BookingMessageResource;
use App\Models\Booking;
use App\Services\Push\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BookingMessageController extends Controller
{
    /**
     * List messages for a booking (newest page first, each page in chronological order).
     *
     * Requires: booking is in a chat-eligible status (paid or beyond).
     * Both parties (face and producer) can read messages.
     */
    public function index(Booking $booking): AnonymousResourceCollection|JsonResponse
    {
        if (Gate::denies('viewMessages', $booking)) {
            return $this->chatLockedResponse();
        }

        $messages = $booking->messages()
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with('sender.userable')
            ->paginate(30);

        // Page 1 = most recent messages; each page is displayed in chronological order.
        $messages->setCollection($messages->getCollection()->reverse()->values());

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
        if (Gate::denies('sendMessage', $booking)) {
            return $this->chatLockedResponse();
        }

        /** @var \App\Models\BookingMessage $message */
        $message = $booking->messages()->create([
            'sender_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        $message->load('sender.userable');

        broadcast(new BookingMessageSent($message))->toOthers();

        // Throttled web push to the other party; never fails the send.
        try {
            $booking->loadMissing('face', 'producer');
            $recipient = $request->user()->id === $booking->face_id ? $booking->producer : $booking->face;

            if ($recipient !== null) {
                app(WebPushService::class)->queueForChatMessage($message->sender, $message->content, $recipient, 'booking', $booking->uuid);
            }
        } catch (\Throwable $e) {
            report($e);
        }

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
