<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Enums\BookingStatus;
use App\Enums\UgcSuspensionAppealStatus;
use App\Enums\UgcSuspensionReason;
use App\Enums\UgcTunnelStatus;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\Producer;
use App\Models\UgcSuspension;
use App\Models\User;
use App\Services\FaceEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UGC 5.3 — cycle d'appel (Face) + revue/réactivation admin. La Face ouvre un appel
 * (none→pending) ; l'admin liste les appels pending, réactive (accepte l'appel OU
 * réactivation directe) ou rejette (reste suspendu). Calque AdminAttendanceDisputeControllerTest
 * (actingAsAdmin via Sanctum token). Temps figé. La Face a une souscription active →
 * canAccessUgc reflète UNIQUEMENT l'état de suspension.
 */
class AdminUgcSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private User $producerUser;

    private Face $face;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();

        $this->producer = Producer::factory()->create();
        $this->producerUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->producer->id,
        ]);
        $this->face = Face::factory()->create([
            'prenom' => 'Aïcha',
            'nom' => 'Bello',
        ]);
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $this->face->id,
        ]);

        FaceSubscription::factory()->starter()->active()->create(['face_id' => $this->face->id]);
    }

    // ===================================================================
    // Fixtures
    // ===================================================================

    /** Suspension active du Face primaire, adossée à un vrai booking UGC (deal exposé). */
    private function suspendPrimaryFace(UgcSuspensionAppealStatus $appeal): UgcSuspension
    {
        $booking = Booking::create([
            'face_id' => $this->faceUser->id,          // users.id
            'producer_id' => $this->producerUser->id,  // users.id
            'status' => BookingStatus::Accepted,
            'accepted_at' => now(),
            'type_contenu' => 'UGC',
            'type_compensation' => 'hybrid',
            'nom_produit' => 'Tenue Shade Fit',
            'valeur_produit' => 20000,
            'nombre_videos' => 2,
            'commission_ugc' => 2250,
            'commission_paid_at' => now()->subDays(10),
            'tarif_base' => 0,
            'montant_remuneration' => 15000,
            'montant_face_recoit' => 14250,
            'montant_total_producteur' => 16500,
        ]);

        $shipment = $booking->shipment()->create([
            'transporteur' => 'Gozem',
            'numero_suivi' => 'GZM-COT-882194',
            'tunnel_status' => UgcTunnelStatus::Suspended,
            'shipped_at' => now()->subDays(10),
            'recu_le' => now()->subDays(8),
            'destinataire_nom' => 'Aïcha Bello',
            'destinataire_ville' => 'Cotonou',
            'destinataire_pays' => 'Bénin',
        ]);

        return UgcSuspension::create([
            'face_id' => $this->face->id,       // faces.id
            'shipment_id' => $shipment->id,
            'reason' => UgcSuspensionReason::UnboxingDeadlineMissed,
            'appeal_status' => $appeal,
            'suspended_at' => now(),
        ]);
    }

    /** Suspension « nue » d'une AUTRE Face (decoy de liste / reject sans pending). */
    private function suspendOtherFace(UgcSuspensionAppealStatus $appeal): UgcSuspension
    {
        $otherFace = Face::factory()->create(['prenom' => 'Fatou', 'nom' => 'Diallo']);

        return UgcSuspension::create([
            'face_id' => $otherFace->id,
            'shipment_id' => null,
            'reason' => UgcSuspensionReason::UnboxingDeadlineMissed,
            'appeal_status' => $appeal,
            'suspended_at' => now(),
        ]);
    }

    private function createAdmin(): Admin
    {
        return Admin::factory()->create(['role' => AdminRole::Admin]);
    }

    private function actingAsAdmin(Admin $admin): static
    {
        return $this->withToken($admin->createToken('admin-test-token')->plainTextToken);
    }

    private function entitlement(): FaceEntitlementService
    {
        return app(FaceEntitlementService::class);
    }

    // ===================================================================
    // AC7 — cycle d'appel (Face)
    // ===================================================================

    public function test_face_opens_appeal(): void
    {
        $s = $this->suspendPrimaryFace(UgcSuspensionAppealStatus::None);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/appeal')
            ->assertOk();

        $this->assertDatabaseHas('ugc_suspensions', [
            'id' => $s->id,
            'appeal_status' => 'pending',
        ]);
    }

    public function test_face_cannot_double_appeal(): void
    {
        $this->suspendPrimaryFace(UgcSuspensionAppealStatus::Pending);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/face/ugc/suspension/appeal')
            ->assertStatus(422);
    }

    // ===================================================================
    // AC8 — revue admin (liste + réactivation + rejet)
    // ===================================================================

    public function test_admin_lists_pending_appeals(): void
    {
        $this->suspendPrimaryFace(UgcSuspensionAppealStatus::Pending);
        $this->suspendOtherFace(UgcSuspensionAppealStatus::None); // decoy, exclu (none)

        $admin = $this->createAdmin();

        $this->actingAsAdmin($admin)
            ->getJson('/api/v1/admin/ugc/suspensions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.appeal_status', 'pending')
            ->assertJsonPath('data.0.face.prenom', 'Aïcha')
            ->assertJsonPath('data.0.deal.owner_kind', 'booking')
            ->assertJsonPath('data.0.deal.product_name', 'Tenue Shade Fit')
            ->assertJsonStructure([
                'data' => [
                    ['uuid', 'reason', 'reason_label', 'suspended_at', 'appeal_status', 'appeal_status_label', 'face' => ['id', 'prenom', 'nom'], 'deal'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_admin_reactivate_accepts_pending_appeal(): void
    {
        $s = $this->suspendPrimaryFace(UgcSuspensionAppealStatus::Pending);
        $admin = $this->createAdmin();

        $this->actingAsAdmin($admin)
            ->postJson("/api/v1/admin/ugc/suspensions/{$s->uuid}/reactivate")
            ->assertOk();

        $this->assertDatabaseHas('ugc_suspensions', [
            'id' => $s->id,
            'appeal_status' => 'accepted',
        ]);
        $this->assertNotNull(UgcSuspension::find($s->id)->reactivated_at);
        $this->assertTrue($this->entitlement()->canAccessUgc($this->face->fresh()));
    }

    public function test_admin_reactivate_direct_without_appeal(): void
    {
        $s = $this->suspendPrimaryFace(UgcSuspensionAppealStatus::None);
        $admin = $this->createAdmin();

        $this->actingAsAdmin($admin)
            ->postJson("/api/v1/admin/ugc/suspensions/{$s->uuid}/reactivate")
            ->assertOk();

        $fresh = UgcSuspension::find($s->id);
        $this->assertNotNull($fresh->reactivated_at);
        $this->assertSame(UgcSuspensionAppealStatus::None, $fresh->appeal_status); // inchangé
    }

    public function test_admin_reactivate_already_reactivated_is_422(): void
    {
        $s = $this->suspendPrimaryFace(UgcSuspensionAppealStatus::None);
        $s->update(['reactivated_at' => now()]);
        $admin = $this->createAdmin();

        $this->actingAsAdmin($admin)
            ->postJson("/api/v1/admin/ugc/suspensions/{$s->uuid}/reactivate")
            ->assertStatus(422);
    }

    public function test_admin_reject_appeal_keeps_suspended(): void
    {
        $s = $this->suspendPrimaryFace(UgcSuspensionAppealStatus::Pending);
        $admin = $this->createAdmin();

        $this->actingAsAdmin($admin)
            ->postJson("/api/v1/admin/ugc/suspensions/{$s->uuid}/reject-appeal")
            ->assertOk();

        $this->assertDatabaseHas('ugc_suspensions', [
            'id' => $s->id,
            'appeal_status' => 'rejected',
        ]);
        $this->assertNull(UgcSuspension::find($s->id)->reactivated_at);
        $this->assertFalse($this->entitlement()->canAccessUgc($this->face->fresh()));

        // Rejeter une suspension SANS appel pending → 422.
        $noPending = $this->suspendOtherFace(UgcSuspensionAppealStatus::None);
        $this->actingAsAdmin($admin)
            ->postJson("/api/v1/admin/ugc/suspensions/{$noPending->uuid}/reject-appeal")
            ->assertStatus(422);
    }

    // ===================================================================
    // AC8 — autorisation (non-admin / guest)
    // ===================================================================

    public function test_non_admin_forbidden(): void
    {
        $this->suspendPrimaryFace(UgcSuspensionAppealStatus::Pending);

        // Guest → 401 (vérifié AVANT de poser un token : withToken persiste l'en-tête).
        $this->getJson('/api/v1/admin/ugc/suspensions')
            ->assertUnauthorized();

        // Face authentifiée → 403 sur une route admin.
        $faceToken = $this->faceUser->createToken('face-token')->plainTextToken;
        $this->withToken($faceToken)
            ->getJson('/api/v1/admin/ugc/suspensions')
            ->assertForbidden();
    }
}
