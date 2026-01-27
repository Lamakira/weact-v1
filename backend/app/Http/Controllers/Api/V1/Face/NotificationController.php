<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated Face.
     *
     * Returns paginated notifications, most recent first.
     * Authorization is handled by the 'face' middleware at route level.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->paginate(15);

        return NotificationResource::collection($notifications);
    }

    /**
     * Get the count of unread notifications.
     *
     * Returns the number of notifications that haven't been read yet.
     * Authorization is handled by the 'face' middleware at route level.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)
            ->unread()
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a notification as read.
     *
     * Sets the read_at timestamp for the notification.
     * Authorization is handled by the 'face' middleware at route level.
     * Only the notification owner can mark it as read.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        // Verify notification belongs to this user
        if ($notification->user_id !== $user->id) {
            abort(403, 'Cette notification ne vous appartient pas');
        }

        $notification->markAsRead();

        return response()->json([
            'data' => new NotificationResource($notification),
            'message' => 'Notification marquée comme lue',
        ]);
    }

    /**
     * Mark all notifications as read.
     *
     * Sets the read_at timestamp for all unread notifications.
     * Authorization is handled by the 'face' middleware at route level.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        Notification::where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Toutes les notifications marquées comme lues',
        ]);
    }
}
