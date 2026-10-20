<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingThreadListResource;
use App\Http\Resources\ConversationListResource;
use App\Http\Resources\ConversationResource;
use App\Models\Booking;
use App\Models\Conversation;
use App\Services\Messaging\ConversationRealtime;
use App\Services\Messaging\ConversationUnreadCounter;
use App\Services\Messaging\MessageThreadLister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Controller for Face conversation operations.
 *
 * Handles listing and viewing conversations with messages.
 */
class ConversationController extends Controller
{
    /**
     * List all conversations for the authenticated Face.
     *
     * Returns mission conversations and booking chats merged, ordered by most recent message, with pagination.
     */
    public function index(Request $request, ConversationUnreadCounter $counter, MessageThreadLister $lister): JsonResponse
    {
        $user = $request->user();

        // Conversations de mission + chats de booking lisibles, triés ensemble par dernier message
        $threads = $lister->paginate($user, 15);

        return response()->json([
            'data' => $threads->getCollection()->map(
                fn ($thread) => $thread instanceof Booking
                    ? (new BookingThreadListResource($thread))->resolve($request)
                    : (new ConversationListResource($thread))->resolve($request)
            )->values(),
            'meta' => [
                'current_page' => $threads->currentPage(),
                'last_page' => $threads->lastPage(),
                'per_page' => $threads->perPage(),
                'total' => $threads->total(),
                'unread_conversations_count' => $counter->countFor($user),
            ],
        ]);
    }

    /**
     * Number of conversations with at least one unread message (sidebar badge).
     */
    public function unreadCount(Request $request, ConversationUnreadCounter $counter): JsonResponse
    {
        return response()->json([
            'data' => ['count' => $counter->countFor($request->user())],
        ]);
    }

    /**
     * Show a conversation with its messages.
     *
     * Also marks unread messages from other participant as read.
     */
    public function show(Request $request, Conversation $conversation, ConversationRealtime $realtime, ConversationUnreadCounter $counter): JsonResponse
    {
        // Authorization via policy - checks if user can view conversation
        Gate::authorize('view', $conversation);

        $user = $request->user();

        // Mark unread messages from other participant as read (+ live read receipt)
        $realtime->markRead($conversation, $user);

        // Load relationships for the resource with explicit chronological ordering
        $conversation->load([
            'messages' => fn ($query) => $query->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
            'messages.sender.userable',
            'candidature.mission.producer',
            'candidature.face',
            'candidature.paymentEntry.missionPayment',
        ]);

        return response()->json([
            'data' => new ConversationResource($conversation),
            'meta' => ['unread_conversations_count' => $counter->countFor($user)],
        ]);
    }

    /**
     * Mark the other participant's messages as read without reloading the thread.
     *
     * Idempotent: a second call marks nothing and broadcasts nothing.
     */
    public function markRead(Request $request, Conversation $conversation, ConversationRealtime $realtime, ConversationUnreadCounter $counter): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $marked = $realtime->markRead($conversation, $user);

        return response()->json([
            'data' => [
                'marked' => $marked,
                'unread_conversations_count' => $counter->countFor($user),
            ],
        ]);
    }
}
