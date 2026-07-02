<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Mail\BookingReceivedMail;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\ValueObjects\BookingPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CreateBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private Producer $producer;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);

        $this->face = Face::factory()->create([
            'tarif_horaire' => 5000,
            'tarif_journalier' => 30000,
            'is_available' => true,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    private function getValidBookingData(): array
    {
        return [
            'face_id' => $this->face->uuid,
            'date_debut' => now()->addWeek()->toDateString(),
            'date_fin' => now()->addWeeks(2)->toDateString(),
            'duree_heures' => 8,
            'type_contenu' => 'Publicité',
            'lieu' => 'Cotonou',
            'message' => 'Bonjour, je souhaite vous réserver pour une publicité.',
        ];
    }

    public function test_producer_can_create_booking_request(): void
    {
        Event::fake([BookingCreated::class]);

        $data = $this->getValidBookingData();

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertCreated()
            ->assertJsonPath('data.face_id', $this->faceUser->id)
            ->assertJsonPath('data.producer_id', $this->producerUser->id)
            ->assertJsonPath('data.status', BookingStatus::Pending->value)
            ->assertJsonPath('data.duree_heures', 8)
            ->assertJsonPath('data.type_contenu', 'Publicité')
            ->assertJsonPath('data.lieu', 'Cotonou')
            ->assertJsonPath('message', 'Demande de booking envoyee');

        $this->assertDatabaseHas('bookings', [
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => BookingStatus::Pending->value,
            'lieu' => 'Cotonou',
        ]);

        Event::assertDispatched(BookingCreated::class);
    }

    public function test_create_booking_dispatches_event_and_queues_email_to_face(): void
    {
        // FIX-24.5: end-to-end integration test — let listeners actually execute.
        // PAS de Event::fake (les 13 autres tests fakent l'event pour isoler la logique
        // de création ; ce test-ci vérifie que le listener email est bien câblé).
        Mail::fake();

        $data = $this->getValidBookingData();

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertCreated();

        // FIX-24.5 SendBookingReceivedEmail listener queues the mail to the Face user.
        Mail::assertQueued(
            BookingReceivedMail::class,
            fn (BookingReceivedMail $mail): bool => $mail->hasTo($this->faceUser->email)
                && $mail->face->is($this->face)
                && $mail->producer->is($this->producer),
        );

        // Non-régression : NotifyFaceOnBookingReceived listener still creates the in-app notification.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->faceUser->id,
            'type' => 'booking_received',
        ]);
    }

    public function test_booking_pricing_is_calculated_correctly(): void
    {
        Event::fake([BookingCreated::class]);

        $data = $this->getValidBookingData();
        $data['duree_heures'] = 8;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertCreated();

        // 8h >= 8h → uses daily rate: 1 day * 30000 = 30000 tarif_base
        // FP-3.1a: the setUp Face has no subscription → Découverte (free) tier → 15 % Face commission.
        $expectedPricing = new BookingPricing(30000, 0.15);

        $response->assertJsonPath('data.tarif_base', $expectedPricing->baseTarif);
        $response->assertJsonPath('data.montant_total_producteur', $expectedPricing->totalProducerPays);
        $response->assertJsonPath('data.montant_face_recoit', $expectedPricing->faceReceives);
    }

    public function test_booking_uses_hourly_rate_for_short_durations(): void
    {
        Event::fake([BookingCreated::class]);

        $data = $this->getValidBookingData();
        $data['duree_heures'] = 4;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertCreated();

        // 4h → tarif_journalier/8 × 4 = 30000/8 × 4 = 15000 tarif_base
        // FP-3.1a: the setUp Face has no subscription → Découverte (free) tier → 15 % Face commission.
        $expectedPricing = new BookingPricing(15000, 0.15);

        $response->assertJsonPath('data.tarif_base', $expectedPricing->baseTarif);
        $response->assertJsonPath('data.montant_total_producteur', $expectedPricing->totalProducerPays);
        $response->assertJsonPath('data.montant_face_recoit', $expectedPricing->faceReceives);
    }

    public function test_face_cannot_create_booking(): void
    {
        $response = $this->actingAs($this->faceUser)
            ->postJson('/api/v1/bookings', $this->getValidBookingData());

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_create_booking(): void
    {
        $response = $this->postJson('/api/v1/bookings', $this->getValidBookingData());

        $response->assertUnauthorized();
    }

    public function test_booking_fails_when_face_is_unavailable(): void
    {
        $this->face->update(['is_available' => false]);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->getValidBookingData());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('face_id');
    }

    public function test_booking_fails_with_invalid_duration(): void
    {
        $data = $this->getValidBookingData();
        $data['duree_heures'] = 2; // Min is 4

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('duree_heures');
    }

    public function test_booking_fails_when_target_is_not_a_face(): void
    {
        $otherProducer = Producer::factory()->create();
        $otherProducerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $otherProducer->id,
        ]);

        $data = $this->getValidBookingData();
        $data['face_id'] = $otherProducer->uuid;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('face_id');
    }

    public function test_booking_validation_requires_all_mandatory_fields(): void
    {
        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'face_id',
                'date_debut',
                'date_fin',
                'duree_heures',
                'type_contenu',
                'lieu',
            ]);
    }

    public function test_booking_date_debut_must_be_in_the_future(): void
    {
        $data = $this->getValidBookingData();
        $data['date_debut'] = now()->subDay()->toDateString();

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('date_debut');
    }

    public function test_booking_date_fin_must_be_after_date_debut(): void
    {
        $data = $this->getValidBookingData();
        $data['date_debut'] = now()->addWeeks(2)->toDateString();
        $data['date_fin'] = now()->addWeek()->toDateString(); // Before date_debut

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('date_fin');
    }

    public function test_booking_allows_same_day_start_and_end_dates(): void
    {
        Event::fake([BookingCreated::class]);

        $data = $this->getValidBookingData();
        $sameDay = now()->addWeek()->toDateString();
        $data['date_debut'] = $sameDay;
        $data['date_fin'] = $sameDay;
        $data['duree_heures'] = 4;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertCreated();
    }

    public function test_booking_fails_when_duration_exceeds_maximum(): void
    {
        $data = $this->getValidBookingData();
        $data['duree_heures'] = 721;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('duree_heures');
    }

    public function test_booking_fails_when_duration_exceeds_selected_date_range_capacity(): void
    {
        $data = $this->getValidBookingData();
        $sameDay = now()->addWeek()->toDateString();
        $data['date_debut'] = $sameDay;
        $data['date_fin'] = $sameDay;
        $data['duree_heures'] = 12;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('duree_heures');
    }

    public function test_booking_fails_when_face_has_overlapping_active_booking(): void
    {
        Booking::factory()->accepted()->create([
            'face_id' => $this->faceUser->id,
            'producer_id' => User::factory()->create([
                'userable_type' => Producer::class,
                'userable_id' => Producer::factory(),
            ])->id,
            'date_debut' => now()->addWeek()->startOfDay(),
            'date_fin' => now()->addWeek()->addDay()->endOfDay(),
        ]);

        $data = $this->getValidBookingData();
        $data['date_debut'] = now()->addWeek()->toDateString();
        $data['date_fin'] = now()->addWeek()->addDay()->toDateString();

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('date_debut');
    }

    public function test_booking_fails_when_face_has_no_tarifs(): void
    {
        $faceNoTarif = Face::factory()->create([
            'tarif_horaire' => null,
            'tarif_journalier' => null,
            'is_available' => true,
        ]);
        $faceUserNoTarif = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $faceNoTarif->id,
        ]);

        $data = $this->getValidBookingData();
        $data['face_id'] = $faceNoTarif->uuid;

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('face_id');
    }

    public function test_server_ignores_client_submitted_amounts(): void
    {
        Event::fake([BookingCreated::class]);

        // Client submits manipulated/fake amounts — server must ignore them
        $data = $this->getValidBookingData();
        $data['duree_heures'] = 8;
        $data['tarif_base'] = 1;               // Fake — should be ignored
        $data['montant_total_producteur'] = 1; // Fake — should be ignored
        $data['montant_face_recoit'] = 1;      // Fake — should be ignored

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertCreated();

        // Server recalculates: 8h → daily rate: 1 day * 30000 = 30000
        // FP-3.1a: the setUp Face has no subscription → Découverte (free) tier → 15 % Face commission.
        $expectedPricing = new BookingPricing(30000, 0.15);

        // Stored amounts must match server calculation, NOT client-submitted values
        $this->assertDatabaseHas('bookings', [
            'tarif_base' => $expectedPricing->baseTarif,
            'montant_total_producteur' => $expectedPricing->totalProducerPays,
            'montant_face_recoit' => $expectedPricing->faceReceives,
        ]);

        $this->assertDatabaseMissing('bookings', [
            'tarif_base' => 1,
        ]);
    }

    public function test_booking_resource_returns_amounts_matching_booking_pricing_vo(): void
    {
        Event::fake([BookingCreated::class]);

        $data = $this->getValidBookingData();
        $data['duree_heures'] = 4; // 4h → tarif_journalier/8 × 4 = 30000/8 × 4 = 15000

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $data);

        $response->assertCreated();

        // FP-3.1a: the setUp Face has no subscription → Découverte (free) tier → 15 % Face commission.
        $expectedPricing = new BookingPricing(15000, 0.15);

        // BookingResource must expose amounts that match BookingPricing VO output
        $response
            ->assertJsonPath('data.tarif_base', $expectedPricing->baseTarif)
            ->assertJsonPath('data.montant_total_producteur', $expectedPricing->totalProducerPays)
            ->assertJsonPath('data.montant_face_recoit', $expectedPricing->faceReceives);

        // Verify the relationship: totalProducerPays = tarif_base + 10% commission
        $this->assertSame(
            $expectedPricing->baseTarif + $expectedPricing->producerCommission,
            $expectedPricing->totalProducerPays
        );

        // Verify Face receives tarif_base - 10% commission
        $this->assertSame(
            $expectedPricing->baseTarif - $expectedPricing->faceCommission,
            $expectedPricing->faceReceives
        );
    }

    public function test_booking_resource_returns_correct_structure(): void
    {
        Event::fake([BookingCreated::class]);

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->getValidBookingData());

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'face_id',
                    'producer_id',
                    'status',
                    'status_label',
                    'date_debut',
                    'date_fin',
                    'duree_heures',
                    'type_contenu',
                    'lieu',
                    'message',
                    'tarif_base',
                    'montant_total_producteur',
                    'montant_face_recoit',
                    'face',
                    'producer',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ]);
    }
}
