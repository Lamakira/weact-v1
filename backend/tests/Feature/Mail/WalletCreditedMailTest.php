<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Enums\ProducerType;
use App\Enums\WalletCreditMotif;
use App\Mail\WalletCreditedMail;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WalletCreditedMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_subject_contains_credit_amount_with_french_accents(): void
    {
        $producer = Producer::factory()->create([
            'type' => ProducerType::Agency,
            'agency_name' => 'Studio Alpha',
        ]);

        $mail = new WalletCreditedMail(
            producer: $producer,
            amount: 27000,
            motif: WalletCreditMotif::BookingCancellationRefund,
            newBalance: 127000,
        );

        $envelope = $mail->envelope();

        $this->assertSame(
            'Votre portefeuille a été crédité de 27 000 XOF',
            $envelope->subject,
        );
    }

    #[Test]
    public function test_render_for_booking_cancellation_refund(): void
    {
        $producer = Producer::factory()->create([
            'type' => ProducerType::Agency,
            'agency_name' => 'Studio Alpha',
        ]);

        $mail = new WalletCreditedMail(
            producer: $producer,
            amount: 27000,
            motif: WalletCreditMotif::BookingCancellationRefund,
            newBalance: 127000,
        );

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString('Bonjour Studio Alpha,', $rendered);
        $this->assertStringContainsString("Remboursement suite à l'annulation du booking", $rendered);
        $this->assertStringContainsString('27 000 XOF', $rendered);
        $this->assertStringContainsString('127 000 XOF', $rendered);
        $this->assertStringContainsString('Voir mon portefeuille', $rendered);
        $this->assertStringContainsString('/producer/wallet', $rendered);
        $this->assertStringContainsString('#198496', $rendered);
        $this->assertStringContainsString('/mentions-legales', $rendered);
    }

    #[Test]
    public function test_render_for_booking_no_show_refund(): void
    {
        $producer = Producer::factory()->create([
            'type' => ProducerType::Particulier,
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'agency_name' => null,
        ]);

        $mail = new WalletCreditedMail(
            producer: $producer,
            amount: 50000,
            motif: WalletCreditMotif::BookingNoShowRefund,
            newBalance: 50000,
        );

        $rendered = html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString("Remboursement suite à l'absence signalée de la Face", $rendered);
        $this->assertStringContainsString(
            '<td style="padding: 0 0 10px; color: #16a34a; font-size: 16px; font-weight: 700; text-align: right;">50 000 XOF</td>',
            $rendered,
        );
        $this->assertStringContainsString(
            '<td style="padding: 0; color: #111827; font-size: 16px; font-weight: 700; text-align: right;">50 000 XOF</td>',
            $rendered,
        );
    }

    #[Test]
    public function test_render_falls_back_to_le_producteur_when_display_name_is_empty(): void
    {
        $producer = Producer::factory()->create([
            'type' => ProducerType::Particulier,
            'first_name' => '',
            'last_name' => '',
            'agency_name' => null,
        ]);

        $mail = new WalletCreditedMail(
            producer: $producer,
            amount: 1000,
            motif: WalletCreditMotif::BookingCancellationRefund,
            newBalance: 1000,
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Bonjour Le Producteur,', $rendered);
    }

    #[Test]
    public function test_html_render_contains_no_unaccented_variants_of_french_words(): void
    {
        $producer = Producer::factory()->create([
            'type' => ProducerType::Agency,
            'agency_name' => 'Studio Beta',
        ]);

        $mail = new WalletCreditedMail(
            producer: $producer,
            amount: 27000,
            motif: WalletCreditMotif::BookingNoShowRefund,
            newBalance: 100000,
        );

        $rendered = strip_tags(html_entity_decode($mail->render(), ENT_QUOTES | ENT_HTML5));

        $this->assertDoesNotMatchRegularExpression('/\b(credite|absence signalee)\b/i', $rendered);
    }
}
