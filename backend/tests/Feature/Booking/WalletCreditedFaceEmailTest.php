<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Events\BookingCompleted;
use App\Listeners\Booking\SendWalletCreditedFaceEmailOnBookingCompleted;
use App\Mail\WalletCreditedFaceMail;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * UGC 7.4 — canal email ADDITIF sur BookingCompleted : confirme à la Face que son
 * portefeuille a été crédité (release escrow → montant_face_recoit). Pour TOUS les
 * bookings (cash + UGC, D-7.4.b). Garde creditHappened RÉPLIQUÉE de l'in-app
 * (montant > 0 && escrow non Refunded — D-7.4.c). Mail::fake() OBLIGATOIRE
 * (D-7.1.f : MAIL_MAILER=smtp pointe sur Gmail réel en local).
 */
class WalletCreditedFaceEmailTest extends TestCase
{
    use RefreshDatabase;

    private const FACE_EMAIL = 'face.wallet@test.bj';

    // ===================================================================
    // Fixtures
    // ===================================================================

    /**
     * Booking complété créditant la Face. type_contenu paramétrable (cash 'Publicité' OU 'UGC')
     * — l'email part dans les deux cas (D-7.4.b). montant_face_recoit explicite (la garde le lit).
     * Aucun escrow par défaut ⇒ escrowTransaction()->where(Refunded)->exists() = false ⇒ crédit réel.
     */
    private function makeCreditedBooking(string $typeContenu = 'Publicité', int $montantFaceRecoit = 85000): Booking
    {
        $face = Face::factory()->create(['prenom' => 'Awa', 'nom' => 'Diallo']);
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => self::FACE_EMAIL,
        ]);

        $producer = Producer::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        return Booking::factory()->create([
            'face_id' => $faceUser->id,         // users.id
            'producer_id' => $producerUser->id, // users.id
            'type_contenu' => $typeContenu,
            'montant_face_recoit' => $montantFaceRecoit,
        ]);
    }

    // ===================================================================
    // AC1 / AC2 / AC3 — garde creditHappened + dispatch email
    // ===================================================================

    public function test_completed_cash_booking_queues_face_wallet_email(): void
    {
        Mail::fake();
        $booking = $this->makeCreditedBooking('Publicité', 85000);
        (new SendWalletCreditedFaceEmailOnBookingCompleted)->handle(new BookingCompleted($booking));
        Mail::assertQueued(
            WalletCreditedFaceMail::class,
            fn (WalletCreditedFaceMail $m): bool => $m->hasTo(self::FACE_EMAIL) && $m->amount === 85000,
        );
    }

    public function test_completed_ugc_booking_queues_face_wallet_email(): void
    {
        Mail::fake();
        $booking = $this->makeCreditedBooking('UGC', 120000);
        (new SendWalletCreditedFaceEmailOnBookingCompleted)->handle(new BookingCompleted($booking));
        Mail::assertQueued(WalletCreditedFaceMail::class, fn (WalletCreditedFaceMail $m): bool => $m->hasTo(self::FACE_EMAIL));
    }

    public function test_product_only_ugc_booking_queues_nothing(): void
    {
        Mail::fake();
        $booking = $this->makeCreditedBooking('UGC', 0); // montant_face_recoit = 0
        (new SendWalletCreditedFaceEmailOnBookingCompleted)->handle(new BookingCompleted($booking));
        Mail::assertNothingQueued();
    }

    public function test_refunded_escrow_booking_queues_nothing(): void
    {
        Mail::fake();
        $booking = $this->makeCreditedBooking('UGC', 120000); // montant > 0 …
        EscrowTransaction::factory()->refunded()->create(['booking_id' => $booking->id]); // … mais escrow remboursé
        (new SendWalletCreditedFaceEmailOnBookingCompleted)->handle(new BookingCompleted($booking->fresh()));
        Mail::assertNothingQueued();
    }

    public function test_empty_face_email_queues_nothing(): void
    {
        Mail::fake();
        $booking = $this->makeCreditedBooking('Publicité', 85000);
        $booking->face->update(['email' => '']); // Face User sans email résolvable
        (new SendWalletCreditedFaceEmailOnBookingCompleted)->handle(new BookingCompleted($booking));
        Mail::assertNothingQueued();
    }

    // ===================================================================
    // AC5 — smoke render de la vue Blade
    // ===================================================================

    public function test_mailable_renders_its_blade_view(): void
    {
        $mailable = new WalletCreditedFaceMail(
            Face::factory()->make(['prenom' => 'Awa']),
            85000,
            200000,
        );

        $html = $mailable->render();

        $this->assertStringContainsString('WEACT', $html); // layout de base rendu
        $this->assertStringContainsString('85 000 XOF', $html);
        $this->assertStringContainsString('/face/wallet', $html);
    }
}
