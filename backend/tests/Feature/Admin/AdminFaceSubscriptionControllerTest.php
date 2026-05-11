<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Enums\FaceSubscriptionAdminAction;
use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\FaceSubscriptionAudit;
use App\Models\Producer;
use App\Models\User;
use App\Services\FaceSubscriptionAdminService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminFaceSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Face $face;

    private function withAdminApiToken(Admin $admin): static
    {
        return $this->withToken($admin->createToken('admin-token')->plainTextToken);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->face = Face::factory()->create();
    }

    // ===================================================================
    // Authorization (AC #3, #4, #5, #6)
    // ===================================================================

    public function test_unauthenticated_list_returns_401(): void
    {
        // AC #3
        $response = $this->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions");

        $response->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED')
            ->assertJsonPath('error.message', 'Non authentifié.');
    }

    public function test_face_user_token_gets_403_on_list(): void
    {
        // AC #4
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $token = $user->createToken('face-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions");

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', "Cette action n'est pas autorisée");
    }

    public function test_producer_user_token_gets_403_on_list(): void
    {
        // AC #4
        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
        ]);
        $token = $user->createToken('producer-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions");

        $response->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', "Cette action n'est pas autorisée");
    }

    public function test_editor_admin_gets_403_on_list(): void
    {
        // AC #5
        $editor = Admin::factory()->editor()->create();

        $this->withAdminApiToken($editor)
            ->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', "Vous n'avez pas les droits nécessaires pour accéder à cette ressource.");
    }

    public function test_admin_role_admin_can_list(): void
    {
        // AC #6
        $this->withAdminApiToken($this->admin)
            ->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions")
            ->assertOk();
    }

    public function test_superadmin_can_list(): void
    {
        // AC #6
        $superadmin = Admin::factory()->superAdmin()->create();

        $this->withAdminApiToken($superadmin)
            ->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions")
            ->assertOk();
    }

    public function test_all_mutating_endpoints_block_non_authorised_callers(): void
    {
        // AC #3, #4, #5
        $subscription = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $mutatingEndpoints = [
            ['POST', "/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", ['notes' => 'Test notes 12345']],
            ['POST', "/api/v1/admin/face-subscriptions/{$subscription->uuid}/extend", ['notes' => 'Test notes 12345', 'additional_days' => 30]],
            ['POST', "/api/v1/admin/face-subscriptions/{$subscription->uuid}/cancel", ['notes' => 'Test notes 12345']],
            ['POST', "/api/v1/admin/face-subscriptions/{$subscription->uuid}/correct", ['notes' => 'Test notes 12345', 'expires_at' => now()->addYear()->toIso8601String()]],
        ];

        // (a) No token → 401
        foreach ($mutatingEndpoints as [$verb, $url, $body]) {
            $this->json($verb, $url, $body)->assertUnauthorized();
        }

        // (b) Face user → 403
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $faceToken = $faceUser->createToken('face-token')->plainTextToken;
        foreach ($mutatingEndpoints as [$verb, $url, $body]) {
            $this->withToken($faceToken)->json($verb, $url, $body)->assertForbidden();
        }

        // (c) Editor admin → 403
        $editor = Admin::factory()->editor()->create();
        foreach ($mutatingEndpoints as [$verb, $url, $body]) {
            $this->withAdminApiToken($editor)->json($verb, $url, $body)->assertForbidden();
        }
    }

    // ===================================================================
    // Index (AC #7, #8, #9)
    // ===================================================================

    public function test_index_returns_empty_subscriptions_for_face_with_no_history(): void
    {
        // AC #8
        $this->withAdminApiToken($this->admin)
            ->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions")
            ->assertOk()
            ->assertJsonPath('data.face.id', $this->face->uuid)
            ->assertJsonPath('data.subscriptions', []);
    }

    public function test_index_returns_subscriptions_ordered_most_recent_first(): void
    {
        // AC #7
        $old = FaceSubscription::factory()->expired()->create([
            'face_id' => $this->face->id,
            'created_at' => now()->subYears(2),
        ]);
        $mid = FaceSubscription::factory()->cancelled()->create([
            'face_id' => $this->face->id,
            'created_at' => now()->subMonths(6),
        ]);
        $newest = FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->withAdminApiToken($this->admin)
            ->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions");

        $response->assertOk();
        $ids = collect($response->json('data.subscriptions'))->pluck('id')->all();
        $this->assertSame([$newest->uuid, $mid->uuid, $old->uuid], $ids);
    }

    public function test_index_returns_audits_ordered_most_recent_first(): void
    {
        // AC #7
        $subscription = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        $olderAudit = FaceSubscriptionAudit::factory()->create([
            'face_subscription_id' => $subscription->id,
            'admin_id' => $this->admin->id,
            'action' => FaceSubscriptionAdminAction::ManualActivate,
            'notes' => 'Activation initiale manuelle',
            'previous_state' => null,
            'new_state' => $this->snapshotShape($subscription),
            'created_at' => now()->subMonth(),
        ]);
        $newerAudit = FaceSubscriptionAudit::factory()->create([
            'face_subscription_id' => $subscription->id,
            'admin_id' => $this->admin->id,
            'action' => FaceSubscriptionAdminAction::Extend,
            'notes' => 'Extension de 60 jours',
            'previous_state' => $this->snapshotShape($subscription),
            'new_state' => $this->snapshotShape($subscription),
            'created_at' => now(),
        ]);

        $response = $this->withAdminApiToken($this->admin)
            ->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions");

        $auditIds = collect($response->json('data.subscriptions.0.audits'))->pluck('id')->all();
        $this->assertSame([$newerAudit->uuid, $olderAudit->uuid], $auditIds);
    }

    public function test_index_never_leaks_provider_payload_fields(): void
    {
        // AC #7 leak guard
        FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
            'provider' => 'fedapay',
            'provider_reference' => 'fedapay_secret_ref_xyz',
            'metadata' => ['internal_token' => 'do_not_leak'],
        ]);

        $response = $this->withAdminApiToken($this->admin)
            ->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions");

        $response->assertOk();
        $response->assertDontSee('fedapay_secret_ref_xyz');
        $response->assertDontSee('do_not_leak');
        $payload = $response->json('data.subscriptions.0');
        $this->assertArrayNotHasKey('provider', $payload);
        $this->assertArrayNotHasKey('provider_reference', $payload);
        $this->assertArrayNotHasKey('metadata', $payload);
    }

    public function test_index_returns_404_when_face_uuid_unknown(): void
    {
        // AC #9
        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/faces/00000000-0000-0000-0000-000000000000/subscriptions')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.message', 'Ressource introuvable.');
    }

    // ===================================================================
    // Activate (AC #10, #11, #12, #13, #14, #15)
    // ===================================================================

    public function test_activate_creates_active_subscription_and_audit_when_face_has_no_subscription(): void
    {
        // AC #10, #11
        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", [
                'notes' => 'Paiement reçu offline le 11/05/2026 par dépôt MTN',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.plan', 'annual_premium')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('message', 'Abonnement activé manuellement');

        $this->assertDatabaseCount('face_subscriptions', 1);
        $sub = FaceSubscription::query()->where('face_id', $this->face->id)->first();
        $this->assertSame(FaceSubscriptionStatus::Active, $sub->status);
        $this->assertSame(FaceSubscriptionPlan::AnnualPremium, $sub->plan);
        $this->assertNotNull($sub->starts_at);
        $this->assertNotNull($sub->expires_at);
        $this->assertTrue($sub->expires_at->equalTo($sub->starts_at->copy()->addDays(365)));
        $this->assertNull($sub->paid_amount);
        $this->assertNull($sub->provider);
        $this->assertNull($sub->provider_reference);
        $this->assertNull($sub->metadata);

        $this->assertDatabaseCount('face_subscription_audits', 1);
        $audit = FaceSubscriptionAudit::query()->where('face_subscription_id', $sub->id)->first();
        $this->assertSame(FaceSubscriptionAdminAction::ManualActivate, $audit->action);
        $this->assertSame($this->admin->id, $audit->admin_id);
        $this->assertSame('Paiement reçu offline le 11/05/2026 par dépôt MTN', $audit->notes);
        $this->assertNull($audit->previous_state);
        $this->assertSame('annual_premium', $audit->new_state['plan']);
        $this->assertSame('active', $audit->new_state['status']);
        $this->assertNotNull($audit->new_state['starts_at']);
        $this->assertNotNull($audit->new_state['expires_at']);
        $this->assertNull($audit->new_state['cancelled_at']);
        $this->assertNull($audit->new_state['paid_amount']);
        $this->assertSame('XOF', $audit->new_state['currency']);
    }

    public function test_activate_respects_explicit_starts_at_and_duration_days(): void
    {
        // AC #10, #15
        $startsAt = now()->subDays(7)->startOfDay();

        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", [
                'notes' => 'Backfill activation rétroactive sur 30 jours',
                'starts_at' => $startsAt->toIso8601String(),
                'duration_days' => 30,
            ]);

        $response->assertStatus(201);
        $sub = FaceSubscription::query()->where('face_id', $this->face->id)->first();
        $this->assertTrue($sub->starts_at->equalTo($startsAt));
        $this->assertTrue($sub->expires_at->equalTo($startsAt->copy()->addDays(30)));
    }

    public function test_activate_returns_409_when_face_already_has_active_subscription(): void
    {
        // AC #12
        FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", [
                'notes' => 'Tentative activation alors qu un abonnement actif existe',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'ALREADY_ACTIVE')
            ->assertJsonPath('error.message', 'Cette Face a déjà un abonnement actif. Utilisez « Étendre » pour prolonger la période en cours.');

        $this->assertDatabaseCount('face_subscriptions', 1);
        $this->assertDatabaseCount('face_subscription_audits', 0);
    }

    public function test_activate_returns_409_when_face_has_pending_payment_subscription(): void
    {
        // AC #13
        FaceSubscription::factory()->pendingPayment()->create(['face_id' => $this->face->id]);

        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", [
                'notes' => 'Tentative activation alors qu un paiement est en attente',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'PENDING_PAYMENT_EXISTS')
            ->assertJsonPath('error.message', 'Un paiement est en attente pour cette Face. Annulez d\'abord l\'abonnement en attente avant l\'activation manuelle.');

        $this->assertDatabaseCount('face_subscription_audits', 0);
    }

    public function test_activate_succeeds_when_face_only_has_terminal_rows(): void
    {
        // AC #14
        FaceSubscription::factory()->expired()->create(['face_id' => $this->face->id]);
        FaceSubscription::factory()->cancelled()->create(['face_id' => $this->face->id]);
        FaceSubscription::factory()->failed()->create(['face_id' => $this->face->id]);

        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", [
                'notes' => 'Réactivation après terminal historique',
            ]);

        $response->assertStatus(201);
        $this->assertSame(4, FaceSubscription::query()->where('face_id', $this->face->id)->count());
    }

    public function test_activate_rejects_future_starts_at(): void
    {
        // AC #15 — FP-1.1 defer mitigation
        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", [
                'notes' => 'Tentative activation future',
                'starts_at' => now()->addDay()->toIso8601String(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_activate_rejects_short_notes(): void
    {
        // AC #15
        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", [
                'notes' => 'OK',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['notes']);
    }

    public function test_activate_rejects_missing_notes(): void
    {
        // AC #15
        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['notes']);
    }

    public function test_activate_rejects_duration_below_30_and_above_3650(): void
    {
        // AC #15
        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", [
                'notes' => 'Durée invalide trop courte',
                'duration_days' => 7,
            ])->assertStatus(422)->assertJsonValidationErrors(['duration_days']);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", [
                'notes' => 'Durée invalide trop longue',
                'duration_days' => 4000,
            ])->assertStatus(422)->assertJsonValidationErrors(['duration_days']);
    }

    // ===================================================================
    // Extend (AC #16, #17, #18)
    // ===================================================================

    public function test_extend_pushes_expires_at_and_writes_audit_on_active_subscription(): void
    {
        // AC #16
        Carbon::setTestNow('2026-05-11T12:00:00Z');

        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonths(11),
        ]);
        $originalExpiry = $subscription->expires_at->copy();

        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$subscription->uuid}/extend", [
                'notes' => 'Compensation downtime — extension 60 jours',
                'additional_days' => 60,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Abonnement étendu')
            ->assertJsonPath('data.id', $subscription->uuid);

        $subscription->refresh();
        $this->assertTrue($subscription->expires_at->equalTo($originalExpiry->copy()->addDays(60)));

        $audit = FaceSubscriptionAudit::query()->where('face_subscription_id', $subscription->id)->first();
        $this->assertSame(FaceSubscriptionAdminAction::Extend, $audit->action);
        $this->assertSame($originalExpiry->toIso8601String(), $audit->previous_state['expires_at']);
        $this->assertSame($subscription->expires_at->toIso8601String(), $audit->new_state['expires_at']);

        Carbon::setTestNow();
    }

    public function test_extend_returns_409_when_subscription_is_zombie_active(): void
    {
        // AC #17
        $subscription = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        FaceSubscription::query()->whereKey($subscription->id)->update([
            'expires_at' => now()->subDay(),
        ]);
        $subscription->refresh();

        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$subscription->uuid}/extend", [
                'notes' => 'Tentative extension sur zombie',
                'additional_days' => 30,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'NOT_EXTENDABLE');
    }

    public function test_extend_returns_409_on_non_active_statuses(): void
    {
        // AC #17
        $cases = [
            'expired' => fn () => FaceSubscription::factory()->expired(),
            'cancelled' => fn () => FaceSubscription::factory()->cancelled(),
            'failed' => fn () => FaceSubscription::factory()->failed(),
            'pendingPayment' => fn () => FaceSubscription::factory()->pendingPayment(),
        ];

        foreach ($cases as $state => $factoryFn) {
            $face = Face::factory()->create();
            $sub = $factoryFn()->create(['face_id' => $face->id]);

            $this->withAdminApiToken($this->admin)
                ->postJson("/api/v1/admin/face-subscriptions/{$sub->uuid}/extend", [
                    'notes' => "Tentative extension {$state}",
                    'additional_days' => 30,
                ])
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'NOT_EXTENDABLE');
        }
    }

    public function test_extend_rejects_missing_additional_days(): void
    {
        // AC #18
        $sub = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$sub->uuid}/extend", [
                'notes' => 'Notes valides mais additional_days manquant',
            ])->assertStatus(422)->assertJsonValidationErrors(['additional_days']);
    }

    public function test_extend_rejects_additional_days_out_of_range(): void
    {
        // AC #18
        $sub = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$sub->uuid}/extend", [
                'notes' => 'Notes valides — additional_days = 0',
                'additional_days' => 0,
            ])->assertStatus(422)->assertJsonValidationErrors(['additional_days']);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$sub->uuid}/extend", [
                'notes' => 'Notes valides — additional_days trop grand',
                'additional_days' => 4000,
            ])->assertStatus(422)->assertJsonValidationErrors(['additional_days']);
    }

    // ===================================================================
    // Cancel (AC #19, #20, #21)
    // ===================================================================

    public function test_cancel_active_subscription_sets_status_and_cancelled_at_with_audit(): void
    {
        // AC #19
        Carbon::setTestNow('2026-05-11T15:00:00Z');

        $subscription = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        $originalStarts = $subscription->starts_at->copy();
        $originalExpires = $subscription->expires_at->copy();

        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$subscription->uuid}/cancel", [
                'notes' => 'Annulation demande client après remboursement intégral',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Abonnement annulé')
            ->assertJsonPath('data.status', 'cancelled');

        $subscription->refresh();
        $this->assertSame(FaceSubscriptionStatus::Cancelled, $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertTrue($subscription->starts_at->equalTo($originalStarts));
        $this->assertTrue($subscription->expires_at->equalTo($originalExpires));

        $audit = FaceSubscriptionAudit::query()->where('face_subscription_id', $subscription->id)->first();
        $this->assertSame(FaceSubscriptionAdminAction::Cancel, $audit->action);
        $this->assertSame('active', $audit->previous_state['status']);
        $this->assertSame('cancelled', $audit->new_state['status']);
        $this->assertNull($audit->previous_state['cancelled_at']);
        $this->assertNotNull($audit->new_state['cancelled_at']);

        Carbon::setTestNow();
    }

    public function test_cancel_pending_payment_succeeds(): void
    {
        // AC #19
        $subscription = FaceSubscription::factory()->pendingPayment()->create(['face_id' => $this->face->id]);

        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$subscription->uuid}/cancel", [
                'notes' => 'Annulation paiement en attente avant activation manuelle',
            ]);

        $response->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_returns_409_on_terminal_statuses(): void
    {
        // AC #20
        $cases = [
            fn () => FaceSubscription::factory()->expired(),
            fn () => FaceSubscription::factory()->cancelled(),
            fn () => FaceSubscription::factory()->failed(),
        ];

        foreach ($cases as $factoryFn) {
            $face = Face::factory()->create();
            $sub = $factoryFn()->create(['face_id' => $face->id]);

            $this->withAdminApiToken($this->admin)
                ->postJson("/api/v1/admin/face-subscriptions/{$sub->uuid}/cancel", [
                    'notes' => 'Tentative annulation sur statut terminal',
                ])
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'NOT_CANCELLABLE');
        }
    }

    public function test_cancel_rejects_missing_notes(): void
    {
        // AC #21
        $sub = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$sub->uuid}/cancel", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);
    }

    // ===================================================================
    // Correct (AC #22, #23)
    // ===================================================================

    public function test_correct_updates_starts_at_only_and_writes_audit(): void
    {
        // AC #22, #23
        $subscription = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        $originalStarts = $subscription->starts_at->copy();
        $originalExpires = $subscription->expires_at->copy();
        $newStarts = $subscription->starts_at->copy()->subDays(3);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$subscription->uuid}/correct", [
                'notes' => 'Correction starts_at après erreur de saisie',
                'starts_at' => $newStarts->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Dates corrigées');

        $subscription->refresh();
        $this->assertTrue($subscription->starts_at->equalTo($newStarts));
        $this->assertTrue($subscription->expires_at->equalTo($originalExpires));

        $audit = FaceSubscriptionAudit::query()->where('face_subscription_id', $subscription->id)->first();
        $this->assertSame(FaceSubscriptionAdminAction::CorrectDates, $audit->action);
        $this->assertSame($originalStarts->toIso8601String(), $audit->previous_state['starts_at']);
        $this->assertSame($newStarts->toIso8601String(), $audit->new_state['starts_at']);
    }

    public function test_correct_updates_expires_at_only_and_writes_audit(): void
    {
        // AC #22, #23
        $subscription = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        $originalStarts = $subscription->starts_at->copy();
        $originalExpires = $subscription->expires_at->copy();
        $newExpires = $subscription->expires_at->copy()->addDays(10);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$subscription->uuid}/correct", [
                'notes' => 'Correction expires_at uniquement',
                'expires_at' => $newExpires->toIso8601String(),
            ])
            ->assertOk();

        $subscription->refresh();
        $this->assertTrue($subscription->starts_at->equalTo($originalStarts));
        $this->assertTrue($subscription->expires_at->equalTo($newExpires));

        $audit = FaceSubscriptionAudit::query()->where('face_subscription_id', $subscription->id)->first();
        $this->assertSame($originalExpires->toIso8601String(), $audit->previous_state['expires_at']);
        $this->assertSame($newExpires->toIso8601String(), $audit->new_state['expires_at']);
    }

    public function test_correct_updates_both_dates_and_writes_audit(): void
    {
        // AC #22, #23
        Carbon::setTestNow('2026-05-11T12:00:00Z');

        $subscription = FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonths(11),
        ]);

        $newStarts = now()->subDays(5);
        $newExpires = now()->addYear();

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$subscription->uuid}/correct", [
                'notes' => 'Correction des deux dates',
                'starts_at' => $newStarts->toIso8601String(),
                'expires_at' => $newExpires->toIso8601String(),
            ])
            ->assertOk();

        $subscription->refresh();
        $this->assertTrue($subscription->starts_at->equalTo($newStarts));
        $this->assertTrue($subscription->expires_at->equalTo($newExpires));

        Carbon::setTestNow();
    }

    public function test_correct_works_on_terminal_subscription(): void
    {
        // AC #22
        $subscription = FaceSubscription::factory()->expired()->create(['face_id' => $this->face->id]);
        $newExpires = now()->subDays(10);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$subscription->uuid}/correct", [
                'notes' => 'Correction dates historiques sur expired',
                'expires_at' => $newExpires->toIso8601String(),
            ])->assertOk();
    }

    public function test_correct_rejects_request_with_neither_date(): void
    {
        // AC #23
        $sub = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$sub->uuid}/correct", [
                'notes' => 'Notes valides mais aucune date fournie',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at'])
            ->assertJsonPath(
                'error.details.expires_at.0',
                'Au moins une des dates starts_at ou expires_at est requise.',
            );
    }

    public function test_correct_rejects_expires_at_not_after_starts_at(): void
    {
        // AC #23
        $sub = FaceSubscription::factory()->active()->create([
            'face_id' => $this->face->id,
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$sub->uuid}/correct", [
                'notes' => 'expires_at antérieur à starts_at actuel — doit échouer',
                'expires_at' => now()->subMonths(2)->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at'])
            ->assertJsonPath(
                'error.details.expires_at.0',
                'expires_at doit être postérieure à starts_at.',
            );

        $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/face-subscriptions/{$sub->uuid}/correct", [
                'notes' => 'Les deux dates invalides',
                'starts_at' => now()->toIso8601String(),
                'expires_at' => now()->subDay()->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at']);
    }

    // ===================================================================
    // Audit invariants (AC #24, #25, #26, #27)
    // ===================================================================

    public function test_audit_and_subscription_change_are_transactional(): void
    {
        // AC #24 — atomicity: forcing the audit insert to fail must roll back the subscription mutation.
        $sub = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);

        $ghostAdmin = new Admin;
        $ghostAdmin->forceFill([
            'id' => 999999999,
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Ghost Admin',
            'email' => 'ghost-admin@example.test',
            'role' => AdminRole::Admin,
        ]);

        $service = app(FaceSubscriptionAdminService::class);

        try {
            $service->cancel(
                subscription: $sub,
                admin: $ghostAdmin,
                notes: 'forced rollback test',
            );
            $this->fail('Expected the audit insert to fail on the admin_id foreign key.');
        } catch (QueryException) {
            // Expected: audit insertion fails, enclosing DB::transaction rolls back.
        }

        $sub->refresh();
        $this->assertSame(FaceSubscriptionStatus::Active, $sub->status);
        $this->assertNull($sub->cancelled_at);
        $this->assertDatabaseCount('face_subscription_audits', 0);
    }

    public function test_audit_admin_id_set_to_null_when_admin_is_deleted(): void
    {
        // AC #26
        $sub = FaceSubscription::factory()->active()->create(['face_id' => $this->face->id]);
        $audit = FaceSubscriptionAudit::factory()->create([
            'face_subscription_id' => $sub->id,
            'admin_id' => $this->admin->id,
        ]);

        $this->admin->delete();
        $audit->refresh();
        $this->assertNull($audit->admin_id);

        $newAdmin = Admin::factory()->create();
        $response = $this->withAdminApiToken($newAdmin)
            ->getJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions");

        $response->assertJsonPath('data.subscriptions.0.audits.0.admin.name', 'Admin supprimé');
        $response->assertJsonPath('data.subscriptions.0.audits.0.admin.id', null);
        $response->assertJsonPath('data.subscriptions.0.audits.0.admin.role', null);
    }

    public function test_audit_snapshot_excludes_provider_payload_fields(): void
    {
        // AC #27
        $response = $this->withAdminApiToken($this->admin)
            ->postJson("/api/v1/admin/faces/{$this->face->uuid}/subscriptions/activate", [
                'notes' => 'Snapshot leak guard test',
            ]);

        $response->assertStatus(201);
        $audit = FaceSubscriptionAudit::query()->first();
        $this->assertArrayNotHasKey('provider', $audit->new_state);
        $this->assertArrayNotHasKey('provider_reference', $audit->new_state);
        $this->assertArrayNotHasKey('metadata', $audit->new_state);
        // MySQL JSON columns do not preserve insertion order; compare the key set.
        $actualKeys = array_keys($audit->new_state);
        sort($actualKeys);
        $expectedKeys = ['cancelled_at', 'currency', 'expires_at', 'paid_amount', 'plan', 'starts_at', 'status'];
        $this->assertSame($expectedKeys, $actualKeys);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotShape(FaceSubscription $subscription): array
    {
        return [
            'plan' => $subscription->plan?->value,
            'status' => $subscription->status?->value,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            'paid_amount' => $subscription->paid_amount,
            'currency' => $subscription->currency,
        ];
    }
}
