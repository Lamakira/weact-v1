<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Events\BookingAccepted;
use App\Listeners\Booking\SendBookingAcceptedEmail;
use App\Listeners\Booking\SendUgcBookingAcceptedEmail;
use App\Mail\BookingAcceptedMail;
use App\Mail\UgcDealAcceptedMail;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Email Producteur ADDITIF sur BookingAccepted (pendant de SendBookingRefusedEmail).
 * Réservé au cash : l'UGC a déjà UgcDealAcceptedMail sur le même event.
 * Mail::fake() OBLIGATOIRE (D-7.1.f : MAIL_MAILER=smtp pointe sur Gmail réel en local).
 */
class SendBookingAcceptedEmailListenerTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCER_EMAIL = 'prod.accepted@test.bj';

    /**
     * Booking accepté avec Face + Producteur résolvables (users.id).
     * ProducerFactory/FaceFactory ne créent PAS de User → on attache les Users (piège ugc-3-0).
     */
    private function makeAcceptedBooking(string $typeContenu = 'Publicité'): Booking
    {
        $face = Face::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'face.accepted@test.bj',
        ]);

        $producer = Producer::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'email' => self::PRODUCER_EMAIL,
        ]);

        return Booking::factory()->create([
            'face_id' => $faceUser->id,         // users.id
            'producer_id' => $producerUser->id, // users.id
            'type_contenu' => $typeContenu,
            'nom_produit' => 'Crème hydratante',
            'lieu' => 'Cotonou, Fidjrossè',
            'accepted_at' => now(),
            'montant_total_producteur' => 110000,
            'montant_face_recoit' => 85000,
        ]);
    }

    public function test_accepted_cash_booking_queues_producer_email(): void
    {
        Mail::fake();
        $booking = $this->makeAcceptedBooking('Publicité');

        (new SendBookingAcceptedEmail)->handle(new BookingAccepted($booking));

        Mail::assertQueued(
            BookingAcceptedMail::class,
            fn (BookingAcceptedMail $m): bool => $m->hasTo(self::PRODUCER_EMAIL),
        );
    }

    /**
     * Garde anti-doublon : sur un booking UGC, SendUgcBookingAcceptedEmail envoie déjà
     * UgcDealAcceptedMail (« expédiez le produit ») sur ce MÊME event. Le nouveau
     * listener doit se taire, sinon le Producteur reçoit deux emails.
     */
    public function test_accepted_ugc_booking_queues_nothing_from_this_listener(): void
    {
        Mail::fake();
        $booking = $this->makeAcceptedBooking('UGC');

        (new SendBookingAcceptedEmail)->handle(new BookingAccepted($booking));

        Mail::assertNotQueued(BookingAcceptedMail::class);
        Mail::assertNothingQueued();
    }

    /** Le canal UGC pré-existant reste intouché : un booking UGC accepté garde son mail dédié. */
    public function test_accepted_ugc_booking_still_queues_the_ugc_email(): void
    {
        Mail::fake();
        $booking = $this->makeAcceptedBooking('UGC');

        (new SendUgcBookingAcceptedEmail)->handle(new BookingAccepted($booking));
        (new SendBookingAcceptedEmail)->handle(new BookingAccepted($booking));

        Mail::assertQueued(UgcDealAcceptedMail::class);
        Mail::assertNotQueued(BookingAcceptedMail::class);
    }

    public function test_accepted_booking_empty_producer_email_queues_nothing(): void
    {
        Mail::fake();
        $booking = $this->makeAcceptedBooking('Publicité');
        $booking->producer->update(['email' => '']); // Producteur User sans email résolvable

        (new SendBookingAcceptedEmail)->handle(new BookingAccepted($booking->fresh()));

        Mail::assertNothingQueued();
    }

    /**
     * Garantie NON-FATALE CRITIQUE : BookingAccepted est dispatché DANS la transaction
     * de BookingService::accept → un throw rollbackerait l'acceptation. On force
     * Mail::to() à throw et on prouve que le listener AVALE l'exception (try/catch
     * englobant SANS re-throw) : aucune exception ne se propage + warning loggé.
     */
    public function test_accepted_booking_listener_swallows_failure_without_rethrow(): void
    {
        Log::spy();
        $booking = $this->makeAcceptedBooking('Publicité');
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail down'));

        // Atteindre la ligne d'assertion prouve qu'aucune exception ne s'est propagée.
        (new SendBookingAcceptedEmail)->handle(new BookingAccepted($booking));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => $message === 'BookingAcceptedMail queue failed')
            ->once();
    }

    /** Bout-en-bout : l'acceptation via l'event câblé produit bien l'email (listener découvert). */
    public function test_dispatching_the_event_reaches_the_listener(): void
    {
        Mail::fake();
        $booking = $this->makeAcceptedBooking('Publicité');

        BookingAccepted::dispatch($booking);

        Mail::assertQueued(
            BookingAcceptedMail::class,
            fn (BookingAcceptedMail $m): bool => $m->hasTo(self::PRODUCER_EMAIL),
        );
    }

    public function test_booking_accepted_mailable_renders_its_blade_view(): void
    {
        $mailable = new BookingAcceptedMail($this->makeAcceptedBooking('Publicité'));

        $html = $mailable->render();

        $this->assertStringContainsString('WEACT', $html); // layout de base rendu
        $this->assertStringContainsString('accepté', $html);
        $this->assertStringContainsString('110 000 XOF', $html);
        $this->assertStringContainsString('24 heures', $html);
        $this->assertStringContainsString('/producer/bookings/', $html);
    }
}
