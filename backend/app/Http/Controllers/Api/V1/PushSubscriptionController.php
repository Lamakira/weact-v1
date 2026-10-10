<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Services\Push\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Web push subscriptions of the authenticated user (Face or Producer; admins
 * are refused upstream by api.token). One row per device endpoint.
 */
class PushSubscriptionController extends Controller
{
    /**
     * VAPID public key for PushManager.subscribe(); null when push is disabled
     * (the frontend then hides the toggle).
     */
    public function publicKey(WebPushService $push): JsonResponse
    {
        return response()->json(['data' => ['public_key' => $push->publicKey()]]);
    }

    /**
     * Register or refresh this device. An endpoint already attached to another
     * user (shared phone) is re-attached to the current user.
     */
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        /** @var array{endpoint: string, keys: array{p256dh: string, auth: string}, content_encoding?: string|null} $data */
        $data = $request->validated();

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['content_encoding'] ?? 'aes128gcm',
        );

        return response()->json(['data' => null, 'message' => 'Notifications activées sur cet appareil'], 201);
    }

    /**
     * Forget this device (only the caller's own subscription for that endpoint).
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['data' => null, 'message' => 'Notifications désactivées sur cet appareil']);
    }
}
