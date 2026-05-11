<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubscriptionStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    public function test_unauthenticated_request_returns_401_with_envelope(): void
    {
        // AC #13 — sanctum returns 401 with global UNAUTHENTICATED envelope + French message
        $response = $this->getJson('/api/v1/face/subscription-status');

        $response->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.message', 'Non authentifié.');
    }

    public function test_producer_user_gets_403_with_envelope(): void
    {
        // AC #14 — non-Face access is rejected with controller-level FORBIDDEN
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $response = $this->actingAs($producerUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_user_without_face_userable_gets_403_with_envelope(): void
    {
        // AC #15 — admin / unlinked user (userable_type = null) gets the same 403 envelope
        $orphanUser = User::factory()->create([
            'userable_type' => null,
            'userable_id' => null,
        ]);

        $response = $this->actingAs($orphanUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Accès réservé aux Faces');
    }

    public function test_free_face_with_no_subscription_returns_free_status(): void
    {
        // AC #3, #11, #12 — free baseline + zero photos + no acting video
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'free')
            ->assertJsonPath('data.plan', null)
            ->assertJsonPath('data.starts_at', null)
            ->assertJsonPath('data.expires_at', null)
            ->assertJsonPath('data.cancelled_at', null)
            ->assertJsonPath('data.is_premium', false)
            ->assertJsonPath('data.is_featured_by_subscription', false)
            ->assertJsonPath('data.can_renew', true)
            ->assertJsonPath('data.subscription_id', null)
            ->assertJsonPath('data.entitlements.album_upload_limit', 2)
            ->assertJsonPath('data.entitlements.public_album_photo_limit', 2)
            ->assertJsonPath('data.entitlements.current_album_photo_count', 0)
            ->assertJsonPath('data.entitlements.public_album_photo_count', 0)
            ->assertJsonPath('data.entitlements.locked_album_photo_count', 0)
            ->assertJsonPath('data.entitlements.can_upload_acting_video', false)
            ->assertJsonPath('data.entitlements.has_acting_video', false)
            ->assertJsonPath('data.entitlements.is_acting_video_publicly_visible', false)
            ->assertJsonPath('data.annual_plan.amount', 50000)
            ->assertJsonPath('data.annual_plan.currency', 'XOF')
            ->assertJsonPath('data.annual_plan.provider', 'fedapay')
            ->assertJsonPath('data.annual_plan.is_available', true);
    }

    public function test_active_premium_face_returns_active_status_with_premium_limits(): void
    {
        // AC #4, #11 — active + 3 photos: premium limits, no locked photos
        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
        ]);
        FacePhoto::factory()->createSequentialForFace($this->face, 3);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan', 'annual_premium')
            ->assertJsonPath('data.cancelled_at', null)
            ->assertJsonPath('data.is_premium', true)
            ->assertJsonPath('data.is_featured_by_subscription', true)
            ->assertJsonPath('data.can_renew', true)
            ->assertJsonPath('data.subscription_id', $subscription->uuid)
            ->assertJsonPath('data.entitlements.album_upload_limit', 4)
            ->assertJsonPath('data.entitlements.public_album_photo_limit', 4)
            ->assertJsonPath('data.entitlements.current_album_photo_count', 3)
            ->assertJsonPath('data.entitlements.public_album_photo_count', 3)
            ->assertJsonPath('data.entitlements.locked_album_photo_count', 0)
            ->assertJsonPath('data.entitlements.can_upload_acting_video', true);

        $this->assertNotNull($response->json('data.starts_at'));
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_active_premium_face_with_4_photos_returns_no_locked_photos(): void
    {
        // AC #11 — premium boundary: 4 photos all publicly visible
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        FacePhoto::factory()->createSequentialForFace($this->face, 4);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.entitlements.current_album_photo_count', 4)
            ->assertJsonPath('data.entitlements.public_album_photo_count', 4)
            ->assertJsonPath('data.entitlements.locked_album_photo_count', 0);
    }

    public function test_free_face_with_4_photos_returns_2_locked_photos(): void
    {
        // AC #11 — free boundary / downgrade scenario: 4 photos stored, 2 locked
        FacePhoto::factory()->createSequentialForFace($this->face, 4);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.entitlements.current_album_photo_count', 4)
            ->assertJsonPath('data.entitlements.public_album_photo_count', 2)
            ->assertJsonPath('data.entitlements.locked_album_photo_count', 2);
    }

    public function test_pending_payment_face_returns_pending_status_with_free_limits_and_blocked_renew(): void
    {
        // AC #5 — pending blocks renew, free limits apply, plan is_available=false
        $subscription = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.plan', 'annual_premium')
            ->assertJsonPath('data.starts_at', null)
            ->assertJsonPath('data.expires_at', null)
            ->assertJsonPath('data.cancelled_at', null)
            ->assertJsonPath('data.is_premium', false)
            ->assertJsonPath('data.is_featured_by_subscription', false)
            ->assertJsonPath('data.can_renew', false)
            ->assertJsonPath('data.subscription_id', $subscription->uuid)
            ->assertJsonPath('data.entitlements.album_upload_limit', 2)
            ->assertJsonPath('data.entitlements.public_album_photo_limit', 2)
            ->assertJsonPath('data.entitlements.can_upload_acting_video', false)
            ->assertJsonPath('data.annual_plan.is_available', false);
    }

    public function test_response_never_leaks_provider_payload_fields(): void
    {
        // AC #5 — provider/provider_reference/metadata never appear in response
        FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $this->face->id,
            'provider' => 'fedapay',
            'provider_reference' => 'fedapay_txn_secret_123',
            'metadata' => ['internal_token' => 'should_not_leak'],
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertDontSee('fedapay_txn_secret_123')
            ->assertDontSee('should_not_leak');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('provider', $data);
        $this->assertArrayNotHasKey('provider_reference', $data);
        $this->assertArrayNotHasKey('metadata', $data);
    }

    public function test_expired_face_returns_expired_status_with_free_limits(): void
    {
        // AC #6 — expired surfaces historical dates but free limits apply, can_renew=true
        $subscription = FaceSubscription::factory()->expired()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.plan', 'annual_premium')
            ->assertJsonPath('data.is_premium', false)
            ->assertJsonPath('data.is_featured_by_subscription', false)
            ->assertJsonPath('data.can_renew', true)
            ->assertJsonPath('data.subscription_id', $subscription->uuid)
            ->assertJsonPath('data.entitlements.album_upload_limit', 2)
            ->assertJsonPath('data.entitlements.public_album_photo_limit', 2)
            ->assertJsonPath('data.entitlements.can_upload_acting_video', false);

        $this->assertNotNull($response->json('data.starts_at'));
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_cancelled_face_returns_cancelled_status_with_cancelled_at_set(): void
    {
        // AC #7 — cancelled exposes cancelled_at timestamp + free-tier entitlements
        FaceSubscription::factory()->cancelled()->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.plan', 'annual_premium')
            ->assertJsonPath('data.is_premium', false)
            ->assertJsonPath('data.is_featured_by_subscription', false)
            ->assertJsonPath('data.can_renew', true)
            ->assertJsonPath('data.entitlements.album_upload_limit', 2)
            ->assertJsonPath('data.entitlements.public_album_photo_limit', 2)
            ->assertJsonPath('data.entitlements.can_upload_acting_video', false);

        $this->assertNotNull($response->json('data.cancelled_at'));
    }

    public function test_failed_face_returns_failed_status_with_free_limits(): void
    {
        // AC #8 — failed allows renewal + free-tier entitlements
        FaceSubscription::factory()->failed()->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.plan', 'annual_premium')
            ->assertJsonPath('data.is_premium', false)
            ->assertJsonPath('data.is_featured_by_subscription', false)
            ->assertJsonPath('data.can_renew', true)
            ->assertJsonPath('data.entitlements.album_upload_limit', 2)
            ->assertJsonPath('data.entitlements.public_album_photo_limit', 2)
            ->assertJsonPath('data.entitlements.can_upload_acting_video', false);
    }

    public function test_active_row_wins_over_historical_terminal_rows(): void
    {
        // AC #9 — Face::activeSubscription() short-circuit ignores historical rows
        FaceSubscription::factory()->expired()->create(['face_id' => $this->face->id]);
        FaceSubscription::factory()->failed()->create(['face_id' => $this->face->id]);
        $active = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.subscription_id', $active->uuid)
            ->assertJsonPath('data.is_premium', true);
    }

    public function test_most_recent_terminal_row_wins_when_no_active_or_pending(): void
    {
        // AC #10 — created_at DESC orders terminal rows; recent cancelled beats old expired
        FaceSubscription::factory()->expired()->create([
            'face_id' => $this->face->id,
            'created_at' => now()->subYears(2),
        ]);
        $recent = FaceSubscription::factory()->cancelled()->create([
            'face_id' => $this->face->id,
            'created_at' => now()->subMonths(2),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.subscription_id', $recent->uuid);
    }

    public function test_face_with_acting_video_and_no_subscription_shows_locked_acting_video_flags(): void
    {
        // AC #12 — has_acting_video=true regardless of premium; visibility=false when free
        $this->face->update(['acting_video' => 'legacy.mp4']);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.entitlements.has_acting_video', true)
            ->assertJsonPath('data.entitlements.is_acting_video_publicly_visible', false)
            ->assertJsonPath('data.entitlements.can_upload_acting_video', false);
    }

    public function test_face_with_acting_video_and_active_subscription_shows_unlocked_acting_video_flags(): void
    {
        // AC #12 — premium path: all three acting-video flags true
        $this->face->update(['acting_video' => 'showtime.mp4']);
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.entitlements.has_acting_video', true)
            ->assertJsonPath('data.entitlements.is_acting_video_publicly_visible', true)
            ->assertJsonPath('data.entitlements.can_upload_acting_video', true);
    }

    public function test_active_face_without_acting_video_has_false_is_acting_video_publicly_visible(): void
    {
        // AC #12 — premium but no DB row: visibility=false even though upload allowed
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.entitlements.has_acting_video', false)
            ->assertJsonPath('data.entitlements.is_acting_video_publicly_visible', false)
            ->assertJsonPath('data.entitlements.can_upload_acting_video', true);
    }

    public function test_annual_plan_metadata_reflects_config(): void
    {
        // AC #3 — config-driven amount/currency/provider read at runtime
        config([
            'face_premium.annual_plan.amount' => 75000,
            'face_premium.annual_plan.currency' => 'XOF',
            'face_premium.annual_plan.provider' => 'fedapay',
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.annual_plan.amount', 75000)
            ->assertJsonPath('data.annual_plan.currency', 'XOF')
            ->assertJsonPath('data.annual_plan.provider', 'fedapay')
            ->assertJsonPath('data.annual_plan.is_available', true);
    }

    public function test_annual_plan_is_unavailable_when_amount_is_zero(): void
    {
        // AC #3 — amount=0 disables the CTA without affecting can_renew
        config(['face_premium.annual_plan.amount' => 0]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.annual_plan.amount', 0)
            ->assertJsonPath('data.annual_plan.is_available', false)
            ->assertJsonPath('data.can_renew', true);
    }

    public function test_response_uses_data_envelope_only(): void
    {
        // Envelope contract: only `data` at the top level (no `message`, `meta`, etc.)
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk();
        $this->assertSame(['data'], array_keys($response->json()));
    }

    public function test_active_status_row_with_past_expires_at_does_not_surface_as_active(): void
    {
        // Resolver contract — a zombie row (status='active' but expires_at past) is
        // rejected by both Face::activeSubscription() (expires_at > now() filter) and
        // the fallback whereIn() (excludes Active). The response must surface 'free',
        // not the misleading status='active' with free-tier entitlements.
        $subscription = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        DB::table('face_subscriptions')
            ->where('id', $subscription->id)
            ->update(['expires_at' => now()->subDay()]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.status', 'free')
            ->assertJsonPath('data.subscription_id', null)
            ->assertJsonPath('data.is_premium', false)
            ->assertJsonPath('data.is_featured_by_subscription', false)
            ->assertJsonPath('data.entitlements.album_upload_limit', 2)
            ->assertJsonPath('data.entitlements.public_album_photo_limit', 2);
    }

    public function test_uses_preloaded_active_subscription_to_avoid_extra_query(): void
    {
        // AC #2 — eager-load contract: active path issues no extra subscription SELECT after Face load
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        DB::enableQueryLog();
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();

        $subscriptionQueries = array_filter(
            $queries,
            fn (array $query): bool => str_contains($query['query'], 'face_subscriptions'),
        );
        $this->assertLessThanOrEqual(2, count($subscriptionQueries));
    }
}
