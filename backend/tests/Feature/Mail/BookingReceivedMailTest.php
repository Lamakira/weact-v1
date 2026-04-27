<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Enums\ProducerType;
use App\Mail\BookingReceivedMail;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BookingReceivedMailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_subject_contains_producer_name_and_booking_date_in_french(): void
    {
        $producer = Producer::factory()->agency()->create([
            'agency_name' => 'Studio Alpha',
        ]);
        $face = Face::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $booking = Booking::factory()->create([
            'date_debut' => '2026-05-12 09:00:00',
            'date_fin' => '2026-05-12 17:00:00',
            'duree_heures' => 8,
            'type_contenu' => 'Publicité',
            'lieu' => 'Cotonou',
            'montant_face_recoit' => 45000,
        ]);

        $mail = new BookingReceivedMail(
            face: $face,
            producer: $producer,
            booking: $booking,
        );

        $envelope = $mail->envelope();

        $this->assertSame(
            'Nouvelle demande de booking de Studio Alpha pour le mardi 12 mai 2026',
            $envelope->subject,
        );
    }

    #[Test]
    public function test_rendered_content_contains_first_name_producer_type_date_duration_location_amount_cta_and_layout_anchors(): void
    {
        $producer = Producer::factory()->agency()->create(['agency_name' => 'Studio Alpha']);
        $face = Face::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $booking = Booking::factory()->create([
            'date_debut' => '2026-05-12 09:00:00',
            'date_fin' => '2026-05-12 17:00:00',
            'duree_heures' => 8,
            'type_contenu' => 'Publicité',
            'lieu' => 'Cotonou',
            'montant_face_recoit' => 45000,
        ]);

        $mail = new BookingReceivedMail(
            face: $face,
            producer: $producer,
            booking: $booking,
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Bonjour Awa', $rendered);
        $this->assertStringContainsString('Studio Alpha', $rendered);
        $this->assertStringContainsString('Publicité', $rendered);
        $this->assertStringContainsString('mai 2026', $rendered);
        $this->assertStringContainsString('8 heures', $rendered);
        $this->assertStringContainsString('Cotonou', $rendered);
        $this->assertStringContainsString('45 000 XOF', $rendered);
        $this->assertStringContainsString('/face/bookings/'.$booking->uuid, $rendered);
        $this->assertStringContainsString('Voir la demande', $rendered);
        $this->assertStringContainsString('#198496', $rendered);
        $this->assertStringContainsString('/mentions-legales', $rendered);
    }

    #[Test]
    public function test_rendered_content_omits_location_row_when_booking_has_no_lieu(): void
    {
        $producer = Producer::factory()->agency()->create(['agency_name' => 'Studio Alpha']);
        $face = Face::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $booking = Booking::factory()->create([
            'lieu' => null,
            'duree_heures' => 8,
            'type_contenu' => 'Publicité',
            'montant_face_recoit' => 45000,
        ]);

        $mail = new BookingReceivedMail(
            face: $face,
            producer: $producer,
            booking: $booking,
        );

        $rendered = $mail->render();

        // The label "Lieu" should not appear when lieu is null (cohérent listener
        // NotifyFaceOnBookingReceived qui omet aussi la mention du lieu quand vide).
        $this->assertStringNotContainsString('>Lieu<', $rendered);
    }

    #[Test]
    public function test_falls_back_to_le_producteur_when_producer_display_name_is_empty(): void
    {
        // Particulier sans first/last_name + agency_name null → slugSourceName vide → display_name vide.
        $producer = Producer::factory()->create([
            'type' => ProducerType::Particulier,
            'first_name' => '',
            'last_name' => '',
            'agency_name' => null,
        ]);
        $face = Face::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $booking = Booking::factory()->create([
            'duree_heures' => 8,
            'type_contenu' => 'Publicité',
            'montant_face_recoit' => 45000,
        ]);

        $mail = new BookingReceivedMail(
            face: $face,
            producer: $producer,
            booking: $booking,
        );

        $rendered = $mail->render();
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Le Producteur', $rendered);
        $this->assertStringContainsString('Le Producteur', $envelope->subject);
    }

    #[Test]
    public function test_rendered_content_uses_french_accents_no_unaccented_variants_appear(): void
    {
        $producer = Producer::factory()->agency()->create(['agency_name' => 'Studio Alpha']);
        $face = Face::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $booking = Booking::factory()->create([
            'lieu' => 'Cotonou',
            'duree_heures' => 8,
            'type_contenu' => 'Publicité',
            'montant_face_recoit' => 45000,
        ]);

        $mail = new BookingReceivedMail(
            face: $face,
            producer: $producer,
            booking: $booking,
        );

        // Strip HTML to get only the visible French text — avoids false positives
        // on PHP/Blade variable names like $dureeHeures or CSS classes.
        $textOnly = strip_tags($mail->render());

        $this->assertDoesNotMatchRegularExpression(
            '/\b(reception|duree|tournee|remuneration|repondre)\b/i',
            $textOnly,
            'No unaccented French variants should appear in rendered email text.',
        );
    }
}
