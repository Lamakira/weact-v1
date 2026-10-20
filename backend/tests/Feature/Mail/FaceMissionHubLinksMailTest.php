<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\FaceConfirmationExpiredMail;
use App\Mail\FaceConfirmationReminderMail;
use App\Models\Face;
use App\Models\Mission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lot C5 : les emails Face renvoient vers la page mission (point central), pas la liste des candidatures.
 */
final class FaceMissionHubLinksMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_confirmation_reminder_links_to_the_face_mission_page(): void
    {
        $mission = Mission::factory()->create();
        $face = Face::factory()->create();

        $rendered = (new FaceConfirmationReminderMail($face, $mission, '12/10 à 08h00'))->render();

        $this->assertStringContainsString("/face/missions/{$mission->uuid}", $rendered);
        $this->assertStringNotContainsString('/face/candidatures', $rendered);
    }

    #[Test]
    public function test_confirmation_expired_links_to_the_face_mission_page(): void
    {
        $mission = Mission::factory()->create();
        $face = Face::factory()->create();

        $rendered = (new FaceConfirmationExpiredMail($face, $mission))->render();

        $this->assertStringContainsString("/face/missions/{$mission->uuid}", $rendered);
        $this->assertStringNotContainsString('/face/candidatures', $rendered);
    }
}
