<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationListResource;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Services\Messaging\ConversationRealtime;
use App\Services\Messaging\ConversationUnreadCounter;
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
     * Returns conversations ordered by most recent message, with pagination.
     */
    public function index(Request $request, ConversationUnreadCounter $counter): JsonResponse
    {
        $user = $request->user();
        $face = $user->userable;

        // Get conversations where this Face is the candidate
        // Order by latest message timestamp, fallback to conversation updated_at for conversations without messages
        $conversations = Conversation::whereHas('candidature', function ($query) use ($face) {
            $query->where('face_id', $face->id);
        })
            ->with([
                'candidature.mission.producer',
                'candidature.face',
                'candidature.paymentEntry.missionPayment',
                'latestMessage.sender.userable',
            ])
            ->withUnreadCountFor($user)
            ->orderByRaw('COALESCE((SELECT MAX(created_at) FROM messages WHERE conversation_id = conversations.id), conversations.updated_at) DESC')
            ->paginate(15);

        return response()->json([
            'data' => ConversationListResource::collection($conversations),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
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
