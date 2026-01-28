<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Producer\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Controller for Producer messaging operations.
 *
 * Handles sending messages in a conversation.
 */
class MessageController extends Controller
{
    /**
     * Send a message in a conversation.
     *
     * @param  SendMessageRequest  $request
     * @param  Conversation  $conversation
     * @return JsonResponse
     */
    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        // Authorization via policy - checks if user can send message
        Gate::authorize('sendMessage', $conversation);

        $user = $request->user();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => get_class($user),
            'content' => $request->validated('content'),
        ]);

        // Load the sender relationship for the resource
        $message->load('sender.userable');

        return response()->json([
            'data' => new MessageResource($message),
            'message' => 'Message envoyé',
        ], 201);
    }
}
