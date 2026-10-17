<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\CandidatureAcceptedMail;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CandidatureAcceptedMailTest extends TestCase
{
    #[Test]
    public function test_mentions_the_reconfirmation_deadline_and_slot_release(): void
    {
        $mail = new CandidatureAcceptedMail(
            faceName: 'Amina',
            missionTitle: 'Unboxing Sérum',
            productName: 'Sérum',
            reconfirmUrl: 'https://weact.test/face/candidatures',
            reconfirmDeadline: Carbon::create(2026, 10, 11, 14, 30, 0, 'UTC'),
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Vous avez jusqu\'au', $rendered);
        $this->assertStringContainsString('11 octobre 2026', $rendered);
        $this->assertStringContainsString('pour reconfirmer, sinon la place sera libérée.', $rendered);
    }

    #[Test]
    public function test_omits_the_deadline_sentence_when_no_deadline_is_given(): void
    {
        $mail = new CandidatureAcceptedMail(
            faceName: 'Amina',
            missionTitle: 'Unboxing Sérum',
            productName: 'Sérum',
            reconfirmUrl: 'https://weact.test/face/candidatures',
        );

        $this->assertStringNotContainsString('sinon la place sera libérée', $mail->render());
    }
}
