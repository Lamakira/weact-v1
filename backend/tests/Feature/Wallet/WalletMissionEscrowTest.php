<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\EscrowTransaction;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lot B7 — le wallet de la Face montre aussi l'argent de ses missions en séquestre
 * (entries Locked d'une place acceptée, confirmée ou en cours), pas seulement celui des
 * bookings. Une absence en fenêtre de contestation compte « en litige », comme un booking.
 */
class WalletMissionEscrowTest extends TestCase
{
    use RefreshDatabase;

    private Face $face;

    private User $faceUser;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
            'balance' => 0,
        ]);

        $this->producer = Producer::factory()->create();
        User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $this->producer->id]);
    }

    private function entry(
        CandidatureStatus $candidatureStatus,
        EscrowStatus $escrowStatus = EscrowStatus::Locked,
        int $amount = 90000,
        ?AttendanceStatus $attendance = null,
        ?Face $face = null,
    ): MissionPaymentCandidature {
        $face ??= $this->face;
        $mission = Mission::factory()->closed()->create(['producer_id' => $this->producer->id]);
        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => $amount,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => $candidatureStatus,
        ]);

        return MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => $amount,
            'escrow_status' => $escrowStatus,
            'attendance_status' => $attendance ?? AttendanceStatus::Pending,
            'locked_at' => now(),
        ]);
    }

    private function wallet(): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->faceUser->createToken('t')->plainTextToken)
            ->getJson('/api/v1/wallet')
            ->assertOk();
    }

    public function test_locked_mission_entries_of_accepted_confirmed_or_in_progress_places_count_as_pending(): void
    {
        $this->entry(CandidatureStatus::Accepted, amount: 90000);
        $this->entry(CandidatureStatus::Confirmed, amount: 45000);
        $this->entry(CandidatureStatus::InProgress, amount: 30000);

        $this->wallet()
            ->assertJsonPath('data.pending_escrow', 165000)
            ->assertJsonPath('data.held_in_dispute', 0);
    }

    public function test_mission_and_booking_escrow_add_up(): void
    {
        $this->entry(CandidatureStatus::Confirmed, amount: 90000);

        $booking = Booking::factory()->paid()->create(['face_id' => $this->faceUser->id]);
        EscrowTransaction::create([
            'booking_id' => $booking->id,
            'amount' => 40000,
            'status' => 'locked',
            'locked_at' => now(),
        ]);

        $this->wallet()->assertJsonPath('data.pending_escrow', 130000);
    }

    public function test_released_refunded_or_still_pending_payment_entries_are_not_counted(): void
    {
        $this->entry(CandidatureStatus::Completed, EscrowStatus::Released);
        $this->entry(CandidatureStatus::Expired, EscrowStatus::Refunded);
        $this->entry(CandidatureStatus::Accepted, EscrowStatus::Pending);

        $this->wallet()->assertJsonPath('data.pending_escrow', 0);
    }

    public function test_an_absence_under_contest_window_counts_as_held_in_dispute(): void
    {
        $this->entry(CandidatureStatus::InProgress, amount: 90000, attendance: AttendanceStatus::Absent);
        $this->entry(CandidatureStatus::InProgress, amount: 20000, attendance: AttendanceStatus::Disputed);
        $this->entry(CandidatureStatus::InProgress, amount: 10000, attendance: AttendanceStatus::Pending);

        $this->wallet()
            ->assertJsonPath('data.pending_escrow', 10000)
            ->assertJsonPath('data.held_in_dispute', 110000);
    }

    public function test_another_faces_entries_are_never_counted(): void
    {
        $otherFace = Face::factory()->create();
        User::factory()->create(['userable_type' => Face::class, 'userable_id' => $otherFace->id]);
        $this->entry(CandidatureStatus::Confirmed, amount: 90000, face: $otherFace);

        $this->wallet()->assertJsonPath('data.pending_escrow', 0);
    }

    public function test_a_producer_wallet_never_shows_mission_escrow(): void
    {
        $this->entry(CandidatureStatus::Confirmed);
        $producerUser = User::query()->where('userable_type', Producer::class)->firstOrFail();

        $this->withToken($producerUser->createToken('t')->plainTextToken)
            ->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonPath('data.pending_escrow', 0)
            ->assertJsonPath('data.held_in_dispute', 0);
    }
}
