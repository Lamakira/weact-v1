<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\MissionCompletedMail;
use App\Models\Face;
use App\Models\Mission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MissionCompletedMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_subject_contains_mission_title_with_french_typography(): void
    {
        $mission = Mission::factory()->create(['titre' => 'Pub TV Printemps 2026']);
        $face = Face::factory()->create(['prenom' => 'Amina', 'nom' => 'Dupont']);

        $mail = new MissionCompletedMail(
            face: $face,
            mission: $mission,
            amount: 90000,
        );

        $envelope = $mail->envelope();

        $this->assertSame(
            'La mission « Pub TV Printemps 2026 » est terminée — votre portefeuille a été crédité',
            $envelope->subject,
        );
    }

    #[Test]
    public function test_rendered_content_contains_first_name_mission_amount_cta_and_layout_anchors(): void
    {
        $mission = Mission::factory()->create(['titre' => 'Pub TV Printemps 2026']);
        $face = Face::factory()->create(['prenom' => 'Amina', 'nom' => 'Dupont']);

        $mail = new MissionCompletedMail(
            face: $face,
            mission: $mission,
            amount: 90000,
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Bonjour Amina', $rendered);
        $this->assertStringContainsString('Pub TV Printemps 2026', $rendered);
        $this->assertStringContainsString('90 000 XOF', $rendered);
        $this->assertStringContainsString('/face/wallet', $rendered);
        $this->assertStringContainsString('Voir mon portefeuille', $rendered);
        $this->assertStringContainsString('#198496', $rendered);
        $this->assertStringContainsString('/mentions-legales', $rendered);
    }

    #[Test]
    public function test_falls_back_to_display_name_when_prenom_is_empty(): void
    {
        $mission = Mission::factory()->create(['titre' => 'X']);
        $face = Face::factory()->create(['prenom' => '', 'nom' => '', 'username' => 'amina_d']);

        $mail = new MissionCompletedMail(
            face: $face,
            mission: $mission,
            amount: 1,
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Bonjour amina_d', $rendered);
    }
}
