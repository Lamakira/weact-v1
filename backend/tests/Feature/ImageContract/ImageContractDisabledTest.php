<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use App\Enums\BookingStatus;
use App\Http\Resources\UserResource;
use App\Jobs\GenerateImageContractPdf;
use App\Models\ImageContract;
use App\Models\SignatureChallenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

class ImageContractDisabledTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->setUpParties(enabled: false);
    }

    public function test_creation_without_conditions_is_unchanged_and_creates_no_contract(): void
    {
        $payload = $this->cashPayload();
        unset($payload['image_terms'], $payload['signature']);

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.image_contract', null);

        $this->assertSame(0, ImageContract::count());
        $this->assertSame(0, SignatureChallenge::count());
    }

    public function test_extra_conditions_sent_while_disabled_are_ignored(): void
    {
        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/bookings', $this->cashPayload(['signature' => ['name' => 'x', 'code' => 'zzz']]))
            ->assertCreated();

        $this->assertSame(0, ImageContract::count());
    }

    public function test_acceptance_without_signature_is_unchanged(): void
    {
        Queue::fake([GenerateImageContractPdf::class]);
        $booking = $this->pendingCashBooking();
        // Même un contrat en base ne bloque rien tant que le drapeau est désactivé.
        $this->makeContract($booking);

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/bookings/{$booking->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', BookingStatus::Accepted->value)
            ->assertJsonPath('data.image_contract', null);

        Queue::assertNothingPushed();
    }

    public function test_routes_answer_not_found_and_the_owner_payload_says_disabled(): void
    {
        $this->actingAs($this->producerUser)
            ->getJson('/api/v1/image-contract/template')
            ->assertNotFound();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'booking_request'])
            ->assertNotFound();

        $payload = UserResource::forOwner($this->producerUser)->resolve(request());
        $this->assertFalse($payload['image_contracts_enabled']);

        config(['image_contracts.enabled' => true]);
        $payload = UserResource::forOwner($this->producerUser)->resolve(request());
        $this->assertTrue($payload['image_contracts_enabled']);
    }

    public function test_listing_and_detail_run_no_image_contract_query(): void
    {
        $booking = $this->pendingCashBooking();

        DB::enableQueryLog();
        $this->actingAs($this->faceUser)->getJson('/api/v1/bookings')->assertOk();
        $this->actingAs($this->faceUser)->getJson("/api/v1/bookings/{$booking->uuid}")->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertStringNotContainsString('image_contracts', $queries);
    }

    public function test_mission_publication_is_unchanged_and_exposes_no_terms(): void
    {
        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/producer/missions', [
                'titre' => 'Recherche visages pour publicité lait',
                'description' => 'Nous recherchons des visages pour une publicité nationale.',
                'date_tournage' => now()->addMonth()->format('Y-m-d'),
                'profil_recherche' => 'Femmes 25-35 ans',
                'budget' => 150000,
                'date_limite_candidature' => now()->addWeeks(2)->format('Y-m-d'),
                'nombre_faces_voulu' => 2,
                'type_mission' => 'publicite',
                'genre_voulu' => 'femme',
                'lieu' => 'Cotonou, Bénin',
                'duree' => '1 journée',
                // Ignorés tant que le drapeau est éteint.
                'image_terms' => $this->terms(),
                'signature' => ['name' => 'x', 'code' => 'zzz'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.image_terms', null);

        $this->assertSame(0, \App\Models\MissionImageTerms::count());
        $this->assertSame(0, SignatureChallenge::count());
    }

    public function test_confirm_and_reconfirm_are_unchanged_even_when_terms_exist(): void
    {
        Queue::fake([GenerateImageContractPdf::class]);
        [, $cash] = $this->cashMissionWithAcceptedFace();
        [, $ugc] = $this->ugcMissionWithAcceptedFace();

        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$cash->uuid}/confirm")
            ->assertOk()
            ->assertJsonPath('data.image_contract', null);
        $this->actingAs($this->faceUser)
            ->postJson("/api/v1/face/candidatures/{$ugc->uuid}/reconfirm")
            ->assertOk()
            ->assertJsonPath('data.image_contract', null);

        $this->assertSame(0, ImageContract::count());
        Queue::assertNothingPushed();
    }

    public function test_mission_details_hide_the_terms_and_the_new_routes_answer_not_found(): void
    {
        [$mission, $candidature] = $this->cashMissionWithAcceptedFace();
        $this->makeCandidatureContract($candidature, ['pdf_path' => 'image-contracts/x.pdf']);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.image_terms', null)
            ->assertJsonPath('candidature.image_contract', null);

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}/candidatures")
            ->assertOk()
            ->assertJsonPath('data.0.image_contract', null);

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/candidatures/{$candidature->uuid}/image-contract/pdf")
            ->assertNotFound();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'mission_publish'])
            ->assertNotFound();
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'candidature_confirm', 'candidature_id' => $candidature->uuid])
            ->assertNotFound();
    }

    public function test_mission_details_run_no_image_query(): void
    {
        [$mission] = $this->cashMissionWithAcceptedFace();

        DB::enableQueryLog();
        $this->actingAs($this->faceUser)->getJson("/api/v1/face/missions/{$mission->uuid}")->assertOk();
        $this->actingAs($this->producerUser)->getJson("/api/v1/producer/missions/{$mission->uuid}")->assertOk();
        $this->actingAs($this->producerUser)->getJson("/api/v1/producer/missions/{$mission->uuid}/candidatures")->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertStringNotContainsString('image_contracts', $queries);
        $this->assertStringNotContainsString('mission_image_terms', $queries);
    }
}
