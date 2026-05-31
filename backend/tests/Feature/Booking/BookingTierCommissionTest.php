<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Events\BookingCreated;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * FP-3.1a — end-to-end proof that the Face-side booking commission is tier-driven
 * (Découverte 15 % / Starter-Pro 10 % / Élite 5 %) while the producer side stays a
 * flat 10 %. Goes through the real BookingService::create() flow (not the factory).
 */
class BookingTierCommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
    }

    /**
     * Creates an available Face (tarif_journalier 30000) + its User; optionally
     * with an active subscription of the given plan state ('starter' | 'pro' | 'elite').
     */
    private function makeFace(?string $planState = null): Face
    {
        $face = Face::factory()->create([
            'tarif_horaire' => 5000,
            'tarif_journalier' => 30000,
            'is_available' => true,
        ]);
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        if ($planState !== null) {
            // Composes with active(): FaceSubscription::factory()->pro()->active()
            FaceSubscription::factory()->{$planState}()->active()->create([
                'face_id' => $face->id,
            ]);
        }

        return $face;
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(Face $face): array
    {
        return [
            'face_id' => $face->uuid,
            'date_debut' => now()->addWeek()->toDateString(),
            'date_fin' => now()->addWeeks(2)->toDateString(),
            'duree_heures' => 8, // ⇒ daily rate: 1 × 30000 = 30000 tarif_base
            'type_contenu' => 'Publicité',
            'lieu' => 'Cotonou',
            'message' => 'Réservation test commission par palier.',
        ];
    }

    public function test_decouverte_free_face_booking_deducts_15_percent_commission(): void
    {
        Event::fake([BookingCreated::class]);
        $face = $this->makeFace(); // no subscription → Découverte (free)

        $response = $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->bookingPayload($face));

        $response->assertCreated()
            ->assertJsonPath('data.tarif_base', 30000)
            ->assertJsonPath('data.montant_total_producteur', 33000) // 30000 + 10 %
            ->assertJsonPath('data.montant_face_recoit', 25500);     // 30000 − 15 %
    }

    public function test_starter_face_booking_deducts_10_percent_commission(): void
    {
        Event::fake([BookingCreated::class]);
        $face = $this->makeFace('starter');

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->bookingPayload($face))
            ->assertCreated()
            ->assertJsonPath('data.montant_total_producteur', 33000)
            ->assertJsonPath('data.montant_face_recoit', 27000); // 30000 − 10 %
    }

    public function test_pro_face_booking_deducts_10_percent_commission(): void
    {
        Event::fake([BookingCreated::class]);
        $face = $this->makeFace('pro');

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->bookingPayload($face))
            ->assertCreated()
            ->assertJsonPath('data.montant_total_producteur', 33000)
            ->assertJsonPath('data.montant_face_recoit', 27000); // 30000 − 10 %
    }

    public function test_elite_face_booking_deducts_5_percent_commission(): void
    {
        Event::fake([BookingCreated::class]);
        $face = $this->makeFace('elite');

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->bookingPayload($face))
            ->assertCreated()
            ->assertJsonPath('data.montant_total_producteur', 33000)
            ->assertJsonPath('data.montant_face_recoit', 28500); // 30000 − 5 %

        // The tier-driven commission must be LOCKED IN on the persisted row, not
        // just reflected in the JSON response (the payout later reads the stored value).
        $this->assertDatabaseHas('bookings', [
            'montant_total_producteur' => 33000,
            'montant_face_recoit' => 28500,
        ]);
    }

    public function test_producer_total_stays_base_plus_10_percent_across_all_tiers(): void
    {
        Event::fake([BookingCreated::class]);

        foreach ([null, 'starter', 'pro', 'elite'] as $planState) {
            $face = $this->makeFace($planState);
            $this->actingAs($this->producerUser)
                ->postJson('/api/v1/bookings', $this->bookingPayload($face))
                ->assertCreated()
                ->assertJsonPath('data.montant_total_producteur', 33000);
        }
    }
}
