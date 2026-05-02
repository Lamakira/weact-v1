<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\FaceMarkedAbsentMail;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FaceMarkedAbsentMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function subject_contains_mission_title_with_french_typography(): void
    {
        $mission = Mission::factory()->create(['titre' => 'Pub TV Été 2026']);
        $face = Face::factory()->create(['prenom' => 'Amina', 'nom' => 'Dupont']);
        $producer = Producer::factory()->individual()->create(['first_name' => 'Studio', 'last_name' => 'Beta']);

        $mail = new FaceMarkedAbsentMail(
            face: $face,
            mission: $mission,
            producer: $producer,
            amount: 90_000,
            disputeDeadline: Carbon::create(2026, 5, 2, 23, 59, 0),
        );

        $this->assertSame(
            'Une absence a été déclarée pour la mission « Pub TV Été 2026 »',
            $mail->envelope()->subject,
        );
    }

    #[Test]
    public function rendered_content_contains_critical_absence_context_and_cta(): void
    {
        $mission = Mission::factory()->create([
            'titre' => 'Pub TV Été 2026',
            'date_tournage' => '2026-04-29',
        ]);
        $face = Face::factory()->create(['prenom' => 'Amina', 'nom' => 'Dupont']);
        $producer = Producer::factory()->individual()->create(['first_name' => 'Studio', 'last_name' => 'Beta']);

        $mail = new FaceMarkedAbsentMail(
            face: $face,
            mission: $mission,
            producer: $producer,
            amount: 90_000,
            disputeDeadline: Carbon::create(2026, 5, 2, 23, 59, 0),
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Bonjour Amina', $rendered);
        $this->assertStringContainsString('Pub TV Été 2026', $rendered);
        $this->assertStringContainsString('Studio Beta', $rendered);
        $this->assertStringContainsString('90 000 XOF', $rendered);
        $this->assertStringContainsString('mercredi 29 avril 2026', $rendered);
        $this->assertStringContainsString('samedi 02 mai 2026', $rendered);
        $this->assertStringContainsString('⏰', $rendered);
        $this->assertStringContainsString('Voir la mission', $rendered);
        $this->assertStringContainsString('Pour contester', $rendered);
        $this->assertStringContainsString('/face/missions/'.$mission->uuid, $rendered);
        $this->assertStringNotContainsString('/dispute-attendance', $rendered);
        $this->assertStringContainsString('#198496', $rendered);
        $this->assertStringContainsString('#fef3c7', $rendered);
        $this->assertStringContainsString('/mentions-legales', $rendered);
    }

    #[Test]
    public function falls_back_to_display_name_when_prenom_is_empty(): void
    {
        $mission = Mission::factory()->create(['titre' => 'X']);
        $face = Face::factory()->create(['prenom' => '', 'nom' => '', 'username' => 'amina_d']);
        $producer = Producer::factory()->create();

        $mail = new FaceMarkedAbsentMail(
            face: $face,
            mission: $mission,
            producer: $producer,
            amount: 1,
            disputeDeadline: Carbon::create(2026, 5, 2, 23, 59, 0),
        );

        $this->assertStringContainsString('Bonjour amina_d', $mail->render());
    }

    #[Test]
    public function amount_format_has_no_decimals_and_uses_xof_suffix(): void
    {
        $mission = Mission::factory()->create(['titre' => 'X']);
        $face = Face::factory()->create(['prenom' => 'A', 'nom' => 'B']);
        $producer = Producer::factory()->create();

        $mail = new FaceMarkedAbsentMail(
            face: $face,
            mission: $mission,
            producer: $producer,
            amount: 1_234_567,
            disputeDeadline: Carbon::create(2026, 5, 2, 23, 59, 0),
        );

        $this->assertStringContainsString('1 234 567 XOF', $mail->render());
    }
}
