<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Enums\ProducerType;
use App\Mail\FaceConfirmedMail;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FaceConfirmedMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_subject_contains_face_name_and_mission_title_with_french_typography(): void
    {
        $producer = Producer::factory()->individual()->create([
            'first_name' => 'Jean',
            'last_name' => 'Martin',
        ]);
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Pub TV Printemps 2026',
        ]);
        $face = Face::factory()->create(['prenom' => 'Marie', 'nom' => 'Dupont']);

        $mail = new FaceConfirmedMail(
            producer: $producer,
            face: $face,
            mission: $mission,
        );

        $envelope = $mail->envelope();

        $this->assertSame(
            'Marie Dupont a confirmé sa participation à la mission « Pub TV Printemps 2026 »',
            $envelope->subject,
        );
    }

    #[Test]
    public function test_rendered_content_contains_producer_name_face_mission_date_cta_uuid_and_layout_anchors(): void
    {
        $producer = Producer::factory()->individual()->create([
            'first_name' => 'Jean',
            'last_name' => 'Martin',
        ]);
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'Pub TV Printemps 2026',
            'date_tournage' => '2026-05-05',
        ]);
        $face = Face::factory()->create(['prenom' => 'Marie', 'nom' => 'Dupont']);

        $mail = new FaceConfirmedMail(
            producer: $producer,
            face: $face,
            mission: $mission,
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Bonjour Jean Martin', $rendered);
        $this->assertStringContainsString('Marie Dupont', $rendered);
        $this->assertStringContainsString('Pub TV Printemps 2026', $rendered);
        $this->assertStringContainsString('mai 2026', $rendered);
        $this->assertStringContainsString('/producer/missions/'.$mission->uuid.'/candidatures', $rendered);
        $this->assertStringContainsString('Voir les candidatures', $rendered);
        $this->assertStringContainsString('#198496', $rendered);
        $this->assertStringContainsString('/mentions-legales', $rendered);
    }

    #[Test]
    public function test_falls_back_to_producteur_when_producer_display_name_is_empty(): void
    {
        $producer = Producer::factory()->create([
            'type' => ProducerType::Particulier,
            'first_name' => '',
            'last_name' => '',
            'agency_name' => null,
        ]);
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'titre' => 'X',
        ]);
        $face = Face::factory()->create(['prenom' => 'Marie', 'nom' => 'Dupont']);

        $mail = new FaceConfirmedMail(
            producer: $producer,
            face: $face,
            mission: $mission,
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Bonjour Producteur', $rendered);
    }
}
