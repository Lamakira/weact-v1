<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Enums\CandidatureStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaceNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    private Face $face;

    private User $producerUser;

    private Producer $producer;

    private Mission $mission;

    private Candidature $candidature;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Face with User
        $this->face = Face::factory()->create([
            'nom' => 'Dupont',
            'prenom' => 'Marie',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        // Create a Producer with User
        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        // Create a published mission owned by the Producer
        $this->mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
            'titre' => 'Tournage pub cosmétiques',
        ]);

        // Create a pending candidature
        $this->candidature = Candidature::factory()->create([
            'mission_id' => $this->mission->id,
            'face_id' => $this->face->id,
            'status' => CandidatureStatus::Pending,
            'message_motivation' => 'Je suis très motivée pour cette mission.',
        ]);
    }

    // ========================================
    // Test notification created on accept (AC#1)
    // ========================================

    public function test_notification_created_when_producer_accepts_candidature(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->uuid}/accept");

        $response->assertOk();

        // Verify notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
        ]);

        // Verify notification data
        $notification = Notification::where('user_id', $this->faceUser->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals('candidature_accepted', $notification->type);
        $this->assertEquals('Tournage pub cosmétiques', $notification->data['mission_title']);
        $this->assertEquals($this->candidature->id, $notification->data['candidature_id']);
        $this->assertEquals('Votre candidature a été acceptée', $notification->data['message']);
    }

    // ========================================
    // Test notification created on reject (AC#2)
    // ========================================

    public function test_notification_created_when_producer_rejects_candidature(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/producer/candidatures/{$this->candidature->uuid}/reject");

        $response->assertOk();

        // Verify notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_rejected',
        ]);

        // Verify notification data
        $notification = Notification::where('user_id', $this->faceUser->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals('candidature_rejected', $notification->type);
        $this->assertEquals('Tournage pub cosmétiques', $notification->data['mission_title']);
        $this->assertEquals($this->candidature->id, $notification->data['candidature_id']);
        $this->assertEquals('Votre candidature a été refusée', $notification->data['message']);
    }

    // ========================================
    // Test list notifications (AC#3)
    // ========================================

    public function test_face_can_list_notifications(): void
    {
        // Create some notifications
        Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'Mission 1',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été acceptée',
            ],
        ]);
        Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_rejected',
            'data' => [
                'mission_title' => 'Mission 2',
                'candidature_id' => 2,
                'message' => 'Votre candidature a été refusée',
            ],
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'data' => [
                            'mission_title',
                            'candidature_id',
                            'message',
                        ],
                        'read_at',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_notifications_are_paginated(): void
    {
        // Create 20 notifications
        for ($i = 1; $i <= 20; $i++) {
            Notification::create([
                'user_id' => $this->faceUser->id,
                'type' => 'candidature_accepted',
                'data' => [
                    'mission_title' => "Mission {$i}",
                    'candidature_id' => $i,
                    'message' => 'Votre candidature a été acceptée',
                ],
            ]);
        }

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/notifications');

        $response->assertOk()
            ->assertJsonPath('meta.total', 20)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonCount(15, 'data');
    }

    public function test_notifications_are_ordered_by_most_recent_first(): void
    {
        // Create older notification using query builder to set custom created_at
        $olderId = \Illuminate\Support\Facades\DB::table('notifications')->insertGetId([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_rejected',
            'data' => json_encode([
                'mission_title' => 'Older Mission',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été refusée',
            ]),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        // Create newer notification
        $newerId = \Illuminate\Support\Facades\DB::table('notifications')->insertGetId([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => json_encode([
                'mission_title' => 'Newer Mission',
                'candidature_id' => 2,
                'message' => 'Votre candidature a été acceptée',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/notifications');

        $response->assertOk();
        $data = $response->json('data');
        // Check by mission title - most recent should be first
        $this->assertEquals('Newer Mission', $data[0]['data']['mission_title']);
        $this->assertEquals('Older Mission', $data[1]['data']['mission_title']);
    }

    // ========================================
    // Test mark as read (AC#4)
    // ========================================

    public function test_face_can_mark_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'Mission Test',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été acceptée',
            ],
        ]);

        $this->assertNull($notification->read_at);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/notifications/{$notification->uuid}/read");

        $response->assertOk()
            ->assertJsonPath('message', 'Notification marquée comme lue')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'data',
                    'read_at',
                    'created_at',
                ],
            ]);

        // Verify read_at was set
        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_marking_already_read_notification_is_idempotent(): void
    {
        $notification = Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'Mission Test',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été acceptée',
            ],
            'read_at' => now()->subHour(),
        ]);

        $originalReadAt = $notification->read_at;

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/notifications/{$notification->uuid}/read");

        $response->assertOk();

        // Verify read_at was not changed
        $notification->refresh();
        $this->assertEquals($originalReadAt->timestamp, $notification->read_at->timestamp);
    }

    public function test_face_can_mark_all_notifications_as_read(): void
    {
        // Create unread notifications
        Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'Mission 1',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été acceptée',
            ],
        ]);
        Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_rejected',
            'data' => [
                'mission_title' => 'Mission 2',
                'candidature_id' => 2,
                'message' => 'Votre candidature a été refusée',
            ],
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('message', 'Toutes les notifications marquées comme lues');

        // Verify all notifications are read
        $unreadCount = Notification::where('user_id', $this->faceUser->id)
            ->unread()
            ->count();
        $this->assertEquals(0, $unreadCount);
    }

    // ========================================
    // Test unread count (AC#5)
    // ========================================

    public function test_face_can_get_unread_notification_count(): void
    {
        // Create mix of read and unread notifications
        Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'Mission 1',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été acceptée',
            ],
        ]); // unread

        Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_rejected',
            'data' => [
                'mission_title' => 'Mission 2',
                'candidature_id' => 2,
                'message' => 'Votre candidature a été refusée',
            ],
        ]); // unread

        Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'Mission 3',
                'candidature_id' => 3,
                'message' => 'Votre candidature a été acceptée',
            ],
            'read_at' => now(),
        ]); // read

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/notifications/unread-count');

        $response->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_unread_count_is_zero_when_all_read(): void
    {
        Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'Mission 1',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été acceptée',
            ],
            'read_at' => now(),
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/notifications/unread-count');

        $response->assertOk()
            ->assertJson(['count' => 0]);
    }

    public function test_unread_count_is_zero_when_no_notifications(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/notifications/unread-count');

        $response->assertOk()
            ->assertJson(['count' => 0]);
    }

    // ========================================
    // Test Producer cannot access Face notifications (AC#6)
    // ========================================

    public function test_producer_cannot_list_notifications(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/face/notifications');

        $response->assertForbidden();
    }

    public function test_producer_cannot_get_unread_count(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->getJson('/api/v1/face/notifications/unread-count');

        $response->assertForbidden();
    }

    public function test_producer_cannot_mark_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'Mission Test',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été acceptée',
            ],
        ]);

        $response = $this->actingAs($this->producerUser)
            ->postJson("/api/v1/face/notifications/{$notification->uuid}/read");

        $response->assertForbidden();
    }

    public function test_producer_cannot_mark_all_as_read(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/face/notifications/read-all');

        $response->assertForbidden();
    }

    // ========================================
    // Test unauthenticated access (AC#7)
    // ========================================

    public function test_unauthenticated_cannot_list_notifications(): void
    {
        $response = $this->getJson('/api/v1/face/notifications');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_get_unread_count(): void
    {
        $response = $this->getJson('/api/v1/face/notifications/unread-count');

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_mark_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'Mission Test',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été acceptée',
            ],
        ]);

        $response = $this->postJson("/api/v1/face/notifications/{$notification->uuid}/read");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_mark_all_as_read(): void
    {
        $response = $this->postJson('/api/v1/face/notifications/read-all');

        $response->assertUnauthorized();
    }

    // ========================================
    // Test notification ownership
    // ========================================

    public function test_face_cannot_mark_other_face_notification_as_read(): void
    {
        // Create another face
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        // Create notification for other face
        $notification = Notification::create([
            'user_id' => $otherFaceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'Mission Test',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été acceptée',
            ],
        ]);

        $response = $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/notifications/{$notification->uuid}/read");

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Cette notification ne vous appartient pas',
            ]);
    }

    public function test_face_only_sees_own_notifications(): void
    {
        // Create another face
        $otherFace = Face::factory()->create();
        $otherFaceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $otherFace->id,
        ]);

        // Create notification for current face
        Notification::create([
            'user_id' => $this->faceUser->id,
            'type' => 'candidature_accepted',
            'data' => [
                'mission_title' => 'My Mission',
                'candidature_id' => 1,
                'message' => 'Votre candidature a été acceptée',
            ],
        ]);

        // Create notification for other face
        Notification::create([
            'user_id' => $otherFaceUser->id,
            'type' => 'candidature_rejected',
            'data' => [
                'mission_title' => 'Other Mission',
                'candidature_id' => 2,
                'message' => 'Votre candidature a été refusée',
            ],
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/notifications');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.data.mission_title', 'My Mission');
    }

    // ========================================
    // Test 404 for non-existent notification
    // ========================================

    public function test_returns_404_for_non_existent_notification(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/notifications/00000000-0000-0000-0000-000000000000/read');

        $response->assertNotFound();
    }
}
