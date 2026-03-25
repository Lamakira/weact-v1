<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoReleaseMissionFundsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_auto_completes_only_closed_missions_with_paid_confirmed_selection(): void
    {
        $producer = Producer::factory()->create();
        $producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);

        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $eligibleMission = Mission::factory()->closed()->create([
            'producer_id' => $producer->id,
            'date_tournage' => now()->subDays(15)->format('Y-m-d'),
        ]);

        $candidature = Candidature::factory()->create([
            'mission_id' => $eligibleMission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

        $payment = MissionPayment::create([
            'mission_id' => $eligibleMission->id,
            'producer_id' => $producer->id,
            'nombre_faces_retenues' => 1,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000,
            'commission_producteur' => 10000,
            'montant_total_producteur' => 110000,
            'commission_faces_total' => 10000,
            'montant_total_faces' => 90000,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now()->subDays(15),
        ]);

        MissionPaymentCandidature::create([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now()->subDays(15),
        ]);

        $ineligibleMission = Mission::factory()->closed()->create([
            'producer_id' => $producer->id,
            'date_tournage' => now()->subDays(15)->format('Y-m-d'),
        ]);

        $this->artisan('missions:auto-release-funds')
            ->assertExitCode(0);

        $this->assertDatabaseHas('missions', [
            'id' => $eligibleMission->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('missions', [
            'id' => $ineligibleMission->id,
            'status' => 'closed',
        ]);

        $faceUser->refresh();
        $this->assertSame(90000, $faceUser->balance);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $faceUser->id,
            'type' => 'mission_completed',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $producerUser->id,
            'type' => 'mission_completed_producer',
        ]);
    }
}
