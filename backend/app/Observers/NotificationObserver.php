<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Services\Push\WebPushService;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Support\Facades\Log;

class NotificationObserver
{
    /**
     * Handle the Notification "created" event.
     *
     * Dispatches a broadcast event so the frontend receives real-time updates.
     * Wrapped in try/catch so notification creation never fails due to broadcast issues.
     */
    public function created(Notification $notification): void
    {
        try {
            NotificationCreated::dispatch($notification);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch NotificationCreated broadcast', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Web push for allowlisted types (queued after commit, silent without VAPID keys).
        try {
            app(WebPushService::class)->queueForNotification($notification);
        } catch (\Throwable $e) {
            Log::error('Failed to queue web push for notification', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'error' => $e->getMessage(),
            ]);
        }

        // WhatsApp alert for allowlisted types (opt-in users only; queued after commit, silent when unavailable).
        try {
            app(WhatsappService::class)->queueForNotification($notification);
        } catch (\Throwable $e) {
            Log::error('Failed to queue WhatsApp alert for notification', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
