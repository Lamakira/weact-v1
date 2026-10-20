<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\CandidatureResponseReminderMail;
use App\Mail\MissionFaceUnconfirmedMail;
use App\Mail\MissionPaymentReminderMail;
use App\Mail\MissionPlaceFreedMail;
use App\Mail\MissionSelectionExpiredMail;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Tests\TestCase;

/**
 * Lot C4 : les e-mails Producteur d'événements de mission pointent directement vers la page
 * détail (`/producer/missions/{uuid}?tab=candidatures`), plus vers l'ancienne URL `/candidatures`
 * (toujours valide via la redirection frontend, mais inutilement indirecte).
 */
class ProducerMissionMailsDetailUrlTest extends TestCase
{
    use RefreshDatabase;

    private Mission $mission;

    protected function setUp(): void
    {
        parent::setUp();

        $producer = Producer::factory()->create();
        $this->mission = Mission::factory()->published()->create(['producer_id' => $producer->id]);
    }

    /**
     * @return array<string, array{0: Mailable}>
     */
    private function mails(): array
    {
        $payment = new MissionPayment(['montant_total_producteur' => 110000]);

        return [
            'MissionPaymentReminderMail' => [new MissionPaymentReminderMail($this->mission, $payment, '20 octobre à 18 h')],
            'CandidatureResponseReminderMail' => [new CandidatureResponseReminderMail($this->mission, 3, '20 octobre à 18 h')],
            'MissionSelectionExpiredMail' => [new MissionSelectionExpiredMail($this->mission)],
            'MissionPlaceFreedMail' => [new MissionPlaceFreedMail($this->mission, 'Marie', 50000, '21 octobre')],
            'MissionFaceUnconfirmedMail' => [new MissionFaceUnconfirmedMail($this->mission, 'Marie', 50000)],
        ];
    }

    public function test_every_producer_mission_mail_links_to_the_detail_page_candidatures_tab(): void
    {
        foreach ($this->mails() as $name => [$mail]) {
            $rendered = $mail->render();

            $this->assertStringContainsString(
                '/producer/missions/'.$this->mission->uuid.'?tab=candidatures',
                $rendered,
                "{$name} doit pointer vers la page détail.",
            );
            $this->assertStringNotContainsString(
                '/producer/missions/'.$this->mission->uuid.'/candidatures',
                $rendered,
                "{$name} ne doit plus utiliser l'ancienne URL /candidatures.",
            );
        }
    }
}
