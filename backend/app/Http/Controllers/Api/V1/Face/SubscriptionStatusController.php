<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\FaceSubscriptionStatus;
use App\Enums\FaceSubscriptionTier;
use App\Http\Controllers\Controller;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Services\FaceEntitlementService;
use App\ValueObjects\TierCapabilities;
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
            ->with('activeSubscription')
            ->findOrFail($user->userable_id);

        $capabilities = $this->entitlement->capabilities($face);
        $representative = $this->resolveRepresentativeSubscription($face);
        $hasPendingPayment = $face->subscriptions()
            ->where('status', FaceSubscriptionStatus::PendingPayment)
            ->exists();

        return response()->json([
            'data' => [
                'current' => [
                    'tier' => $capabilities->tier->value,
                    'plan' => $representative?->plan->value,
                    'status' => $representative?->status->value ?? 'free',
                    'starts_at' => $representative?->starts_at?->toIso8601String(),
                    'expires_at' => $representative?->expires_at?->toIso8601String(),
                    'cancelled_at' => $representative?->cancelled_at?->toIso8601String(),
                    'capabilities' => $this->capabilitiesArray($capabilities),
                ],
                'offers' => $this->buildOffers(),
                'cta' => $this->buildCta($capabilities, $representative, $hasPendingPayment),
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
        if ($face->relationLoaded('activeSubscription')) {
            $candidate = $face->getRelation('activeSubscription');

            if ($candidate instanceof FaceSubscription) {
                return $candidate;
            }
        }

        return FaceSubscription::query()
            ->where('face_id', $face->getKey())
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

    /**
     * The four tier offers (Free, Starter, Pro, Élite) in ascending order,
     * each with a config-driven price + the full capabilities matrix. Lets the
     * frontend render the tier-comparison cards without any extra request.
     *
     * @return list<array<string, mixed>>
     */
    private function buildOffers(): array
    {
        $currency = (string) config('face_subscription_tiers.currency');
        $offers = [];

        foreach (FaceSubscriptionTier::cases() as $tier) {
            $offers[] = [
                'tier' => $tier->value,
                'price' => (int) config('face_subscription_tiers.tiers.'.$tier->value.'.price'),
                'currency' => $currency,
                'capabilities' => $this->capabilitiesArray(
                    $this->entitlement->capabilitiesForTier($tier),
                ),
            ];
        }

        return $offers;
    }

    /**
     * Payment-action affordances. `upgrade`/`downgrade` are derived from the
     * current tier's `sort_priority` against every tier; `renew` is true when
     * the Face has a subscription on record. Every flag is forced to false
     * while a payment is pending — a second initiation would be rejected by
     * FP-2.5 with PENDING_PAYMENT_EXISTS.
     *
     * @return array<string, bool>
     */
    private function buildCta(
        TierCapabilities $capabilities,
        ?FaceSubscription $representative,
        bool $hasPendingPayment,
    ): array {
        $currentPriority = $capabilities->sortPriority;
        $hasHigherTier = false;
        $hasLowerPaidTier = false;

        foreach (FaceSubscriptionTier::cases() as $tier) {
            $tierCapabilities = $this->entitlement->capabilitiesForTier($tier);
            $price = (int) config('face_subscription_tiers.tiers.'.$tier->value.'.price');

            if ($tierCapabilities->sortPriority < $currentPriority) {
                $hasHigherTier = true;
            }

            if ($tierCapabilities->sortPriority > $currentPriority && $price > 0) {
                $hasLowerPaidTier = true;
            }
        }

        return [
            'upgrade_available' => ! $hasPendingPayment && $hasHigherTier,
            'downgrade_available' => ! $hasPendingPayment && $hasLowerPaidTier,
            'renew_available' => ! $hasPendingPayment && $representative !== null,
        ];
    }

    /**
     * Serialize a TierCapabilities value object to the canonical 8-field JSON
     * matrix. The single producer of this shape — used for both `current` and
     * every `offers[]` entry so the two are guaranteed identical.
     *
     * @return array<string, mixed>
     */
    private function capabilitiesArray(TierCapabilities $capabilities): array
    {
        return [
            'max_album_photos' => $capabilities->maxAlbumPhotos,
            'max_presentation_videos' => $capabilities->maxPresentationVideos,
            'max_acting_videos' => $capabilities->maxActingVideos,
            'max_ugc_videos' => $capabilities->maxUgcVideos,
            'ugc_access' => $capabilities->ugcAccess,
            'commission_rate' => $capabilities->commissionRate,
            'sort_priority' => $capabilities->sortPriority,
            'has_elite_badge' => $capabilities->hasEliteBadge,
        ];
    }
}
