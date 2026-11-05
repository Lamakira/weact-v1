<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

class ImageContractPrivacyTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpParties();
    }

    public function test_the_booking_resource_exposes_neither_a_full_email_nor_a_user_agent_nor_the_fingerprint(): void
    {
        $booking = $this->pendingCashBooking();
        $contract = $this->makeSignedContract($booking, [
            'producer_user_agent' => 'Mozilla/5.0 ProducerSecretAgent',
            'face_user_agent' => 'Mozilla/5.0 FaceSecretAgent',
            'pdf_path' => 'image-contracts/x.pdf',
            'pdf_sha256' => str_repeat('cd', 32),
        ]);

        foreach ([$this->faceUser, $this->producerUser] as $viewer) {
            $json = $this->actingAs($viewer)
                ->getJson("/api/v1/bookings/{$booking->uuid}")
                ->assertOk()
                ->assertJsonPath('data.image_contract.id', $contract->uuid)
                ->getContent();

            $payload = (string) $json;
            $this->assertStringNotContainsString('ProducerSecretAgent', $payload);
            $this->assertStringNotContainsString('FaceSecretAgent', $payload);
            $this->assertStringNotContainsString('user_agent', $payload);
            $this->assertStringNotContainsString(str_repeat('cd', 32), $payload);
            $this->assertStringNotContainsString('email_masked', $payload);
            // Le bloc contrat lui-même ne porte aucune adresse complète (le reste du booking
            // expose déjà les comptes selon les règles existantes de UserResource).
            $block = (string) json_encode(json_decode($payload, true)['data']['image_contract']);
            $this->assertStringNotContainsString('@', $block);
        }

        $this->assertArrayNotHasKey('producer_user_agent', $contract->toArray());
        $this->assertArrayNotHasKey('face_user_agent', $contract->toArray());
    }

    public function test_the_list_does_not_load_the_contract(): void
    {
        $booking = $this->pendingCashBooking();
        $this->makeContract($booking);

        $this->actingAs($this->faceUser)
            ->getJson('/api/v1/bookings')
            ->assertOk()
            ->assertJsonPath('data.0.image_contract', null);
    }

    public function test_status_follows_the_lifecycle(): void
    {
        $booking = $this->pendingCashBooking();
        $contract = $this->makeContract($booking);
        $this->assertSame('awaiting_face', $contract->status());

        $contract->update(['face_signed_at' => now(), 'face_signed_name' => 'Aïcha Bello']);
        $this->assertSame('signed', $contract->fresh()->status());

        $contract->update(['voided_at' => now()]);
        $this->assertSame('void', $contract->fresh()->status());

        $contract->update(['effective_at' => now()]);
        $this->assertSame('effective', $contract->fresh()->status());
    }

    public function test_the_candidature_payload_exposes_neither_a_full_email_nor_a_user_agent_nor_the_fingerprint(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $candidature->update(['status' => \App\Enums\CandidatureStatus::Confirmed]);
        $contract = $this->makeCandidatureContract($candidature, [
            'producer_user_agent' => 'Mozilla/5.0 ProducerSecretAgent',
            'face_user_agent' => 'Mozilla/5.0 FaceSecretAgent',
            'pdf_path' => 'image-contracts/x.pdf',
            'pdf_sha256' => str_repeat('cd', 32),
        ]);

        $faceJson = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$candidature->mission->uuid}")
            ->assertOk()
            ->assertJsonPath('candidature.image_contract.id', $contract->uuid)
            ->assertJsonPath('candidature.image_contract.pdf_ready', true)
            ->getContent();

        $producerJson = $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$candidature->mission->uuid}/candidatures")
            ->assertOk()
            ->assertJsonPath('data.0.image_contract.id', $contract->uuid)
            ->getContent();

        foreach ([(string) $faceJson, (string) $producerJson] as $payload) {
            $this->assertStringNotContainsString('SecretAgent', $payload);
            $this->assertStringNotContainsString('user_agent', $payload);
            $this->assertStringNotContainsString(str_repeat('cd', 32), $payload);
            $this->assertStringNotContainsString('email_masked', $payload);
        }

        $data = json_decode((string) $faceJson, true);
        $this->assertStringNotContainsString('@', (string) json_encode($data['candidature']['image_contract']));
        $data = json_decode((string) $producerJson, true);
        $this->assertStringNotContainsString('@', (string) json_encode($data['data'][0]['image_contract']));
    }

    public function test_the_mission_terms_payload_exposes_neither_an_email_nor_a_user_agent(): void
    {
        [$mission] = $this->cashMissionWithAcceptedFace();

        foreach ([$this->faceUser, $this->producerUser] as $viewer) {
            $prefix = $viewer->is($this->faceUser) ? 'face' : 'producer';
            $payload = (string) $this->actingAs($viewer)
                ->getJson("/api/v1/{$prefix}/missions/{$mission->uuid}")
                ->assertOk()
                ->assertJsonPath('data.image_terms.finalite', 'Campagne réseaux sociaux de la marque')
                ->getContent();

            $this->assertStringNotContainsString('TestBrowser', $payload);
            $this->assertStringNotContainsString('user_agent', $payload);
            $this->assertStringNotContainsString('email_masked', $payload);
            $this->assertStringNotContainsString('p•••••@example.com', $payload);
        }

        $this->assertArrayNotHasKey('producer_user_agent', $mission->imageTerms->toArray());
    }

    public function test_the_producer_candidature_list_loads_the_contracts_without_n_plus_one(): void
    {
        [$mission, $first] = $this->cashMissionWithAcceptedFace();
        $this->makeCandidatureContract($first);
        foreach (range(1, 3) as $i) {
            $other = \App\Models\Candidature::factory()->create(['mission_id' => $mission->id, 'status' => \App\Enums\CandidatureStatus::Confirmed]);
            $this->makeCandidatureContract($other);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($this->producerUser)->getJson("/api/v1/producer/missions/{$mission->uuid}/candidatures")->assertOk();
        $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->pluck('query')->filter(fn (string $q) => str_contains($q, 'from `image_contracts`'));
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertCount(1, $queries);
    }

    public function test_the_producer_signed_name_is_shown_to_the_producer_but_never_to_a_face(): void
    {
        [$mission] = $this->cashMissionWithAcceptedFace();

        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/producer/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('data.image_terms.producer_signed_name', 'Jean Producteur');

        $response = $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonMissingPath('data.image_terms.producer_signed_name')
            ->assertJsonPath('data.image_terms.producer_signed_at', $mission->imageTerms->producer_signed_at->toIso8601String());

        $this->assertStringNotContainsString('Jean Producteur', (string) $response->getContent());
    }
}
