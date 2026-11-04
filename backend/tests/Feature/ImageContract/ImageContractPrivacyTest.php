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
}
