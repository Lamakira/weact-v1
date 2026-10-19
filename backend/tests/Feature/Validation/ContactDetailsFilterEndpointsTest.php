<?php

declare(strict_types=1);

namespace Tests\Feature\Validation;

use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use App\Rules\NoContactDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lot B12 — le filtre de coordonnées s'applique au brief d'un booking et à la
 * motivation d'une candidature ; un brief sans coordonnées est enregistré tel quel.
 */
class ContactDetailsFilterEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    private Face $face;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
        ]);

        $this->face = Face::factory()->create([
            'sexe' => 'homme',
            'tarif_journalier' => 40000,
            'is_available' => true,
        ]);
        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(?string $message): array
    {
        return array_filter([
            'face_id' => $this->face->uuid,
            'date_debut' => now()->addDays(10)->toDateString(),
            'date_fin' => now()->addDays(10)->toDateString(),
            'duree_heures' => 8,
            'type_contenu' => 'Publicité',
            'lieu' => 'Cotonou',
            'message' => $message,
        ], fn ($value) => $value !== null);
    }

    public function test_a_booking_brief_without_contact_details_is_saved(): void
    {
        $brief = 'Publicité de 30 secondes pour une marque de jus, deux tenues à prévoir.';

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->bookingPayload($brief))
            ->assertCreated()
            ->assertJsonPath('data.message', $brief);
    }

    public function test_a_booking_without_brief_is_still_accepted(): void
    {
        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->bookingPayload(null))
            ->assertCreated()
            ->assertJsonPath('data.message', null);
    }

    public function test_a_booking_brief_with_a_phone_number_is_rejected(): void
    {
        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->bookingPayload('Appelez-moi au 97 12 34 56'))
            ->assertStatus(422)
            ->assertJsonPath('errors.message.0', NoContactDetails::MESSAGE);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_a_candidature_motivation_with_a_social_handle_is_rejected(): void
    {
        $mission = Mission::factory()->published()->forAll()->create([
            'date_limite_candidature' => now()->addDays(7),
        ]);
        User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $mission->producer_id,
        ]);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/missions/{$mission->uuid}/apply", [
                'message_motivation' => 'Très motivée, voyez mon travail sur instagram.com/awa.officiel',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.message_motivation.0', NoContactDetails::MESSAGE);

        $this->assertDatabaseCount('candidatures', 0);
    }
}
