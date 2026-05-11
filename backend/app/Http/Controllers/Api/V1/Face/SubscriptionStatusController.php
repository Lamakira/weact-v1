<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\FaceSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Services\FaceEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionStatusController extends Controller
{
    public function __construct(
        private readonly FaceEntitlementService $entitlement,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ], 403);
        }

        $face = Face::query()
            ->with(['activeSubscription', 'photos'])
            ->findOrFail($user->userable_id);

        $subscription = $this->resolveRepresentativeSubscription($face);
        $status = $subscription?->status?->value ?? 'free';
        $isPremium = $this->entitlement->isPremium($face);
        $publicLimit = $this->entitlement->publicAlbumPhotoLimit($face);
        $uploadLimit = $this->entitlement->albumUploadLimit($face);
        $currentPhotos = $face->photos->count();
        $publicPhotos = min($currentPhotos, $publicLimit);
        $lockedPhotos = max(0, $currentPhotos - $publicLimit);
        $hasActingVideo = $face->acting_video !== null;
        $canRenew = $status !== FaceSubscriptionStatus::PendingPayment->value;
        $configuredAmount = max(0, (int) config('face_premium.annual_plan.amount'));

        return response()->json([
            'data' => [
                'status' => $status,
                'plan' => $subscription?->plan?->value,
                'starts_at' => $subscription?->starts_at?->toIso8601String(),
                'expires_at' => $subscription?->expires_at?->toIso8601String(),
                'cancelled_at' => $subscription?->cancelled_at?->toIso8601String(),
                'is_premium' => $isPremium,
                'is_featured_by_subscription' => $this->entitlement->isFeaturedBySubscription($face),
                'can_renew' => $canRenew,
                'subscription_id' => $subscription?->uuid,
                'entitlements' => [
                    'album_upload_limit' => $uploadLimit,
                    'public_album_photo_limit' => $publicLimit,
                    'current_album_photo_count' => $currentPhotos,
                    'public_album_photo_count' => $publicPhotos,
                    'locked_album_photo_count' => $lockedPhotos,
                    'can_upload_acting_video' => $this->entitlement->canUploadActingVideo($face),
                    'has_acting_video' => $hasActingVideo,
                    'is_acting_video_publicly_visible' => $isPremium && $hasActingVideo,
                ],
                'annual_plan' => [
                    'amount' => $configuredAmount,
                    'currency' => (string) config('face_premium.annual_plan.currency'),
                    'provider' => (string) config('face_premium.annual_plan.provider'),
                    'is_available' => $canRenew && $configuredAmount > 0,
                ],
            ],
        ]);
    }

    /**
     * Resolve the single subscription row that represents the Face's current
     * status: prefer the qualifying active one (via the eager-loaded short-
     * circuit), then any pending payment, otherwise the most recently created
     * terminal row (expired/cancelled/failed). Rows with `status = active` but
     * past `expires_at` are excluded from the fallback so the response never
     * surfaces `status="active"` while entitlements remain free-tier.
     */
    private function resolveRepresentativeSubscription(Face $face): ?FaceSubscription
    {
        if ($face->relationLoaded('activeSubscription') && $face->activeSubscription !== null) {
            return $face->activeSubscription;
        }

        return $face->subscriptions()
            ->whereIn('status', [
                FaceSubscriptionStatus::PendingPayment->value,
                FaceSubscriptionStatus::Expired->value,
                FaceSubscriptionStatus::Cancelled->value,
                FaceSubscriptionStatus::Failed->value,
            ])
            ->orderByRaw(
                'FIELD(status, ?) DESC, created_at DESC, id DESC',
                [FaceSubscriptionStatus::PendingPayment->value],
            )
            ->first();
    }
}
