<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationListResource;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Controller for Producer conversation operations.
 *
 * Handles listing and viewing conversations with messages.
 */
class ConversationController extends Controller
{
    /**
     * List all conversations for the authenticated Producer.
     *
     * Returns conversations ordered by most recent message, with pagination.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $producer = $user->userable;

        // Get conversations where this Producer owns the mission
        // Order by latest message timestamp, fallback to conversation updated_at for conversations without messages
        $conversations = Conversation::whereHas('candidature.mission', function ($query) use ($producer) {
            $query->where('producer_id', $producer->id);
        })
            ->with([
                'candidature.mission.producer',
                'candidature.face',
                'latestMessage.sender.userable',
            ])
            ->orderByRaw('COALESCE((SELECT MAX(created_at) FROM messages WHERE conversation_id = conversations.id), conversations.updated_at) DESC')
            ->paginate(15);

        return response()->json([
            'data' => ConversationListResource::collection($conversations),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * Show a conversation with its messages.
     *
     * Also marks unread messages from other participant as read.
     *
     * @param  Request  $request
     * @param  Conversation  $conversation
     * @return JsonResponse
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        // Authorization via policy - checks if user can view conversation
        Gate::authorize('view', $conversation);

        $user = $request->user();

        // Mark unread messages from other participant as read
        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id)
            ->update(['read_at' => now()]);

        // Load relationships for the resource with explicit chronological ordering
        $conversation->load([
            'messages' => fn ($query) => $query->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
            'messages.sender.userable',
            'candidature.mission.producer',
            'candidature.face',
        ]);

        return response()->json([
            'data' => new ConversationResource($conversation),
        ]);
    }
}
