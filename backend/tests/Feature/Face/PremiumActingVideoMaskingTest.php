<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Models\Admin;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PremiumActingVideoMaskingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @return array{face: Face, user: User}
     */
    private function makeFaceWithActingVideo(string $filename = 'foo.mp4', ?string $subscriptionState = null): array
    {
        $face = Face::factory()->create([
            'acting_video' => $filename,
            'acting_video_thumbnail' => 'foo-thumb.jpg',
        ]);
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        if ($subscriptionState === 'active') {
            FaceSubscription::factory()->active()->create(['face_id' => $face->id]);
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

    public function test_public_response_masks_acting_video_for_free_face(): void
    {
        ['face' => $face] = $this->makeFaceWithActingVideo('foo.mp4');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonPath('data.acting_video_url', null)
            ->assertJsonPath('data.has_acting_video', false);
    }

    public function test_public_response_exposes_acting_video_for_premium_face(): void
    {
        ['face' => $face] = $this->makeFaceWithActingVideo('foo.mp4', 'active');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");

        $response->assertOk()
            ->assertJsonPath('data.has_acting_video', true);

        $this->assertStringContainsString('foo.mp4', $response->json('data.acting_video_url'));
    }

    public function test_producer_response_masks_acting_video_for_free_face(): void
    {
        ['face' => $face] = $this->makeFaceWithActingVideo('foo.mp4');
        $producerUser = $this->actingProducer();

        $response = $this->actingAs($producerUser)
            ->getJson("/api/v1/producer/candidates/{$face->uuid}");

        $response->assertOk()
            ->assertJsonPath('data.acting_video_url', null)
            ->assertJsonPath('data.acting_video_thumbnail_url', null);
    }

    public function test_owner_response_keeps_acting_video_url_visible_with_locked_flag(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFaceWithActingVideo('foo.mp4');

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk();
        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertStringContainsString('foo.mp4', $response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_owner_response_keeps_acting_video_url_visible_and_unlocked_when_premium(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFaceWithActingVideo('foo.mp4', 'active');

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/profile');

        $response->assertOk();
        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertTrue($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_admin_response_keeps_acting_video_url_visible_with_lock_flag_reflecting_state(): void
    {
        ['face' => $face] = $this->makeFaceWithActingVideo('foo.mp4');
        $adminToken = $this->adminToken();

        $response = $this->withToken($adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk();
        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertFalse($response->json('data.is_acting_video_publicly_visible'));

        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $response = $this->withToken($adminToken)
            ->getJson("/api/v1/admin/faces/{$face->uuid}");

        $response->assertOk();
        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertTrue($response->json('data.is_acting_video_publicly_visible'));
    }

    public function test_owner_acting_video_show_endpoint_returns_url_regardless_of_subscription(): void
    {
        ['face' => $face, 'user' => $faceUser] = $this->makeFaceWithActingVideo('foo.mp4');

        $response = $this->actingAs($faceUser)
            ->getJson('/api/v1/face/acting-video');

        $response->assertOk();
        $this->assertNotNull($response->json('data.acting_video_url'));
        $this->assertStringContainsString('foo.mp4', $response->json('data.acting_video_url'));
    }

    public function test_free_face_with_existing_acting_video_becomes_public_after_subscribing(): void
    {
        ['face' => $face] = $this->makeFaceWithActingVideo('legacy.mp4');

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");
        $response->assertOk()
            ->assertJsonPath('data.acting_video_url', null);

        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $response = $this->getJson("/api/v1/public/faces/{$face->username}");
        $response->assertOk();
        $this->assertStringContainsString('legacy.mp4', $response->json('data.acting_video_url'));
    }
}
