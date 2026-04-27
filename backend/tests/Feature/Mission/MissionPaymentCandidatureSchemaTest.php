<?php

declare(strict_types=1);

namespace Tests\Feature\Mission;

use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MissionPaymentCandidatureSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function createPaymentEntry(array $overrides = []): MissionPaymentCandidature
    {
        $producer = Producer::factory()->create();
        $face = Face::factory()->create();

        $mission = Mission::factory()->closed()->create([
            'producer_id' => $producer->id,
        ]);

        $candidature = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

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
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $attributes = array_merge([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => EscrowStatus::Locked,
            'locked_at' => now(),
        ], $overrides);

        return MissionPaymentCandidature::create($attributes);
    }

    public function test_mission_payment_candidatures_table_has_attendance_status_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('mission_payment_candidatures', 'attendance_status'),
            "Column 'attendance_status' should exist in mission_payment_candidatures table"
        );
    }

    public function test_attendance_status_defaults_to_pending_in_database(): void
    {
        $entry = $this->createPaymentEntry();

        $this->assertTrue(Schema::hasColumn('mission_payment_candidatures', 'attendance_status'));

        $this->assertDatabaseHas('mission_payment_candidatures', [
            'id' => $entry->id,
            'attendance_status' => 'pending',
        ]);

        $entry->refresh();

        $this->assertSame(AttendanceStatus::Pending, $entry->attendance_status);
    }

    public function test_attendance_status_enum_casting_works(): void
    {
        $entry = $this->createPaymentEntry([
            'attendance_status' => AttendanceStatus::Present,
        ]);

        $entry->refresh();

        $this->assertInstanceOf(AttendanceStatus::class, $entry->attendance_status);
        $this->assertSame(AttendanceStatus::Present, $entry->attendance_status);
    }

    public function test_attendance_status_accepts_all_four_enum_values(): void
    {
        foreach (AttendanceStatus::cases() as $case) {
            $entry = $this->createPaymentEntry([
                'attendance_status' => $case,
            ]);

            $this->assertDatabaseHas('mission_payment_candidatures', [
                'id' => $entry->id,
                'attendance_status' => $case->value,
            ]);
        }
    }

    public function test_attendance_status_rejects_invalid_value(): void
    {
        $producer = Producer::factory()->create();
        $face = Face::factory()->create();

        $mission = Mission::factory()->closed()->create([
            'producer_id' => $producer->id,
        ]);

        $candidature = Candidature::factory()->create([
            'face_id' => $face->id,
            'mission_id' => $mission->id,
            'status' => CandidatureStatus::Confirmed,
        ]);

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
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        \DB::table('mission_payment_candidatures')->insert([
            'mission_payment_id' => $payment->id,
            'candidature_id' => $candidature->id,
            'face_id' => $face->id,
            'montant_face_recoit' => 90000,
            'escrow_status' => 'locked',
            'attendance_status' => 'invalid_value',
            'locked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
