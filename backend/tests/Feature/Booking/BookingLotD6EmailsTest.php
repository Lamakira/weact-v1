<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Events\BookingCommissionPaid;
use App\Events\BookingDisputeSettled;
use App\Events\BookingExpired;
use App\Events\BookingPartiallyConfirmed;
use App\Mail\BookingDisputeSettledMail;
use App\Mail\BookingExpiredMail;
use App\Mail\BookingPaidFaceMail;
use App\Mail\BookingPaidProducerMail;
use App\Mail\BookingProducerConfirmedMail;
use App\Mail\BookingRefusedMail;
use App\Mail\WalletCreditedFaceMail;
use App\Mail\WalletCreditedMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Lot D6 : e-mails manquants côté bookings (payé, expiré, confirmation Producteur, issue de litige).
 * Les relances (confirmation Face, rappel de tournage) sont couvertes dans BookingDisputeWindowTest
 * et RemindShootingDayCommandTest.
 */
class BookingLotD6EmailsTest extends TestCase
{
    use RefreshDatabase;

    private User $producerUser;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'UTC'));

        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => Producer::factory()->create()->id,
            'email' => 'studio@example.test',
            'balance' => 0,
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => Face::factory()->create(['rating_penalty' => 0.0])->id,
            'email' => 'face@example.test',
            'balance' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function booking(BookingStatus $status, array $attributes = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'face_id' => $this->faceUser->id,
            'producer_id' => $this->producerUser->id,
            'status' => $status,
            'type_contenu' => 'Publicité',
            // Forme réelle : dates calendaires à minuit.
            'date_debut' => now()->addDays(5)->startOfDay(),
            'date_fin' => now()->addDays(5)->startOfDay(),
            'lieu' => 'Cotonou',
            'tarif_base' => 100000,
            'montant_total_producteur' => 110000,
            'montant_face_recoit' => 90000,
        ], $attributes));
    }

    // ------------------------------------------------------------------
    // 1. Booking payé
    // ------------------------------------------------------------------

    public function test_paid_booking_emails_the_face_and_the_producer(): void
    {
        Mail::fake();

        $booking = $this->booking(BookingStatus::Accepted, ['accepted_at' => now()]);

        app(BookingService::class)->markAsPaid($booking, 'ref-d6');

        Mail::assertQueued(BookingPaidFaceMail::class, 1);
        Mail::assertQueued(
            BookingPaidFaceMail::class,
            fn (BookingPaidFaceMail $mail): bool => $mail->hasTo('face@example.test') && $mail->booking->is($booking),
        );
        Mail::assertQueued(BookingPaidProducerMail::class, 1);
        Mail::assertQueued(
            BookingPaidProducerMail::class,
            fn (BookingPaidProducerMail $mail): bool => $mail->hasTo('studio@example.test') && ! $mail->ugc,
        );

        // Contenu Face : net perçu (90 000), lieu, lien ; contenu Producteur : montant payé (110 000).
        $faceHtml = (new BookingPaidFaceMail($booking->fresh()->load('face.userable', 'producer.userable')))->render();
        $this->assertStringContainsString('90 000 XOF', $faceHtml);
        $this->assertStringContainsString('Cotonou', $faceHtml);
        $this->assertStringContainsString("/face/bookings/{$booking->uuid}", $faceHtml);
        $producerHtml = (new BookingPaidProducerMail($booking->fresh()->load('face.userable', 'producer.userable')))->render();
        $this->assertStringContainsString('110 000 XOF', $producerHtml);
        $this->assertStringContainsString("/producer/bookings/{$booking->uuid}", $producerHtml);
    }

    public function test_paid_email_shows_the_calendar_date_and_never_an_hour(): void
    {
        $booking = $this->booking(BookingStatus::Paid)->load('face.userable', 'producer.userable');

        $html = (new BookingPaidFaceMail($booking))->render();

        $this->assertStringContainsString(now()->addDays(5)->locale('fr')->translatedFormat('l d F Y'), $html);
        $this->assertStringNotContainsString('01h00', $html);
        $this->assertStringNotContainsString(' à ', strip_tags(explode('Date du tournage', $html)[1] ?? ''));
    }

    public function test_replaying_mark_as_paid_sends_no_duplicate_emails(): void
    {
        Mail::fake();

        $booking = $this->booking(BookingStatus::Accepted, ['accepted_at' => now()]);

        app(BookingService::class)->markAsPaid($booking, 'ref-replay');
        app(BookingService::class)->markAsPaid($booking->fresh(), 'ref-replay');

        Mail::assertQueued(BookingPaidFaceMail::class, 1);
        Mail::assertQueued(BookingPaidProducerMail::class, 1);
    }

    public function test_payment_that_expires_the_booking_emails_the_face_but_not_the_producer_the_expiry(): void
    {
        Mail::fake();

        // Accepté il y a plus de 24 h, cron pas passé : markAsPaid l'expire puis crédite le wallet.
        $booking = $this->booking(BookingStatus::Accepted, ['accepted_at' => now()->subHours(30)]);

        app(BookingService::class)->markAsPaid($booking, 'ref-expire-here');

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        Mail::assertQueued(WalletCreditedMail::class, 1);
        Mail::assertQueued(BookingExpiredMail::class, 1);
        Mail::assertQueued(BookingExpiredMail::class, fn (BookingExpiredMail $mail): bool => ! $mail->forProducer && $mail->hasTo('face@example.test'));
        Mail::assertNotQueued(BookingExpiredMail::class, fn (BookingExpiredMail $mail): bool => $mail->forProducer);
        // L'in-app Producteur reste inchangée.
        $this->assertDatabaseHas('notifications', ['user_id' => $this->producerUser->id, 'type' => 'booking_expired']);
    }

    public function test_late_payment_credited_to_the_wallet_does_not_send_the_paid_emails(): void
    {
        Mail::fake();

        $booking = $this->booking(BookingStatus::Expired, ['accepted_at' => now()->subDays(2)]);

        app(BookingService::class)->markAsPaid($booking, 'ref-late');

        Mail::assertQueued(WalletCreditedMail::class, 1);
        Mail::assertNotQueued(BookingPaidFaceMail::class);
        Mail::assertNotQueued(BookingPaidProducerMail::class);
    }

    public function test_paid_email_failure_never_blocks_the_payment(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $booking = $this->booking(BookingStatus::Accepted, ['accepted_at' => now()]);

        $paid = app(BookingService::class)->markAsPaid($booking, 'ref-down');

        $this->assertSame(BookingStatus::Paid, $paid->status);
        $this->assertDatabaseHas('escrow_transactions', ['booking_id' => $booking->id, 'status' => 'locked']);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->faceUser->id, 'type' => 'booking_paid']);
    }

    public function test_ugc_commission_paid_sends_only_the_producer_receipt_not_the_cash_paid_mails(): void
    {
        Mail::fake();

        $booking = $this->booking(BookingStatus::CommissionPaid, [
            'type_contenu' => 'UGC',
            'commission_paid_at' => now(),
            'nom_produit' => 'Sneakers',
            'montant_total_producteur' => 5000,
            'montant_face_recoit' => 0,
        ]);

        BookingCommissionPaid::dispatch($booking);

        Mail::assertQueued(BookingPaidProducerMail::class, 1);
        Mail::assertQueued(
            BookingPaidProducerMail::class,
            fn (BookingPaidProducerMail $mail): bool => $mail->hasTo('studio@example.test') && $mail->ugc,
        );
        // La Face garde son e-mail UGC existant (UgcCommissionPaidMail) : pas de BookingPaidFaceMail en plus.
        Mail::assertNotQueued(BookingPaidFaceMail::class);
    }

    // ------------------------------------------------------------------
    // 2. Booking expiré
    // ------------------------------------------------------------------

    public function test_expired_booking_emails_both_parties_with_the_reason(): void
    {
        $cases = [
            BookingExpiredMail::KIND_FACE_NO_RESPONSE => $this->booking(BookingStatus::Expired, ['accepted_at' => null]),
            BookingExpiredMail::KIND_PRODUCER_NO_PAYMENT => $this->booking(BookingStatus::Expired, ['accepted_at' => now()->subDay()]),
            BookingExpiredMail::KIND_UGC_COMMISSION => $this->booking(BookingStatus::Expired, ['type_contenu' => 'UGC', 'commission_paid_at' => null]),
        ];

        foreach ($cases as $kind => $booking) {
            Mail::fake();

            BookingExpired::dispatch($booking);

            Mail::assertQueued(BookingExpiredMail::class, 2);
            Mail::assertQueued(
                BookingExpiredMail::class,
                fn (BookingExpiredMail $mail): bool => $mail->kind === $kind && $mail->forProducer && $mail->hasTo('studio@example.test'),
            );
            Mail::assertQueued(
                BookingExpiredMail::class,
                fn (BookingExpiredMail $mail): bool => $mail->kind === $kind && ! $mail->forProducer && $mail->hasTo('face@example.test'),
            );
            // Aucun doublon avec le refus automatique (autre événement).
            Mail::assertNotQueued(BookingRefusedMail::class);
        }

        // Textes selon le motif.
        $html = fn (string $kind, bool $forProducer): string => (new BookingExpiredMail(
            $cases[$kind]->load('face.userable', 'producer.userable'),
            $kind,
            $forProducer,
        ))->render();
        $this->assertStringContainsString("n'a pas répondu", $html(BookingExpiredMail::KIND_FACE_NO_RESPONSE, true));
        $this->assertStringContainsString("n'a pas payé dans les 24 h", $html(BookingExpiredMail::KIND_PRODUCER_NO_PAYMENT, false));
        $this->assertStringContainsString('commission', $html(BookingExpiredMail::KIND_UGC_COMMISSION, true));
    }

    public function test_expired_email_failure_never_blocks_the_expiry(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $booking = $this->booking(BookingStatus::Accepted, ['accepted_at' => now()->subHours(30)]);

        app(BookingService::class)->expire($booking);

        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->faceUser->id, 'type' => 'booking_expired']);
    }

    // ------------------------------------------------------------------
    // 3. Le Producteur a confirmé la fin
    // ------------------------------------------------------------------

    public function test_producer_confirmation_emails_the_face_with_the_automatic_payment_date(): void
    {
        Mail::fake();

        $booking = $this->booking(BookingStatus::ConfirmedByProducer, [
            'date_debut' => now()->startOfDay(),
            'date_fin' => now()->startOfDay(),
        ]);

        BookingPartiallyConfirmed::dispatch($booking, $this->producerUser);

        // date_fin (10/10, minuit) + 72 h = 13/10.
        Mail::assertQueued(BookingProducerConfirmedMail::class, 1);
        Mail::assertQueued(
            BookingProducerConfirmedMail::class,
            fn (BookingProducerConfirmedMail $mail): bool => $mail->hasTo('face@example.test') && $mail->dueAt === '13/10/2026',
        );
        $html = (new BookingProducerConfirmedMail($booking->load('producer.userable'), '13/10/2026'))->render();
        $this->assertStringContainsString('13/10/2026', $html);
        $this->assertStringContainsString("/face/bookings/{$booking->uuid}", $html);
    }

    public function test_producer_confirmation_email_has_no_date_when_the_deadline_is_past(): void
    {
        Mail::fake();

        $booking = $this->booking(BookingStatus::ConfirmedByProducer, [
            'date_debut' => now()->subDays(6)->startOfDay(),
            'date_fin' => now()->subDays(5)->startOfDay(),
        ]);

        BookingPartiallyConfirmed::dispatch($booking, $this->producerUser);

        Mail::assertQueued(BookingProducerConfirmedMail::class, fn (BookingProducerConfirmedMail $mail): bool => $mail->dueAt === null);
    }

    public function test_face_confirmation_sends_no_producer_confirmed_email_to_the_face(): void
    {
        Mail::fake();

        $booking = $this->booking(BookingStatus::ConfirmedByFace, [
            'date_debut' => now()->startOfDay(),
            'date_fin' => now()->startOfDay(),
            'face_confirmed_at' => now(),
        ]);

        BookingPartiallyConfirmed::dispatch($booking, $this->faceUser);

        Mail::assertNotQueued(BookingProducerConfirmedMail::class);
    }

    public function test_producer_confirmation_email_failure_never_blocks_the_confirmation(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $booking = $this->booking(BookingStatus::Paid, [
            'date_debut' => now()->startOfDay(),
            'date_fin' => now()->startOfDay(),
        ]);

        $confirmed = app(BookingService::class)->confirm($booking, $this->producerUser);

        $this->assertSame(BookingStatus::ConfirmedByProducer, $confirmed->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->faceUser->id, 'type' => 'booking_confirmation_pending']);
    }

    // ------------------------------------------------------------------
    // 5. Issue d'une contestation : exactement UN e-mail par partie
    // ------------------------------------------------------------------

    /**
     * Nombre d'e-mails d'issue/crédit mis en file pour une adresse (hors e-mails sans rapport avec le litige).
     */
    private function disputeMailsTo(string $email): int
    {
        $count = 0;

        foreach ([WalletCreditedMail::class, WalletCreditedFaceMail::class, BookingDisputeSettledMail::class] as $class) {
            $count += Mail::queued($class, fn ($mail): bool => $mail->hasTo($email))->count();
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function disputedBooking(BookingStatus $status, array $attributes = []): Booking
    {
        $booking = $this->booking($status, array_merge([
            'date_debut' => now()->subDay()->startOfDay(),
            'date_fin' => now()->subDay()->startOfDay(),
            'settlement_due_at' => now()->subHour(),
        ], $attributes));

        EscrowTransaction::factory()->create([
            'booking_id' => $booking->id,
            'amount' => 90000,
            'status' => 'locked',
        ]);

        return $booking;
    }

    public function test_favor_producer_no_show_gives_each_party_exactly_one_email(): void
    {
        Mail::fake();

        $booking = $this->disputedBooking(BookingStatus::NoShow);

        app(BookingService::class)->settleDispute($booking, DisputeResolutionOutcome::FavorProducer);

        $this->assertSame(1, $this->disputeMailsTo('studio@example.test'));
        $this->assertSame(1, $this->disputeMailsTo('face@example.test'));
        Mail::assertQueued(WalletCreditedMail::class, 1);
        Mail::assertQueued(
            BookingDisputeSettledMail::class,
            fn (BookingDisputeSettledMail $mail): bool => $mail->hasTo('face@example.test') && ! $mail->forProducer && $mail->penalty && ! $mail->contested,
        );
        $html = (new BookingDisputeSettledMail($booking->fresh()->load('face.userable', 'producer.userable'), false, DisputeResolutionOutcome::FavorProducer, false, true))->render();
        $this->assertStringContainsString('Une pénalité a été appliquée', $html);
    }

    public function test_favor_producer_late_cancellation_gives_each_party_exactly_one_email(): void
    {
        Mail::fake();

        $booking = $this->disputedBooking(BookingStatus::CancelledByProducer, ['cancellation_reason' => 'other']);

        app(BookingService::class)->settleDispute($booking, DisputeResolutionOutcome::FavorProducer);

        $this->assertSame(1, $this->disputeMailsTo('studio@example.test'));
        $this->assertSame(1, $this->disputeMailsTo('face@example.test'));
        Mail::assertQueued(
            BookingDisputeSettledMail::class,
            fn (BookingDisputeSettledMail $mail): bool => $mail->hasTo('face@example.test') && ! $mail->penalty,
        );
    }

    public function test_favor_face_no_show_gives_each_party_exactly_one_email(): void
    {
        Mail::fake();

        $booking = $this->disputedBooking(BookingStatus::NoShow, ['disputed_at' => now()->subHour(), 'settlement_due_at' => now()->addHours(10)]);

        app(BookingService::class)->settleDispute($booking, DisputeResolutionOutcome::FavorFace, Admin::factory()->create(), 'Preuves de présence.');

        // Face : e-mail de crédit (via BookingCompleted) avec la phrase d'issue ; Producteur : e-mail d'issue.
        $this->assertSame(1, $this->disputeMailsTo('face@example.test'));
        $this->assertSame(1, $this->disputeMailsTo('studio@example.test'));
        Mail::assertQueued(
            WalletCreditedFaceMail::class,
            fn (WalletCreditedFaceMail $mail): bool => str_contains((string) $mail->outcomeNote, "L'administrateur a tranché en ta faveur"),
        );
        Mail::assertQueued(
            BookingDisputeSettledMail::class,
            fn (BookingDisputeSettledMail $mail): bool => $mail->hasTo('studio@example.test') && $mail->forProducer && $mail->contested,
        );
    }

    public function test_favor_face_late_cancellation_gives_each_party_exactly_one_email(): void
    {
        Mail::fake();

        $booking = $this->disputedBooking(BookingStatus::CancelledByProducer, [
            'cancellation_reason' => 'other',
            'disputed_at' => now()->subHour(),
            'settlement_due_at' => now()->addHours(10),
        ]);

        app(BookingService::class)->settleDispute($booking, DisputeResolutionOutcome::FavorFace, Admin::factory()->create(), 'Annulation trop tardive.');

        $this->assertSame(1, $this->disputeMailsTo('face@example.test'));
        $this->assertSame(1, $this->disputeMailsTo('studio@example.test'));
        Mail::assertQueued(WalletCreditedFaceMail::class, 1);
        Mail::assertQueued(BookingDisputeSettledMail::class, fn (BookingDisputeSettledMail $mail): bool => $mail->hasTo('studio@example.test'));
    }

    public function test_winner_without_credit_gets_the_outcome_email_so_nobody_is_left_without(): void
    {
        Mail::fake();

        $booking = $this->booking(BookingStatus::NoShow, ['disputed_at' => now()->subHour()]);

        // Montant crédité nul : aucun e-mail de crédit possible, les deux parties reçoivent l'e-mail d'issue.
        BookingDisputeSettled::dispatch($booking, DisputeResolutionOutcome::FavorProducer, 0);

        $this->assertSame(1, $this->disputeMailsTo('studio@example.test'));
        $this->assertSame(1, $this->disputeMailsTo('face@example.test'));
        Mail::assertNotQueued(WalletCreditedMail::class);
    }

    public function test_favor_face_no_show_with_zero_amount_gives_each_party_one_outcome_email(): void
    {
        Mail::fake();

        $booking = $this->booking(BookingStatus::Completed, ['disputed_at' => now()->subHour()]);

        // Absence tranchée en faveur de la Face, montant crédité nul : aucun e-mail de crédit, un e-mail d'issue chacun.
        BookingDisputeSettled::dispatch($booking, DisputeResolutionOutcome::FavorFace, 0);

        $this->assertSame(1, $this->disputeMailsTo('studio@example.test'));
        $this->assertSame(1, $this->disputeMailsTo('face@example.test'));
        Mail::assertNotQueued(WalletCreditedFaceMail::class);
        Mail::assertQueued(BookingDisputeSettledMail::class, 2);
    }

    public function test_dispute_outcome_email_failure_never_blocks_the_settlement(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $booking = $this->disputedBooking(BookingStatus::NoShow);

        app(BookingService::class)->settleDispute($booking, DisputeResolutionOutcome::FavorProducer);

        $this->assertSame(110000, $this->producerUser->fresh()->balance);
        $this->assertNotNull($booking->fresh()->dispute_resolved_at);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->faceUser->id, 'type' => 'booking_dispute_settled']);
    }
}
