<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\FaceSelectedMail;
use App\Models\Face;
use App\Models\Mission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FaceSelectedMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_subject_contains_mission_title_with_french_typography(): void
    {
        $mission = Mission::factory()->create(['titre' => 'Pub TV Printemps 2026']);
        $face = Face::factory()->create(['prenom' => 'Amina', 'nom' => 'Dupont']);

        $mail = new FaceSelectedMail(
            face: $face,
            mission: $mission,
            producerName: 'Studio Alpha',
            amount: 75000,
        );

        $envelope = $mail->envelope();

        $this->assertSame(
            'Vous avez été sélectionnée pour la mission « Pub TV Printemps 2026 »',
            $envelope->subject,
        );
    }

    #[Test]
    public function test_rendered_content_contains_first_name_mission_producer_date_amount_cta(): void
    {
        $mission = Mission::factory()->create([
            'titre' => 'Pub TV Printemps 2026',
            'date_tournage' => '2026-05-05',
        ]);
        $face = Face::factory()->create(['prenom' => 'Amina', 'nom' => 'Dupont']);

        $mail = new FaceSelectedMail(
            face: $face,
            mission: $mission,
            producerName: 'Studio Alpha',
            amount: 75000,
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Bonjour Amina', $rendered);
        $this->assertStringContainsString('Pub TV Printemps 2026', $rendered);
        $this->assertStringContainsString('Studio Alpha', $rendered);
        $this->assertStringContainsString('75 000 XOF', $rendered);
        $this->assertStringContainsString('mai 2026', $rendered);
        $this->assertStringContainsString('/face/candidatures', $rendered);
        $this->assertStringContainsString('Confirmer ma participation', $rendered);
        $this->assertStringContainsString('#198496', $rendered);
        $this->assertStringContainsString('/mentions-legales', $rendered);
    }

    #[Test]
    public function test_falls_back_to_display_name_when_prenom_is_empty(): void
    {
        $mission = Mission::factory()->create(['titre' => 'X']);
        $face = Face::factory()->create(['prenom' => '', 'nom' => '', 'username' => 'amina_d']);

        $mail = new FaceSelectedMail(
            face: $face,
            mission: $mission,
            producerName: 'Y',
            amount: 1,
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Bonjour amina_d', $rendered);
    }
}
