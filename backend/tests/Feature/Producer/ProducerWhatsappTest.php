<?php

declare(strict_types=1);

namespace Tests\Feature\Producer;

use App\Enums\MissionStatus;
use App\Enums\ProducerType;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Producer WhatsApp number: owner-editable, visible to the owner and admins only
 * (never to Faces or the public — it would enable off-platform deals).
 */
class ProducerWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private const NUMBER = '+229 01 97 12 34 56';

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create([
            'type' => ProducerType::Particulier,
            'agency_name' => null,
            'first_name' => 'Marie',
            'last_name' => 'Martin',
        ]);
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    // ========== Update ==========

    public function test_new_producer_has_no_whatsapp_number(): void
    {
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/basic-info')
            ->assertOk()
            ->assertJsonPath('data.whatsapp_number', null);
    }

    public function test_particulier_can_save_whatsapp_number(): void
    {
        $this->actingAs($this->producerUser)
            ->putJson('/api/v1/producer/basic-info', [
                'first_name' => 'Marie',
                'whatsapp_number' => self::NUMBER,
            ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_number', self::NUMBER);

        $this->assertSame(self::NUMBER, $this->producer->fresh()->whatsapp_number);
    }

    public function test_agency_can_save_whatsapp_number(): void
    {
        $agency = Producer::factory()->create([
            'type' => ProducerType::Agency,
            'agency_name' => 'Production ABC',
            'first_name' => null,
            'last_name' => null,
        ]);
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $agency->id,
        ]);

        $this->actingAs($user)
            ->putJson('/api/v1/producer/basic-info', ['whatsapp_number' => self::NUMBER])
            ->assertOk()
            ->assertJsonPath('data.type', 'agency')
            ->assertJsonPath('data.whatsapp_number', self::NUMBER);
    }

    public function test_whatsapp_number_can_be_cleared(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);

        $this->actingAs($this->producerUser)
            ->putJson('/api/v1/producer/basic-info', ['whatsapp_number' => null])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_number', null);

        $this->assertNull($this->producer->fresh()->whatsapp_number);
    }

    public function test_updating_the_name_without_the_key_keeps_the_number(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);

        $this->actingAs($this->producerUser)
            ->putJson('/api/v1/producer/basic-info', ['first_name' => 'Julie'])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_number', self::NUMBER);

        $this->assertSame(self::NUMBER, $this->producer->fresh()->whatsapp_number);
    }

    public function test_whatsapp_number_longer_than_30_characters_is_rejected(): void
    {
        $this->actingAs($this->producerUser)
            ->putJson('/api/v1/producer/basic-info', [
                'first_name' => 'Marie',
                'whatsapp_number' => str_repeat('1', 31),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('whatsapp_number');

        $this->assertNull($this->producer->fresh()->whatsapp_number);
    }

    // ========== Exposure ==========

    public function test_owner_sees_their_number_in_the_user_payload(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/producer/profile')
            ->assertOk()
            ->assertJsonPath('data.whatsapp_number', self::NUMBER);
    }

    public function test_face_fetching_a_booking_does_not_get_the_producer_number(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);

        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk();

        $this->assertNotNull($response->json('data.producer.userable.display_name'));
        // The Face's own payload legitimately carries a (null) whatsapp_number key:
        // only the Producer side must be free of it.
        $this->assertArrayNotHasKey('whatsapp_number', $response->json('data.producer.userable'));
        $this->assertArrayNotHasKey('has_whatsapp', $response->json('data.producer.userable'));
        $this->assertStringNotContainsString('97 12 34 56', $response->getContent());
    }

    public function test_the_producer_sees_their_own_number_in_a_booking(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);

        $booking = Booking::factory()->pending()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
        ]);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/bookings/{$booking->uuid}")
            ->assertOk()
            ->assertJsonPath('data.producer.userable.whatsapp_number', self::NUMBER);
    }

    public function test_face_fetching_a_mission_does_not_get_the_producer_number(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);

        $mission = Mission::factory()->create([
            'producer_id' => $this->producer->id,
            'status' => MissionStatus::Published,
        ]);

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk();

        $this->assertNotNull($response->json('data.producer'));
        $this->assertStringNotContainsString('whatsapp', $response->getContent());
        $this->assertStringNotContainsString('97 12 34 56', $response->getContent());
    }

    public function test_public_producer_profile_does_not_expose_the_number(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);

        $response = $this->getJson("/api/v1/public/producers/{$this->producer->slug}")
            ->assertOk();

        $this->assertStringNotContainsString('whatsapp', $response->getContent());
        $this->assertStringNotContainsString('97 12 34 56', $response->getContent());
    }

    public function test_raw_model_serialization_hides_the_number(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);

        $this->assertArrayNotHasKey('whatsapp_number', $this->producer->fresh()->toArray());
    }

    public function test_admin_sees_the_number_in_list_and_detail(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);
        $token = Admin::factory()->create()->createToken('admin-token')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/admin/producers/{$this->producer->uuid}")
            ->assertOk()
            ->assertJsonPath('data.whatsapp_number', self::NUMBER)
            ->assertJsonPath('data.has_whatsapp', true);

        $this->withToken($token)
            ->getJson('/api/v1/admin/producers')
            ->assertOk()
            ->assertJsonPath('data.0.whatsapp_number', self::NUMBER);
    }

    public function test_admin_detail_reports_no_whatsapp_when_missing(): void
    {
        $token = Admin::factory()->create()->createToken('admin-token')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/admin/producers/{$this->producer->uuid}")
            ->assertOk()
            ->assertJsonPath('data.whatsapp_number', null)
            ->assertJsonPath('data.has_whatsapp', false);
    }

    // ========== GDPR ==========

    public function test_data_export_includes_the_number(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);

        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/user/data-export')
            ->assertOk()
            ->assertJsonPath('data.profile.type', 'Producer')
            ->assertJsonPath('data.profile.whatsapp_number', self::NUMBER);
    }

    public function test_account_deletion_nulls_the_number(): void
    {
        $this->producer->update(['whatsapp_number' => self::NUMBER]);

        $this->actingAs($this->producerUser)
            ->deleteJson('/api/v1/user/account', ['password' => 'password'])
            ->assertOk();

        $this->assertNull($this->producer->fresh()->whatsapp_number);
    }
}
