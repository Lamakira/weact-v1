<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Enums\AttendanceStatus;
use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\FinancialEventType;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Admin;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\FinancialEvent;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceDisputeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
    }

    /**
     * @return array{0: Mission, 1: list<array{entry: MissionPaymentCandidature, face: Face, faceUser: User, candidature: Candidature}>}
     */
    private function createDisputedEntries(
        int $count = 1,
        MissionStatus $missionStatus = MissionStatus::PendingAttendanceValidation,
    ): array {
        /** @var Mission $mission */
        $mission = Mission::factory()->closed()->create([
            'producer_id' => $this->producer->id,
        ]);

        if ($missionStatus !== MissionStatus::Closed) {
            $mission->update(['status' => $missionStatus]);
            $mission->refresh();
        }

        /** @var MissionPayment $payment */
        $payment = MissionPayment::create([
            'mission_id' => $mission->id,
            'producer_id' => $this->producer->id,
            'nombre_faces_retenues' => $count,
            'budget_par_face' => 100000,
            'montant_sous_total' => 100000 * $count,
            'commission_producteur' => 10000 * $count,
            'montant_total_producteur' => 110000 * $count,
            'commission_faces_total' => 10000 * $count,
            'montant_total_faces' => 90000 * $count,
            'status' => MissionPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $faces = [];
        for ($i = 0; $i < $count; $i++) {
            /** @var Face $face */
            $face = Face::factory()->create([
                'prenom' => "Face{$i}",
                'nom' => 'Test',
            ]);

            /** @var User $faceUser */
            $faceUser = User::factory()->create([
                'userable_type' => Face::class,
                'userable_id' => $face->id,
                'email' => "mission{$mission->id}.face{$i}@example.test",
            ]);

            /** @var Candidature $candidature */
            $candidature = Candidature::factory()->create([
                'mission_id' => $mission->id,
                'face_id' => $face->id,
                'status' => CandidatureStatus::Confirmed,
            ]);

            /** @var MissionPaymentCandidature $entry */
            $entry = MissionPaymentCandidature::create([
                'mission_payment_id' => $payment->id,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Locked,
                'attendance_status' => AttendanceStatus::Disputed,
                'locked_at' => now()->subDays(3),
                'notified_at' => now()->subDays(2),
            ]);

            $faces[] = [
                'entry' => $entry,
                'face' => $face,
                'faceUser' => $faceUser,
                'candidature' => $candidature,
            ];
        }

        return [$mission, $faces];
    }

    private function createAdmin(AdminRole $role = AdminRole::Admin): Admin
    {
        return Admin::factory()->create(['role' => $role]);
    }

    private function actingAsAdmin(Admin $admin): static
    {
        return $this->withToken($admin->createToken('admin-test-token')->plainTextToken);
    }

    public function test_index_returns_disputed_entries_only(): void
    {
        [, $disputedFaces] = $this->createDisputedEntries(3);
        [, $absentFaces] = $this->createDisputedEntries(2);
        [, $releasedFaces] = $this->createDisputedEntries(1);

        foreach ($absentFaces as $face) {
            $face['entry']->update(['attendance_status' => AttendanceStatus::Absent]);
        }

        $releasedFaces[0]['entry']->update([
            'attendance_status' => AttendanceStatus::Present,
            'escrow_status' => EscrowStatus::Released,
            'released_at' => now(),
        ]);

        $admin = $this->createAdmin();
        $response = $this->actingAsAdmin($admin)->getJson('/api/v1/admin/attendance-disputes');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('message', 'Litiges récupérés avec succès');

        // Shape assertions on the first item — the eager loading should produce non-null relations.
        $first = $response->json('data.0');
        $this->assertIsArray($first);
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('mission', $first);
        $this->assertArrayHasKey('face', $first);
        $this->assertSame(90000, $first['montant_face_recoit']);
        $this->assertSame('disputed', $first['attendance_status']);
        $this->assertSame('locked', $first['escrow_status']);
        $this->assertNotNull($first['notified_at']);
        $this->assertNotNull($first['disputed_at']);
        $this->assertSame($this->producer->display_name, $first['mission']['producer']['display_name']);
        $this->assertNotEmpty($first['face']['display_name']);

        // Sanity: ensure non-disputed entries are NOT in the payload.
        $payloadIds = array_column($response->json('data'), 'id');
        foreach ($disputedFaces as $face) {
            $this->assertContains($face['entry']->id, $payloadIds);
        }
        foreach ($absentFaces as $face) {
            $this->assertNotContains($face['entry']->id, $payloadIds);
        }
        $this->assertNotContains($releasedFaces[0]['entry']->id, $payloadIds);
    }

    public function test_index_returns_empty_when_no_disputes(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAsAdmin($admin)->getJson('/api/v1/admin/attendance-disputes');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_index_paginates_results_when_more_than_20(): void
    {
        $this->createDisputedEntries(25);
        $admin = $this->createAdmin();

        $response = $this->actingAsAdmin($admin)->getJson('/api/v1/admin/attendance-disputes?page=2');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 25);
    }

    public function test_index_rejects_editor_with_403(): void
    {
        $editor = $this->createAdmin(AdminRole::Editor);

        $response = $this->actingAsAdmin($editor)->getJson('/api/v1/admin/attendance-disputes');

        $response->assertForbidden();
        $this->assertSame('FORBIDDEN', $response->json('error.code'));
    }

    public function test_index_rejects_unauthenticated_with_401(): void
    {
        $response = $this->getJson('/api/v1/admin/attendance-disputes');

        $response->assertUnauthorized();
        $this->assertSame('UNAUTHENTICATED', $response->json('error.code'));
    }

    public function test_index_rejects_non_admin_bearer_token_with_403(): void
    {
        [, $faces] = $this->createDisputedEntries(1);
        $faceUser = $faces[0]['faceUser'];

        $response = $this->withToken($faceUser->createToken('face-test-token')->plainTextToken)
            ->getJson('/api/v1/admin/attendance-disputes');

        $response->assertForbidden();
        $this->assertSame('FORBIDDEN', $response->json('error.code'));
    }

    public function test_resolve_favor_face_credits_face_wallet_with_audit(): void
    {
        [, $faces] = $this->createDisputedEntries(1);
        $admin = $this->createAdmin();
        $entry = $faces[0]['entry'];

        $response = $this->actingAsAdmin($admin)
            ->postJson("/api/v1/admin/attendance-disputes/{$entry->id}/resolve", [
                'outcome' => 'face',
                'notes' => '  Trail vidéo prouve la présence  ',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Litige résolu avec succès')
            ->assertJsonPath('data.id', $entry->id)
            ->assertJsonPath('data.escrow_status', 'released');

        $entry->refresh();
        $this->assertSame(EscrowStatus::Released, $entry->escrow_status);
        $this->assertSame(AttendanceStatus::Disputed, $entry->attendance_status);
        $this->assertSame(90000, $faces[0]['faceUser']->refresh()->balance);
        $this->assertSame(0, $this->producerUser->refresh()->balance);

        /** @var FinancialEvent $event */
        $event = FinancialEvent::where('idempotency_key', "mission_attendance_escrow_release:{$entry->id}")->firstOrFail();
        $this->assertSame(FinancialEventType::EscrowRelease, $event->type);
        $this->assertSame('disputed_resolved_face', $event->metadata['reason'] ?? null);
        $this->assertSame($admin->id, $event->metadata['admin_id'] ?? null);
        $this->assertSame('admin', $event->metadata['admin_role'] ?? null);
        $this->assertSame('Trail vidéo prouve la présence', $event->metadata['admin_notes'] ?? null);
        $this->assertSame('favor_face', $event->metadata['outcome'] ?? null);
    }

    public function test_resolve_favor_producer_credits_producer_wallet_with_audit(): void
    {
        [, $faces] = $this->createDisputedEntries(1);
        $admin = $this->createAdmin(AdminRole::SuperAdmin);
        $entry = $faces[0]['entry'];

        $response = $this->actingAsAdmin($admin)
            ->postJson("/api/v1/admin/attendance-disputes/{$entry->id}/resolve", [
                'outcome' => 'producer',
                'notes' => 'Producer fournit attestation employeur',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.escrow_status', 'refunded');

        $entry->refresh();
        $this->assertSame(EscrowStatus::Refunded, $entry->escrow_status);
        $this->assertSame(90000, $this->producerUser->refresh()->balance);
        $this->assertSame(0, $faces[0]['faceUser']->refresh()->balance);

        /** @var FinancialEvent $event */
        $event = FinancialEvent::where('idempotency_key', "mission_attendance_refund:{$entry->id}")->firstOrFail();
        $this->assertSame(FinancialEventType::Refund, $event->type);
        $this->assertSame('disputed_resolved_producer', $event->metadata['reason'] ?? null);
        $this->assertSame(100, $event->metadata['refund_percentage'] ?? null);
        $this->assertSame($admin->id, $event->metadata['admin_id'] ?? null);
        $this->assertSame('superadmin', $event->metadata['admin_role'] ?? null);
        $this->assertSame('Producer fournit attestation employeur', $event->metadata['admin_notes'] ?? null);
        $this->assertSame('favor_producer', $event->metadata['outcome'] ?? null);
    }

    public function test_resolve_rejects_editor_with_403(): void
    {
        [, $faces] = $this->createDisputedEntries(1);
        $editor = $this->createAdmin(AdminRole::Editor);
        $entry = $faces[0]['entry'];

        $response = $this->actingAsAdmin($editor)
            ->postJson("/api/v1/admin/attendance-disputes/{$entry->id}/resolve", [
                'outcome' => 'face',
                'notes' => 'Note valide',
            ]);

        $response->assertForbidden();
        $this->assertSame('FORBIDDEN', $response->json('error.code'));
        $this->assertSame(EscrowStatus::Locked, $entry->fresh()->escrow_status);
    }

    public function test_resolve_rejects_non_admin_bearer_token_with_403(): void
    {
        [, $faces] = $this->createDisputedEntries(1);
        $faceUser = $faces[0]['faceUser'];
        $entry = $faces[0]['entry'];

        $response = $this->withToken($faceUser->createToken('face-test-token')->plainTextToken)
            ->postJson("/api/v1/admin/attendance-disputes/{$entry->id}/resolve", [
                'outcome' => 'face',
                'notes' => 'Note valide',
            ]);

        $response->assertForbidden();
        $this->assertSame('FORBIDDEN', $response->json('error.code'));
        $this->assertSame(EscrowStatus::Locked, $entry->fresh()->escrow_status);
    }

    public function test_resolve_rejects_unauthenticated_with_401(): void
    {
        [, $faces] = $this->createDisputedEntries(1);
        $entry = $faces[0]['entry'];

        $response = $this->postJson("/api/v1/admin/attendance-disputes/{$entry->id}/resolve", [
            'outcome' => 'face',
            'notes' => 'Note valide',
        ]);

        $response->assertUnauthorized();
        $this->assertSame('UNAUTHENTICATED', $response->json('error.code'));
        $this->assertSame(EscrowStatus::Locked, $entry->fresh()->escrow_status);
    }

    public function test_resolve_returns_404_when_entry_not_found(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAsAdmin($admin)
            ->postJson('/api/v1/admin/attendance-disputes/999999/resolve', [
                'outcome' => 'face',
                'notes' => 'Note valide',
            ]);

        $response->assertNotFound();
        $this->assertSame('NOT_FOUND', $response->json('error.code'));
    }

    public function test_resolve_returns_422_when_entry_not_in_disputed_state(): void
    {
        [, $faces] = $this->createDisputedEntries(1);
        $entry = $faces[0]['entry'];
        $entry->update(['attendance_status' => AttendanceStatus::Absent]);

        $admin = $this->createAdmin();

        $response = $this->actingAsAdmin($admin)
            ->postJson("/api/v1/admin/attendance-disputes/{$entry->id}/resolve", [
                'outcome' => 'face',
                'notes' => 'Note valide',
            ]);

        $response->assertStatus(422);
        $this->assertSame('VALIDATION_ERROR', $response->json('error.code'));
        $this->assertSame(EscrowStatus::Locked, $entry->fresh()->escrow_status);
    }

    public function test_resolve_validates_notes_required_min_length(): void
    {
        [, $faces] = $this->createDisputedEntries(1);
        $admin = $this->createAdmin();
        $entry = $faces[0]['entry'];
        $url = "/api/v1/admin/attendance-disputes/{$entry->id}/resolve";

        // Missing notes.
        $r1 = $this->actingAsAdmin($admin)->postJson($url, ['outcome' => 'face']);
        $r1->assertStatus(422);
        $this->assertSame('VALIDATION_ERROR', $r1->json('error.code'));
        $this->assertNotEmpty($r1->json('error.details.notes'));

        // Empty notes.
        $r2 = $this->actingAsAdmin($admin)->postJson($url, ['outcome' => 'face', 'notes' => '']);
        $r2->assertStatus(422);
        $this->assertNotEmpty($r2->json('error.details.notes'));

        // Notes too short (3 chars).
        $r3 = $this->actingAsAdmin($admin)->postJson($url, ['outcome' => 'face', 'notes' => 'abc']);
        $r3->assertStatus(422);
        $this->assertNotEmpty($r3->json('error.details.notes'));

        // Invalid outcome.
        $r4 = $this->actingAsAdmin($admin)->postJson($url, ['outcome' => 'invalid', 'notes' => 'Note valide']);
        $r4->assertStatus(422);
        $this->assertNotEmpty($r4->json('error.details.outcome'));

        // Whitespace-only notes are trimmed before validation.
        $r5 = $this->actingAsAdmin($admin)->postJson($url, ['outcome' => 'face', 'notes' => '      ']);
        $r5->assertStatus(422);
        $this->assertNotEmpty($r5->json('error.details.notes'));
    }
}
