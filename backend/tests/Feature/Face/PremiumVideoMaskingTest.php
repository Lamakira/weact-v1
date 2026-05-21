<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceSubscriptionStatus;
use App\Enums\FaceVideoType;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\FaceVideo;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PremiumVideoMaskingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Seed a Face at the Élite media ceiling — 1 presentation video (scalar
     * column) + 2 acting + 1 UGC (face_videos rows) — so one fixture exercises
     * every tier's masking. $tier ∈ null (Free) | starter | pro | elite.
     *
     * @return array{face: Face, user: User}
     */
    private function makeFaceWithFullMedia(?string $tier = null): array
    {
        $face = Face::factory()->create([
            'presentation_video' => 'presentation.mp4',
            'presentation_video_thumbnail' => 'presentation-thumb.jpg',
        ]);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        FaceVideo::factory()->createSequentialForFace($face, FaceVideoType::Acting, 2);
        FaceVideo::factory()->createSequentialForFace($face, FaceVideoType::Ugc, 1);

        if ($tier !== null) {
            FaceSubscription::factory()->{$tier}()->active()->create(['face_id' => $face->id]);
        }

        return ['face' => $face, 'user' => $user];
    }

    private function actingProducer(): User
    {
        $producer = Producer::factory()->create();

        return User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
    }

    private function adminToken(): string
    {
        return Admin::factory()->create()->createToken('admin-token')->plainTextToken;
    }

    // === Public lens ===

    public function test_public_lens_free_face_has_no_videos_and_masks_presentation(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia();

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(0, 'data.videos')
            ->assertJsonPath('data.presentation_video_url', null)
            ->assertJsonPath('data.has_presentation_video', false);
    }

    public function test_public_lens_starter_face_has_no_portfolio_videos_but_keeps_presentation(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia('starter');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonCount(0, 'data.videos')
            ->assertJsonPath('data.has_presentation_video', true);
        $this->assertNotNull($response->json('data.presentation_video_url'));
    }

    public function test_public_lens_pro_face_exposes_one_acting_video_only(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia('pro');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()->assertJsonCount(1, 'data.videos');
        $this->assertSame('acting', $response->json('data.videos.0.type'));
        $this->assertSame(1, $response->json('data.videos.0.position'));
    }

    public function test_public_lens_elite_face_exposes_all_three_videos(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia('elite');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()->assertJsonCount(3, 'data.videos');
    }

    public function test_public_lens_videos_omit_lock_fields(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia('elite');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk();
        foreach ($response->json('data.videos') as $video) {
            $this->assertArrayHasKey('type', $video);
            $this->assertArrayHasKey('video_url', $video);
            $this->assertArrayNotHasKey('is_locked', $video);
            $this->assertArrayNotHasKey('lock_reason', $video);
        }
    }

    // === Producer lens ===

    public function test_producer_lens_free_face_has_no_videos(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia();

        $response = $this->actingAs($this->actingProducer())
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()
            ->assertJsonCount(0, 'data.videos')
            ->assertJsonPath('data.presentation_video_url', null);
    }

    public function test_producer_lens_pro_face_exposes_one_acting_video(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia('pro');

        $response = $this->actingAs($this->actingProducer())
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()->assertJsonCount(1, 'data.videos');
    }

    public function test_producer_lens_elite_face_exposes_all_three_videos(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia('elite');

        $response = $this->actingAs($this->actingProducer())
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()->assertJsonCount(3, 'data.videos');
    }

    public function test_producer_lens_videos_omit_lock_fields(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia('elite');

        $response = $this->actingAs($this->actingProducer())
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk();
        foreach ($response->json('data.videos') as $video) {
            $this->assertArrayNotHasKey('is_locked', $video);
            $this->assertArrayNotHasKey('lock_reason', $video);
        }
    }

    public function test_producer_lens_starter_face_keeps_presentation_video(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia('starter');

        $response = $this->actingAs($this->actingProducer())
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()->assertJsonCount(0, 'data.videos');
        $this->assertNotNull($response->json('data.presentation_video_url'));
    }

    // === Owner lens ===

    public function test_owner_lens_free_face_sees_all_videos_locked_tier_below_required(): void
    {
        ['user' => $user] = $this->makeFaceWithFullMedia();

        $response = $this->actingAs($user)->getJson('/api/v1/face/profile');

        $response->assertOk()->assertJsonCount(3, 'data.videos');
        foreach ($response->json('data.videos') as $video) {
            $this->assertTrue($video['is_locked']);
            $this->assertSame('tier_below_required', $video['lock_reason']);
        }
    }

    public function test_owner_lens_free_face_presentation_video_is_locked(): void
    {
        ['user' => $user] = $this->makeFaceWithFullMedia();

        $response = $this->actingAs($user)->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonPath('data.is_presentation_video_locked', true)
            ->assertJsonPath('data.presentation_video_lock_reason', 'tier_below_required');
    }

    public function test_owner_lens_starter_face_sees_portfolio_videos_locked_but_presentation_unlocked(): void
    {
        ['user' => $user] = $this->makeFaceWithFullMedia('starter');

        $response = $this->actingAs($user)->getJson('/api/v1/face/profile');

        $response->assertOk()->assertJsonCount(3, 'data.videos');
        foreach ($response->json('data.videos') as $video) {
            $this->assertTrue($video['is_locked']);
            $this->assertSame('tier_below_required', $video['lock_reason']);
        }
        $response->assertJsonPath('data.is_presentation_video_locked', false);
    }

    public function test_owner_lens_pro_face_acting_quota_exceeded_and_ugc_tier_below_required(): void
    {
        ['user' => $user] = $this->makeFaceWithFullMedia('pro');

        $response = $this->actingAs($user)->getJson('/api/v1/face/profile');

        $response->assertOk();
        $videos = collect($response->json('data.videos'));

        $acting1 = $videos->first(fn ($v) => $v['type'] === 'acting' && $v['position'] === 1);
        $acting2 = $videos->first(fn ($v) => $v['type'] === 'acting' && $v['position'] === 2);
        $ugc1 = $videos->first(fn ($v) => $v['type'] === 'ugc' && $v['position'] === 1);

        $this->assertFalse($acting1['is_locked']);
        $this->assertNull($acting1['lock_reason']);
        $this->assertTrue($acting2['is_locked']);
        $this->assertSame('quota_exceeded', $acting2['lock_reason']);
        $this->assertTrue($ugc1['is_locked']);
        $this->assertSame('tier_below_required', $ugc1['lock_reason']);
    }

    public function test_owner_lens_pro_face_presentation_video_is_not_locked(): void
    {
        ['user' => $user] = $this->makeFaceWithFullMedia('pro');

        $response = $this->actingAs($user)->getJson('/api/v1/face/profile');

        $response->assertOk()
            ->assertJsonPath('data.is_presentation_video_locked', false)
            ->assertJsonPath('data.presentation_video_lock_reason', null);
    }

    public function test_owner_lens_elite_face_sees_all_videos_unlocked(): void
    {
        ['user' => $user] = $this->makeFaceWithFullMedia('elite');

        $response = $this->actingAs($user)->getJson('/api/v1/face/profile');

        $response->assertOk()->assertJsonCount(3, 'data.videos');
        foreach ($response->json('data.videos') as $video) {
            $this->assertFalse($video['is_locked']);
            $this->assertNull($video['lock_reason']);
        }
        $response->assertJsonPath('data.is_presentation_video_locked', false);
    }

    public function test_owner_lens_video_items_carry_lock_metadata(): void
    {
        ['user' => $user] = $this->makeFaceWithFullMedia('pro');

        $response = $this->actingAs($user)->getJson('/api/v1/face/profile');

        $response->assertOk();
        foreach ($response->json('data.videos') as $video) {
            $this->assertArrayHasKey('id', $video);
            $this->assertArrayHasKey('type', $video);
            $this->assertArrayHasKey('video_url', $video);
            $this->assertArrayHasKey('thumbnail_url', $video);
            $this->assertArrayHasKey('position', $video);
            $this->assertArrayHasKey('is_locked', $video);
            $this->assertArrayHasKey('lock_reason', $video);
        }
    }

    // === Admin lens ===

    public function test_admin_lens_free_face_sees_all_videos_locked(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia();

        $response = $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()->assertJsonCount(3, 'data.videos');
        foreach ($response->json('data.videos') as $video) {
            $this->assertTrue($video['is_locked']);
            $this->assertSame('tier_below_required', $video['lock_reason']);
        }
    }

    public function test_admin_lens_pro_face_acting_quota_exceeded(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia('pro');

        $response = $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk();
        $videos = collect($response->json('data.videos'));
        $acting2 = $videos->first(fn ($v) => $v['type'] === 'acting' && $v['position'] === 2);

        $this->assertTrue($acting2['is_locked']);
        $this->assertSame('quota_exceeded', $acting2['lock_reason']);
    }

    public function test_admin_lens_elite_face_sees_all_videos_unlocked(): void
    {
        ['face' => $face] = $this->makeFaceWithFullMedia('elite');

        $response = $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk()->assertJsonCount(3, 'data.videos');
        foreach ($response->json('data.videos') as $video) {
            $this->assertFalse($video['is_locked']);
        }
    }

    // === lock_reason semantics + downgrade preservation ===

    public function test_lock_reason_distinguishes_quota_exceeded_from_tier_below_required(): void
    {
        // Pro: maxActingVideos = 1 (positive → over-quota acting = quota_exceeded);
        // maxUgcVideos = 0 (zero → any UGC = tier_below_required).
        ['user' => $user] = $this->makeFaceWithFullMedia('pro');

        $response = $this->actingAs($user)->getJson('/api/v1/face/profile');

        $reasons = collect($response->json('data.videos'))
            ->filter(fn ($v) => $v['is_locked'])
            ->pluck('lock_reason')
            ->all();

        $this->assertContains('quota_exceeded', $reasons);
        $this->assertContains('tier_below_required', $reasons);
    }

    public function test_downgrade_from_elite_to_free_preserves_video_rows_and_files(): void
    {
        ['face' => $face, 'user' => $user] = $this->makeFaceWithFullMedia('elite');

        foreach ($face->videos as $video) {
            Storage::disk('public')->put('videos/faces/'.$video->type->value.'/'.$video->filename, 'content');
            Storage::disk('public')->put('videos/faces/'.$video->type->value.'/thumbnails/'.$video->thumbnail, 'thumb');
        }

        // Expire the subscription → the Face drops to the implicit Free tier.
        FaceSubscription::where('face_id', $face->id)->update([
            'status' => FaceSubscriptionStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonCount(0, 'data.videos');

        $this->actingAs($user)->getJson('/api/v1/face/profile')
            ->assertOk()
            ->assertJsonCount(3, 'data.videos');

        $this->assertSame(3, FaceVideo::where('face_id', $face->id)->count());
        foreach ($face->videos as $video) {
            Storage::disk('public')->assertExists('videos/faces/'.$video->type->value.'/'.$video->filename);
        }
    }
}
