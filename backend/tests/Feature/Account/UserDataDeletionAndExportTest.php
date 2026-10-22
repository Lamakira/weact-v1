<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Candidature;
use App\Models\Conversation;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Message;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Account deletion money guard + scrubbing, and data export completeness
 * (UserDataController::destroy / export). Existing coverage of the same endpoints
 * lives in AccountDeletionReRegistrationTest, NullPasswordAccountTest and
 * ProducerWhatsappTest (all left untouched); every test here is new.
 */
class UserDataDeletionAndExportTest extends TestCase
{
    use RefreshDatabase;

    private function faceUser(): User
    {
        $face = Face::factory()->withPersonalInfo()->create([
            'bio' => 'Ma bio',
            'ville' => 'Cotonou',
            'pays' => 'Bénin',
            'taille' => 175,
            'poids' => 65,
            'langues' => ['Français', 'Fon'],
            'categories' => ['acteur'],
            'niches' => ['mode'],
            'tarif_horaire' => 5000,
            'tarif_journalier' => 30000,
            'whatsapp_number' => '+22997000001',
        ]);

        return User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'consent_ip' => '203.0.113.7',
        ]);
    }

    private function producerUser(): User
    {
        $producer = Producer::factory()->create(['whatsapp_number' => '+22997000002']);

        return User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
    }

    private function deleteAccount(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)->deleteJson('/api/v1/user/account', ['password' => 'password']);
    }

    private function assertBlocked(User $user, string $messageFragment): void
    {
        $this->deleteAccount($user)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ACCOUNT_DELETION_BLOCKED')
            ->assertJsonPath('error.message', fn (string $m) => str_contains($m, $messageFragment));

        $this->assertTrue($user->fresh()->is_active);
        $this->assertStringNotContainsString('anonymized', $user->fresh()->email);
    }

    // ---------------------------------------------------------------- guard

    public function test_deletion_is_blocked_while_the_wallet_holds_money(): void
    {
        $user = $this->faceUser();
        $user->forceFill(['balance' => 12500])->save();

        $this->assertBlocked($user, 'Votre portefeuille contient encore 12 500 F CFA');
    }

    public function test_deletion_is_blocked_by_a_pending_withdrawal(): void
    {
        $user = $this->faceUser();
        WithdrawalRequest::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $this->assertBlocked($user, 'demande de retrait');
    }

    public function test_a_processed_withdrawal_does_not_block_deletion(): void
    {
        $user = $this->faceUser();
        WithdrawalRequest::factory()->approved()->create(['user_id' => $user->id]);

        $this->deleteAccount($user)->assertOk();
    }

    public function test_deletion_is_blocked_by_a_booking_with_locked_escrow_for_the_face(): void
    {
        $face = $this->faceUser();
        $booking = Booking::factory()->create(['face_id' => $face->id, 'status' => 'paid']);
        EscrowTransaction::factory()->create(['booking_id' => $booking->id, 'status' => 'locked']);

        $this->assertBlocked($face, 'argent en jeu');
    }

    public function test_deletion_is_blocked_by_a_booking_with_locked_escrow_for_the_producer(): void
    {
        $producer = $this->producerUser();
        $booking = Booking::factory()->create(['producer_id' => $producer->id, 'status' => 'paid']);
        EscrowTransaction::factory()->create(['booking_id' => $booking->id, 'status' => 'locked']);

        $this->assertBlocked($producer, 'argent en jeu');
    }

    public function test_deletion_is_blocked_by_a_booking_pending_settlement(): void
    {
        $face = $this->faceUser();
        $booking = Booking::factory()->create([
            'face_id' => $face->id,
            'status' => 'no_show',
            'settlement_due_at' => now()->addHours(10),
        ]);
        EscrowTransaction::factory()->create(['booking_id' => $booking->id, 'status' => 'locked']);

        $this->assertBlocked($face, 'argent en jeu');
    }

    public function test_a_booking_whose_escrow_is_released_does_not_block_deletion(): void
    {
        $face = $this->faceUser();
        $booking = Booking::factory()->create(['face_id' => $face->id, 'status' => 'completed']);
        EscrowTransaction::factory()->released()->create(['booking_id' => $booking->id]);

        $this->deleteAccount($face)->assertOk();
    }

    public function test_deletion_is_blocked_by_locked_candidature_escrow_for_the_face(): void
    {
        $faceUser = $this->faceUser();
        $this->lockedCandidatureEscrow(Producer::factory()->create(), (int) $faceUser->userable_id);

        $this->assertBlocked($faceUser, 'argent en jeu');
    }

    public function test_deletion_is_blocked_by_locked_candidature_escrow_for_the_producer(): void
    {
        $producerUser = $this->producerUser();
        $producer = $producerUser->userable;
        $this->assertInstanceOf(Producer::class, $producer);
        $this->lockedCandidatureEscrow($producer, (int) Face::factory()->create()->id);

        $this->assertBlocked($producerUser, 'argent en jeu');
    }

    public function test_the_guard_runs_before_the_password_check(): void
    {
        $user = $this->faceUser();
        $user->forceFill(['balance' => 1000])->save();

        $this->actingAs($user)
            ->deleteJson('/api/v1/user/account', ['password' => 'wrong-password'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ACCOUNT_DELETION_BLOCKED');
    }

    public function test_every_blocker_is_listed_in_the_message(): void
    {
        $user = $this->faceUser();
        $user->forceFill(['balance' => 1000])->save();
        WithdrawalRequest::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $message = (string) $this->deleteAccount($user)->assertStatus(409)->json('error.message');

        $this->assertStringContainsString('portefeuille', $message);
        $this->assertStringContainsString('demande de retrait', $message);
    }

    public function test_deletion_is_allowed_for_a_clean_account(): void
    {
        $user = $this->faceUser();

        $this->deleteAccount($user)->assertOk();

        $this->assertFalse($user->fresh()->is_active);
    }

    // ---------------------------------------------------------------- scrub

    public function test_deleting_a_face_scrubs_the_remaining_personal_data(): void
    {
        $user = $this->faceUser();
        $face = $user->userable;
        $this->assertInstanceOf(Face::class, $face);

        $this->deleteAccount($user)->assertOk();

        $face = $face->fresh();
        $this->assertNotNull($face);
        $this->assertNull($face->sexe);
        $this->assertNull($face->date_naissance);
        $this->assertNull($face->nationalite);
        $this->assertNull($face->pays);
        $this->assertNull($face->ville);
        $this->assertNull($face->taille);
        $this->assertNull($face->poids);
        $this->assertNull($face->langues);
        $this->assertSame([], $face->categories);
        $this->assertSame([], $face->niches);
        $this->assertNull($face->tarif_horaire);
        $this->assertNull($face->tarif_journalier);
        $this->assertNull($face->bio);
        $this->assertNull($face->whatsapp_number);
        $this->assertNull($user->fresh()->consent_ip);
    }

    public function test_deleting_a_producer_replaces_the_slug_and_clears_contact_data(): void
    {
        $user = $this->producerUser();
        $producer = $user->userable;
        $this->assertInstanceOf(Producer::class, $producer);
        $oldSlug = $producer->slug;

        $this->deleteAccount($user)->assertOk();

        $producer = $producer->fresh();
        $this->assertNotNull($producer);
        $this->assertSame('deleted-'.$producer->id, $producer->slug);
        $this->assertNotSame($oldSlug, $producer->slug);
        $this->assertNull($producer->whatsapp_number);
    }

    public function test_deletion_masks_withdrawal_phones_but_keeps_the_financial_rows(): void
    {
        $user = $this->faceUser();
        $withdrawal = WithdrawalRequest::factory()->approved()->create([
            'user_id' => $user->id,
            'amount' => 40000,
            'phone_number' => '97123456',
        ]);
        $wallet = WalletTransaction::factory()->create(['user_id' => $user->id, 'amount' => 777]);

        $this->deleteAccount($user)->assertOk();

        $withdrawal = $withdrawal->fresh();
        $this->assertNotNull($withdrawal);
        $this->assertSame(40000, $withdrawal->amount);
        $this->assertStringNotContainsString('97123456', $withdrawal->phone_number);
        $this->assertSame('[supprimé]', $withdrawal->phone_number);
        $this->assertNotNull(WalletTransaction::find($wallet->id));
        $this->assertSame(777, $wallet->fresh()?->amount);
    }

    public function test_deletion_replaces_authored_messages_but_not_the_counterparts(): void
    {
        $user = $this->faceUser();
        $other = $this->producerUser();
        $booking = Booking::factory()->create(['face_id' => $user->id, 'producer_id' => $other->id, 'status' => 'completed']);

        $mine = BookingMessage::factory()->create(['booking_id' => $booking->id, 'sender_id' => $user->id, 'content' => 'secret face']);
        $theirs = BookingMessage::factory()->create(['booking_id' => $booking->id, 'sender_id' => $other->id, 'content' => 'reponse producteur']);

        $conversation = $this->conversationFor($user, $other);
        $myChat = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $user->id, 'sender_type' => User::class, 'content' => 'mon chat']);
        $theirChat = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id, 'sender_type' => User::class, 'content' => 'chat producteur']);

        $this->deleteAccount($user)->assertOk();

        $this->assertSame('[message supprimé]', $mine->fresh()?->content);
        $this->assertSame('reponse producteur', $theirs->fresh()?->content);
        $this->assertSame('[message supprimé]', $myChat->fresh()?->content);
        $this->assertSame('chat producteur', $theirChat->fresh()?->content);
    }

    // --------------------------------------------------------------- export

    public function test_export_contains_the_new_sections_for_a_face(): void
    {
        $user = $this->faceUser();
        $other = $this->producerUser();
        $booking = Booking::factory()->create(['face_id' => $user->id, 'producer_id' => $other->id, 'status' => 'completed']);
        BookingMessage::factory()->create(['booking_id' => $booking->id, 'sender_id' => $user->id, 'content' => 'bonjour booking']);
        BookingMessage::factory()->create(['booking_id' => $booking->id, 'sender_id' => $other->id, 'content' => 'salut booking']);

        $conversation = $this->conversationFor($user, $other);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $user->id, 'sender_type' => User::class, 'content' => 'bonjour chat']);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id, 'sender_type' => User::class, 'content' => 'salut chat']);

        WalletTransaction::factory()->create(['user_id' => $user->id, 'amount' => 4242, 'reference' => 'wlt_export_test']);
        WithdrawalRequest::factory()->create(['user_id' => $user->id, 'phone_number' => '97111222', 'status' => 'approved']);
        Notification::create(['user_id' => $user->id, 'type' => 'test_type', 'data' => ['x' => 1]]);

        $response = $this->actingAs($user)->getJson('/api/v1/user/data-export')->assertOk();

        $response->assertJsonPath('data.account.consent_ip', '203.0.113.7');
        $this->assertCount(2, $response->json('data.booking_messages'));
        $this->assertCount(2, $response->json('data.conversation_messages'));
        $this->assertSame(['bonjour booking', 'salut booking'], array_column($response->json('data.booking_messages'), 'content'));
        $this->assertSame([true, false], array_column($response->json('data.conversation_messages'), 'from_me'));
        $this->assertSame('wlt_export_test', $response->json('data.wallet_transactions.0.reference'));
        $this->assertSame('97111222', $response->json('data.withdrawal_requests.0.phone_number'));
        $this->assertSame('test_type', $response->json('data.notifications.0.type'));
        $this->assertArrayHasKey('subscriptions', $response->json('data'));
        $this->assertArrayHasKey('media', $response->json('data'));
        $this->assertArrayHasKey('profile_photo_url', $response->json('data.profile'));
    }

    public function test_export_for_a_producer_lists_mission_payments_and_their_conversation_messages(): void
    {
        $producerUser = $this->producerUser();
        $faceUser = $this->faceUser();
        $producer = $producerUser->userable;
        $this->assertInstanceOf(Producer::class, $producer);

        $conversation = $this->conversationFor($faceUser, $producerUser);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $faceUser->id, 'sender_type' => User::class, 'content' => 'message de la face']);

        MissionPayment::create([
            'mission_id' => Mission::factory()->create(['producer_id' => $producer->id])->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($producerUser)->getJson('/api/v1/user/data-export')->assertOk();

        $this->assertSame(110000, $response->json('data.mission_payments.0.montant_total_producteur'));
        $this->assertSame('message de la face', $response->json('data.conversation_messages.0.content'));
        $this->assertFalse($response->json('data.conversation_messages.0.from_me'));
    }

    public function test_export_never_leaks_another_users_data(): void
    {
        $user = $this->faceUser();
        $other = $this->producerUser();
        $other->update(['email' => 'autre.personne@example.com']);
        $other->userable?->update(['whatsapp_number' => '+22999887766']);

        $booking = Booking::factory()->create(['face_id' => $user->id, 'producer_id' => $other->id, 'status' => 'completed']);
        BookingMessage::factory()->create(['booking_id' => $booking->id, 'sender_id' => $other->id, 'content' => 'salut']);
        $conversation = $this->conversationFor($user, $other);
        Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $other->id, 'sender_type' => User::class, 'content' => 'salut chat']);

        // A stranger's data that must never appear.
        $stranger = $this->faceUser();
        $strangerBooking = Booking::factory()->create(['face_id' => $stranger->id, 'status' => 'completed']);
        BookingMessage::factory()->create(['booking_id' => $strangerBooking->id, 'sender_id' => $stranger->id, 'content' => 'message etranger']);
        $strangerConversation = $this->conversationFor($stranger, $this->producerUser());
        Message::factory()->create(['conversation_id' => $strangerConversation->id, 'sender_id' => $stranger->id, 'sender_type' => User::class, 'content' => 'chat etranger']);
        WalletTransaction::factory()->create(['user_id' => $stranger->id, 'reference' => 'wlt_stranger']);
        WithdrawalRequest::factory()->create(['user_id' => $stranger->id, 'phone_number' => '90909090']);
        Notification::create(['user_id' => $stranger->id, 'type' => 'stranger_type', 'data' => ['x' => 1]]);

        $json = (string) $this->actingAs($user)->getJson('/api/v1/user/data-export')->assertOk()->getContent();

        foreach (['autre.personne@example.com', '22999887766', 'message etranger', 'chat etranger', 'wlt_stranger', '90909090', 'stranger_type', $stranger->email] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
    }

    public function test_deletion_is_blocked_by_a_paid_ugc_commission_on_a_published_mission(): void
    {
        $producerUser = $this->producerUser();
        $mission = Mission::factory()->published()->create([
            'producer_id' => $producerUser->userable_id,
            'type_mission' => 'ugc',
            'commission_paid_at' => now(),
        ]);

        $this->assertBlocked($producerUser, 'commission UGC');

        // Refunded, or mission terminal: no longer blocking.
        $mission->update(['commission_refunded_at' => now()]);
        $this->deleteAccount($producerUser)->assertOk();
    }

    public function test_deletion_is_blocked_by_a_ugc_booking_in_commission_paid(): void
    {
        $producerUser = $this->producerUser();
        $booking = Booking::factory()->create([
            'producer_id' => $producerUser->id,
            'status' => 'commission_paid',
            'commission_paid_at' => now(),
        ]);

        $this->assertBlocked($producerUser, 'commission UGC');

        $booking->update(['status' => 'expired', 'commission_refunded_at' => now()]);
        $this->deleteAccount($producerUser)->assertOk();
    }

    public function test_deletion_is_blocked_by_pending_candidature_escrow_with_a_fedapay_transaction(): void
    {
        $faceUser = $this->faceUser();
        $entry = $this->lockedCandidatureEscrow(Producer::factory()->create(), (int) $faceUser->userable_id);
        $entry->update(['escrow_status' => 'pending', 'fedapay_transaction_id' => '998877']);

        $this->assertBlocked($faceUser, 'argent en jeu');

        $entry->update(['fedapay_transaction_id' => null]);
        $this->deleteAccount($faceUser)->assertOk();
    }

    public function test_deletion_is_blocked_by_a_booking_whose_fedapay_checkout_started(): void
    {
        $producerUser = $this->producerUser();
        Booking::factory()->create([
            'producer_id' => $producerUser->id,
            'status' => 'accepted',
            'fedapay_transaction_id' => 123456,
        ]);

        $this->assertBlocked($producerUser, 'argent en jeu');
    }

    public function test_deletion_is_blocked_by_a_pending_mission_payment_with_a_fedapay_transaction(): void
    {
        $producerUser = $this->producerUser();
        $producer = $producerUser->userable;
        $this->assertInstanceOf(Producer::class, $producer);

        MissionPayment::create([
            'mission_id' => Mission::factory()->published()->create(['producer_id' => $producer->id])->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'fedapay_transaction_id' => '555444',
            'status' => 'pending',
        ]);

        $this->assertBlocked($producerUser, 'argent en jeu');
    }

    public function test_deletion_is_blocked_by_a_pending_wallet_debit(): void
    {
        $user = $this->faceUser();
        WalletTransaction::factory()->debit()->create(['user_id' => $user->id, 'status' => 'pending']);

        $this->assertBlocked($user, 'demande de retrait');
    }

    public function test_deletion_masks_the_phone_in_withdrawal_wallet_descriptions(): void
    {
        $user = $this->faceUser();
        $tx = WalletTransaction::factory()->debit()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'amount' => 5000,
            'description' => 'Retrait vers mtn — 97123456',
        ]);

        $this->deleteAccount($user)->assertOk();

        $tx = $tx->fresh();
        $this->assertNotNull($tx);
        $this->assertStringNotContainsString('97123456', (string) $tx->description);
        $this->assertSame(5000, $tx->amount);
    }

    public function test_deleting_a_face_clears_its_candidature_motivation_messages(): void
    {
        $user = $this->faceUser();
        $candidature = Candidature::factory()->create([
            'face_id' => $user->userable_id,
            'message_motivation' => 'Mon texte perso',
        ]);

        $this->deleteAccount($user)->assertOk();

        $this->assertNull($candidature->fresh()?->message_motivation);
    }

    public function test_deleting_a_producer_closes_its_open_missions_without_money(): void
    {
        $producerUser = $this->producerUser();
        $published = Mission::factory()->published()->create(['producer_id' => $producerUser->userable_id]);
        $draft = Mission::factory()->draft()->create(['producer_id' => $producerUser->userable_id]);
        $completed = Mission::factory()->completed()->create(['producer_id' => $producerUser->userable_id]);

        $this->deleteAccount($producerUser)->assertOk();

        $this->assertSame('closed', $published->fresh()?->status->value);
        $this->assertSame('closed', $draft->fresh()?->status->value);
        $this->assertSame('completed', $completed->fresh()?->status->value);
    }

    public function test_export_for_a_face_lists_mission_earnings_ratings_and_booking_uuid(): void
    {
        $faceUser = $this->faceUser();
        $this->lockedCandidatureEscrow(Producer::factory()->create(), (int) $faceUser->userable_id);
        $booking = Booking::factory()->create(['face_id' => $faceUser->id, 'status' => 'completed']);

        $response = $this->actingAs($faceUser)->getJson('/api/v1/user/data-export')->assertOk();

        $this->assertSame(90000, $response->json('data.mission_earnings.0.montant_face_recoit'));
        $this->assertSame('locked', $response->json('data.mission_earnings.0.escrow_status'));
        $this->assertSame($booking->uuid, $response->json('data.bookings.0.uuid'));
        $this->assertArrayHasKey('ratings_received', $response->json('data'));
        $this->assertArrayHasKey('message_motivation', $response->json('data.candidatures.0'));
    }

    // -------------------------------------------------------------- fixtures

    /**
     * Conversation between a Face user and a Producer user (candidature on one of
     * the producer's missions).
     */
    private function conversationFor(User $faceUser, User $producerUser): Conversation
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $producerUser->userable_id]);
        $candidature = Candidature::factory()->accepted()->create([
            'face_id' => $faceUser->userable_id,
            'mission_id' => $mission->id,
        ]);

        return Conversation::factory()->create(['candidature_id' => $candidature->id]);
    }

    private function lockedCandidatureEscrow(Producer $producer, int $faceId): MissionPaymentCandidature
    {
        $mission = Mission::factory()->published()->create(['producer_id' => $producer->id]);
        $candidature = Candidature::factory()->accepted()->create(['face_id' => $faceId, 'mission_id' => $mission->id]);
        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => 'paid',
        ]);

        return MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $faceId,
            'montant_face_recoit' => 90000,
            'escrow_status' => 'locked',
            'locked_at' => now(),
        ]);
    }
}
