<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Face;
use App\Models\FaceSubscription;
use App\Services\FaceEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceEntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    private FaceEntitlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FaceEntitlementService;
    }

    public function test_face_without_subscription_gets_free_limits(): void
    {
        $face = Face::factory()->create();

        $this->assertSame(2, $this->service->albumUploadLimit($face));
        $this->assertSame(2, $this->service->publicAlbumPhotoLimit($face));
        $this->assertFalse($this->service->isPremium($face));
        $this->assertFalse($this->service->isFeaturedBySubscription($face));
    }

    public function test_face_with_pending_payment_subscription_gets_free_limits(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->pendingPayment()->create(['face_id' => $face->id]);

        $this->assertSame(2, $this->service->albumUploadLimit($face));
        $this->assertSame(2, $this->service->publicAlbumPhotoLimit($face));
        $this->assertFalse($this->service->isPremium($face));
        $this->assertFalse($this->service->isFeaturedBySubscription($face));
    }

    public function test_face_with_active_unexpired_annual_premium_gets_premium_limits(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $this->assertSame(4, $this->service->albumUploadLimit($face));
        $this->assertSame(4, $this->service->publicAlbumPhotoLimit($face));
        $this->assertTrue($this->service->isPremium($face));
        $this->assertTrue($this->service->isFeaturedBySubscription($face));
    }

    public function test_face_with_active_status_but_past_expiry_gets_free_limits(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertSame(2, $this->service->albumUploadLimit($face));
        $this->assertSame(2, $this->service->publicAlbumPhotoLimit($face));
        $this->assertFalse($this->service->isPremium($face));
        $this->assertFalse($this->service->isFeaturedBySubscription($face));
    }

    public function test_face_with_expired_subscription_gets_free_limits(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->expired()->create(['face_id' => $face->id]);

        $this->assertSame(2, $this->service->albumUploadLimit($face));
        $this->assertSame(2, $this->service->publicAlbumPhotoLimit($face));
        $this->assertFalse($this->service->isPremium($face));
        $this->assertFalse($this->service->isFeaturedBySubscription($face));
    }

    public function test_face_with_cancelled_subscription_gets_free_limits(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->cancelled()->create(['face_id' => $face->id]);

        $this->assertSame(2, $this->service->albumUploadLimit($face));
        $this->assertSame(2, $this->service->publicAlbumPhotoLimit($face));
        $this->assertFalse($this->service->isPremium($face));
        $this->assertFalse($this->service->isFeaturedBySubscription($face));
    }

    public function test_face_with_failed_subscription_gets_free_limits(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->failed()->create(['face_id' => $face->id]);

        $this->assertSame(2, $this->service->albumUploadLimit($face));
        $this->assertSame(2, $this->service->publicAlbumPhotoLimit($face));
        $this->assertFalse($this->service->isPremium($face));
        $this->assertFalse($this->service->isFeaturedBySubscription($face));
    }

    public function test_current_active_subscription_wins_over_stale_historical_rows(): void
    {
        $face = Face::factory()->create();

        // Historical churn: a few stale rows that must NOT grant premium on their own.
        FaceSubscription::factory()->expired()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->cancelled()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->failed()->create(['face_id' => $face->id]);

        // The current, valid subscription that should grant premium entitlements.
        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $this->assertTrue($this->service->isPremium($face));
        $this->assertSame(4, $this->service->albumUploadLimit($face));
        $this->assertSame(4, $this->service->publicAlbumPhotoLimit($face));
        $this->assertTrue($this->service->isFeaturedBySubscription($face));
    }

    public function test_stale_subscriptions_alone_do_not_grant_premium(): void
    {
        $face = Face::factory()->create();

        FaceSubscription::factory()->expired()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->cancelled()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->failed()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->pendingPayment()->create(['face_id' => $face->id]);

        $this->assertFalse($this->service->isPremium($face));
        $this->assertSame(2, $this->service->albumUploadLimit($face));
        $this->assertSame(2, $this->service->publicAlbumPhotoLimit($face));
        $this->assertFalse($this->service->isFeaturedBySubscription($face));
    }

    public function test_service_uses_preloaded_active_subscription_relation_when_available(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $faceWithRelation = Face::query()->with('activeSubscription')->find($face->id);

        $this->assertTrue($faceWithRelation->relationLoaded('activeSubscription'));
        $this->assertTrue($this->service->isPremium($faceWithRelation));
        $this->assertSame(4, $this->service->albumUploadLimit($faceWithRelation));
    }

    public function test_preloaded_active_subscription_ignores_non_annual_rows_with_later_expiry(): void
    {
        $face = Face::factory()->create();
        $annualSubscription = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => now()->addMonth(),
        ]);

        \DB::table('face_subscriptions')->insert([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'face_id' => $face->id,
            'plan' => 'legacy_non_annual',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addYear(),
            'cancelled_at' => null,
            'paid_amount' => 100000,
            'currency' => 'XOF',
            'provider' => null,
            'provider_reference' => null,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $faceWithRelation = Face::query()->with('activeSubscription')->find($face->id);

        $this->assertTrue($faceWithRelation->relationLoaded('activeSubscription'));
        $this->assertSame($annualSubscription->id, $faceWithRelation->activeSubscription->id);
        $this->assertTrue($this->service->isPremium($faceWithRelation));
    }

    public function test_preloaded_active_subscription_is_null_when_only_non_annual_rows_exist(): void
    {
        $face = Face::factory()->create();

        \DB::table('face_subscriptions')->insert([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'face_id' => $face->id,
            'plan' => 'legacy_non_annual',
            'status' => 'active',
            'starts_at' => now()->subDays(7),
            'expires_at' => now()->addYear(),
            'cancelled_at' => null,
            'paid_amount' => 100000,
            'currency' => 'XOF',
            'provider' => null,
            'provider_reference' => null,
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $faceWithRelation = Face::query()->with('activeSubscription')->find($face->id);

        $this->assertTrue($faceWithRelation->relationLoaded('activeSubscription'));
        $this->assertNull($faceWithRelation->activeSubscription);
        $this->assertFalse($this->service->isPremium($faceWithRelation));
        $this->assertSame(2, $this->service->albumUploadLimit($faceWithRelation));
        $this->assertSame(2, $this->service->publicAlbumPhotoLimit($faceWithRelation));
        $this->assertFalse($this->service->isFeaturedBySubscription($faceWithRelation));
    }
}
