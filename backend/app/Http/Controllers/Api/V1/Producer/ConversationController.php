<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Controller for Producer conversation operations.
 *
 * Handles viewing conversation with messages.
 */
class ConversationController extends Controller
{
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

        // Load relationships for the resource
        $conversation->load([
            'messages.sender.userable',
            'candidature.mission.producer',
            'candidature.face',
        ]);

        return response()->json([
            'data' => new ConversationResource($conversation),
        ]);
    }
}
