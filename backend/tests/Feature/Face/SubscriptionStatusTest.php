<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Face;
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

    // ---------------------------------------------------------------------
    // Auth (AC #15)
    // ---------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401_with_envelope(): void
    {
        $response = $this->getJson('/api/v1/face/subscription-status');

        $response->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.message', 'Non authentifié.');
    }

    public function test_producer_user_gets_403_with_envelope(): void
    {
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

    // ---------------------------------------------------------------------
    // data.current per subscription state (AC #3 / #4)
    // ---------------------------------------------------------------------

    public function test_free_face_with_no_subscription_returns_free_current_block(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.tier', 'free')
            ->assertJsonPath('data.current.plan', null)
            ->assertJsonPath('data.current.status', 'free')
            ->assertJsonPath('data.current.starts_at', null)
            ->assertJsonPath('data.current.expires_at', null)
            ->assertJsonPath('data.current.cancelled_at', null)
            ->assertJsonPath('data.current.capabilities', $this->tierMatrices()['free']);
    }

    public function test_active_starter_face_returns_starter_current_block(): void
    {
        FaceSubscription::factory()->starter()->active()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.tier', 'starter')
            ->assertJsonPath('data.current.plan', 'starter')
            ->assertJsonPath('data.current.status', 'active')
            ->assertJsonPath('data.current.capabilities', $this->tierMatrices()['starter']);

        $this->assertNotNull($response->json('data.current.starts_at'));
        $this->assertNotNull($response->json('data.current.expires_at'));
    }

    public function test_active_pro_face_returns_pro_current_block(): void
    {
        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.tier', 'pro')
            ->assertJsonPath('data.current.plan', 'pro')
            ->assertJsonPath('data.current.status', 'active')
            ->assertJsonPath('data.current.capabilities', $this->tierMatrices()['pro']);

        $this->assertNotNull($response->json('data.current.starts_at'));
        $this->assertNotNull($response->json('data.current.expires_at'));
    }

    public function test_active_elite_face_returns_elite_current_block(): void
    {
        FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.tier', 'elite')
            ->assertJsonPath('data.current.plan', 'elite')
            ->assertJsonPath('data.current.status', 'active')
            ->assertJsonPath('data.current.capabilities', $this->tierMatrices()['elite'])
            ->assertJsonPath('data.current.capabilities.commission_rate', 0.05)
            ->assertJsonPath('data.current.capabilities.sort_priority', 1)
            ->assertJsonPath('data.current.capabilities.has_elite_badge', true);
    }

    public function test_expired_face_returns_free_tier_with_historical_plan_and_dates(): void
    {
        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.tier', 'free')
            ->assertJsonPath('data.current.plan', 'pro')
            ->assertJsonPath('data.current.status', 'expired')
            ->assertJsonPath('data.current.capabilities', $this->tierMatrices()['free']);

        $this->assertNotNull($response->json('data.current.starts_at'));
        $this->assertNotNull($response->json('data.current.expires_at'));
    }

    public function test_cancelled_face_returns_free_tier_with_cancelled_at(): void
    {
        FaceSubscription::factory()->pro()->cancelled()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.tier', 'free')
            ->assertJsonPath('data.current.plan', 'pro')
            ->assertJsonPath('data.current.status', 'cancelled')
            ->assertJsonPath('data.current.capabilities', $this->tierMatrices()['free']);

        $this->assertNotNull($response->json('data.current.cancelled_at'));
    }

    public function test_failed_face_returns_free_tier_failed_status(): void
    {
        FaceSubscription::factory()->pro()->failed()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.tier', 'free')
            ->assertJsonPath('data.current.plan', 'pro')
            ->assertJsonPath('data.current.status', 'failed')
            ->assertJsonPath('data.current.capabilities', $this->tierMatrices()['free']);
    }

    public function test_pending_payment_face_returns_free_tier_pending_status(): void
    {
        FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.tier', 'free')
            ->assertJsonPath('data.current.plan', 'pro')
            ->assertJsonPath('data.current.status', 'pending_payment')
            ->assertJsonPath('data.current.starts_at', null)
            ->assertJsonPath('data.current.expires_at', null)
            ->assertJsonPath('data.current.capabilities', $this->tierMatrices()['free']);
    }

    // ---------------------------------------------------------------------
    // Representative subscription resolver (AC #12 / #13)
    // ---------------------------------------------------------------------

    public function test_active_row_wins_over_historical_terminal_rows(): void
    {
        $faceId = $this->face->id;
        FaceSubscription::factory()->pro()->expired()->create(['face_id' => $faceId]);
        FaceSubscription::factory()->starter()->failed()->create(['face_id' => $faceId]);
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $faceId]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.status', 'active')
            ->assertJsonPath('data.current.tier', 'pro');
    }

    public function test_most_recent_terminal_row_wins_when_no_active(): void
    {
        $faceId = $this->face->id;
        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $faceId,
            'created_at' => now()->subYears(2),
        ]);
        FaceSubscription::factory()->pro()->cancelled()->create([
            'face_id' => $faceId,
            'created_at' => now()->subMonths(2),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.status', 'cancelled');
    }

    public function test_zombie_active_row_with_past_expiry_resolves_to_free(): void
    {
        $subscription = FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
        ]);
        DB::table('face_subscriptions')
            ->where('id', $subscription->id)
            ->update(['expires_at' => now()->subDay()]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.tier', 'free')
            ->assertJsonPath('data.current.status', 'free')
            ->assertJsonPath('data.current.plan', null)
            ->assertJsonPath('data.current.capabilities', $this->tierMatrices()['free']);
    }

    // ---------------------------------------------------------------------
    // data.offers (AC #6 / #7)
    // ---------------------------------------------------------------------

    public function test_offers_returns_four_tiers_in_ascending_order(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonCount(4, 'data.offers')
            ->assertJsonPath('data.offers.0.tier', 'free')
            ->assertJsonPath('data.offers.1.tier', 'starter')
            ->assertJsonPath('data.offers.2.tier', 'pro')
            ->assertJsonPath('data.offers.3.tier', 'elite');
    }

    public function test_offers_prices_match_config(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.offers.0.price', 0)
            ->assertJsonPath('data.offers.1.price', 12000)
            ->assertJsonPath('data.offers.2.price', 25000)
            ->assertJsonPath('data.offers.3.price', 40000)
            ->assertJsonPath('data.offers.0.currency', 'XOF')
            ->assertJsonPath('data.offers.1.currency', 'XOF')
            ->assertJsonPath('data.offers.2.currency', 'XOF')
            ->assertJsonPath('data.offers.3.currency', 'XOF');
    }

    public function test_offers_capabilities_match_config_per_tier(): void
    {
        $matrices = $this->tierMatrices();

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.offers.0.capabilities', $matrices['free'])
            ->assertJsonPath('data.offers.1.capabilities', $matrices['starter'])
            ->assertJsonPath('data.offers.2.capabilities', $matrices['pro'])
            ->assertJsonPath('data.offers.3.capabilities', $matrices['elite']);
    }

    public function test_offer_capabilities_shape_matches_current_capabilities_shape(): void
    {
        $expectedKeys = [
            'max_album_photos',
            'max_presentation_videos',
            'max_acting_videos',
            'max_ugc_videos',
            'ugc_access',
            'commission_rate',
            'sort_priority',
            'has_elite_badge',
        ];

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk();

        $this->assertSame($expectedKeys, array_keys((array) $response->json('data.current.capabilities')));
        $this->assertSame($expectedKeys, array_keys((array) $response->json('data.offers.0.capabilities')));
    }

    public function test_offer_price_is_config_driven(): void
    {
        config(['face_subscription_tiers.tiers.pro.price' => 99999]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.offers.2.tier', 'pro')
            ->assertJsonPath('data.offers.2.price', 99999);
    }

    // ---------------------------------------------------------------------
    // data.cta (AC #8 / #9)
    // ---------------------------------------------------------------------

    public function test_cta_for_free_face_allows_upgrade_only(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.cta.upgrade_available', true)
            ->assertJsonPath('data.cta.downgrade_available', false)
            ->assertJsonPath('data.cta.renew_available', false);
    }

    public function test_cta_for_active_starter_allows_upgrade_and_renew_not_downgrade(): void
    {
        FaceSubscription::factory()->starter()->active()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.cta.upgrade_available', true)
            ->assertJsonPath('data.cta.downgrade_available', false)
            ->assertJsonPath('data.cta.renew_available', true);
    }

    public function test_cta_for_active_pro_allows_all_three(): void
    {
        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.cta.upgrade_available', true)
            ->assertJsonPath('data.cta.downgrade_available', true)
            ->assertJsonPath('data.cta.renew_available', true);
    }

    public function test_cta_for_active_elite_allows_downgrade_and_renew_not_upgrade(): void
    {
        FaceSubscription::factory()->elite()->active()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.cta.upgrade_available', false)
            ->assertJsonPath('data.cta.downgrade_available', true)
            ->assertJsonPath('data.cta.renew_available', true);
    }

    public function test_cta_for_expired_face_allows_upgrade_and_renew_not_downgrade(): void
    {
        FaceSubscription::factory()->pro()->expired()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.cta.upgrade_available', true)
            ->assertJsonPath('data.cta.downgrade_available', false)
            ->assertJsonPath('data.cta.renew_available', true);
    }

    public function test_cta_is_all_false_during_pending_payment(): void
    {
        FaceSubscription::factory()->pro()->pendingPayment()->create([
            'face_id' => $this->face->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.cta.upgrade_available', false)
            ->assertJsonPath('data.cta.downgrade_available', false)
            ->assertJsonPath('data.cta.renew_available', false);
    }

    public function test_cta_is_all_false_during_pending_tier_change_with_active_subscription(): void
    {
        $faceId = $this->face->id;
        FaceSubscription::factory()->pro()->active()->create(['face_id' => $faceId]);
        FaceSubscription::factory()->elite()->pendingPayment()->create(['face_id' => $faceId]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk()
            ->assertJsonPath('data.current.status', 'active')
            ->assertJsonPath('data.current.tier', 'pro')
            ->assertJsonPath('data.current.plan', 'pro')
            ->assertJsonPath('data.cta.upgrade_available', false)
            ->assertJsonPath('data.cta.downgrade_available', false)
            ->assertJsonPath('data.cta.renew_available', false);
    }

    // ---------------------------------------------------------------------
    // Contract: leak guard, envelope, query budget (AC #10 / #11 / #16)
    // ---------------------------------------------------------------------

    public function test_response_never_leaks_provider_payload_fields(): void
    {
        FaceSubscription::factory()->pro()->pendingPayment()->create([
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

        $current = (array) $response->json('data.current');
        $this->assertArrayNotHasKey('provider', $current);
        $this->assertArrayNotHasKey('provider_reference', $current);
        $this->assertArrayNotHasKey('metadata', $current);
    }

    public function test_response_data_envelope_has_exactly_current_offers_cta(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/subscription-status');

        $response->assertOk();

        $this->assertSame(['data'], array_keys((array) $response->json()));
        $this->assertSame(['current', 'offers', 'cta'], array_keys((array) $response->json('data')));

        $this->assertSame(
            ['tier', 'plan', 'status', 'starts_at', 'expires_at', 'cancelled_at', 'capabilities'],
            array_keys((array) $response->json('data.current')),
        );
        $this->assertSame(
            ['tier', 'price', 'currency', 'capabilities'],
            array_keys((array) $response->json('data.offers.0')),
        );
        $this->assertSame(
            ['upgrade_available', 'downgrade_available', 'renew_available'],
            array_keys((array) $response->json('data.cta')),
        );
    }

    public function test_active_path_uses_eager_loaded_subscription_within_query_budget(): void
    {
        FaceSubscription::factory()->pro()->active()->create([
            'face_id' => $this->face->id,
        ]);

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

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * The capabilities matrix per tier, mirroring config/face_subscription_tiers.php.
     *
     * @return array<string, array<string, mixed>>
     */
    private function tierMatrices(): array
    {
        return [
            'free' => [
                'max_album_photos' => 1,
                'max_presentation_videos' => 0,
                'max_acting_videos' => 0,
                'max_ugc_videos' => 0,
                'ugc_access' => false,
                'commission_rate' => 0.1,
                'sort_priority' => 4,
                'has_elite_badge' => false,
            ],
            'starter' => [
                'max_album_photos' => 2,
                'max_presentation_videos' => 1,
                'max_acting_videos' => 0,
                'max_ugc_videos' => 0,
                'ugc_access' => true,
                'commission_rate' => 0.1,
                'sort_priority' => 3,
                'has_elite_badge' => false,
            ],
            'pro' => [
                'max_album_photos' => 4,
                'max_presentation_videos' => 1,
                'max_acting_videos' => 1,
                'max_ugc_videos' => 0,
                'ugc_access' => true,
                'commission_rate' => 0.1,
                'sort_priority' => 2,
                'has_elite_badge' => false,
            ],
            'elite' => [
                'max_album_photos' => 6,
                'max_presentation_videos' => 1,
                'max_acting_videos' => 2,
                'max_ugc_videos' => 1,
                'ugc_access' => true,
                'commission_rate' => 0.05,
                'sort_priority' => 1,
                'has_elite_badge' => true,
            ],
        ];
    }
}
