<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FaceSubscriptionStatus;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminSubscriptionsListTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private function withAdminApiToken(Admin $admin): static
    {
        return $this->withToken($admin->createToken('admin-token')->plainTextToken);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    // ===================================================================
    // Authorization — list + stats (401 / 403 dont editor)
    // ===================================================================

    public function test_unauthenticated_requests_return_401(): void
    {
        $this->getJson('/api/v1/admin/face-subscriptions')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->getJson('/api/v1/admin/face-subscriptions/stats')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_face_user_token_gets_403(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        $token = $user->createToken('face-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/face-subscriptions')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->withToken($token)
            ->getJson('/api/v1/admin/face-subscriptions/stats')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_editor_admin_gets_403(): void
    {
        $editor = Admin::factory()->editor()->create();

        $this->withAdminApiToken($editor)
            ->getJson('/api/v1/admin/face-subscriptions')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->withAdminApiToken($editor)
            ->getJson('/api/v1/admin/face-subscriptions/stats')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_admin_and_superadmin_can_access_list_and_stats(): void
    {
        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions')
            ->assertOk();

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions/stats')
            ->assertOk();

        $superadmin = Admin::factory()->superAdmin()->create();

        $this->withAdminApiToken($superadmin)
            ->getJson('/api/v1/admin/face-subscriptions')
            ->assertOk();

        $this->withAdminApiToken($superadmin)
            ->getJson('/api/v1/admin/face-subscriptions/stats')
            ->assertOk();
    }

    // ===================================================================
    // List — bloc face, tri défaut, filtres, search, pagination
    // ===================================================================

    public function test_list_returns_face_block_and_sorts_by_expires_at_asc_with_nulls_last(): void
    {
        $faceLate = Face::factory()->create(['nom' => 'Zidane', 'prenom' => 'Zinedine', 'username' => 'zizou']);
        $faceSoon = Face::factory()->create(['nom' => 'Adjovi', 'prenom' => 'Awa', 'username' => 'awa-a']);
        $facePending = Face::factory()->create(['nom' => 'Kone', 'prenom' => 'Binta', 'username' => 'binta-k']);

        $late = FaceSubscription::factory()->active()->create([
            'face_id' => $faceLate->id,
            'expires_at' => now()->addDays(300),
        ]);
        $soon = FaceSubscription::factory()->active()->create([
            'face_id' => $faceSoon->id,
            'expires_at' => now()->addDays(10),
        ]);
        $pending = FaceSubscription::factory()->pendingPayment()->create([
            'face_id' => $facePending->id,
        ]);

        $response = $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        // expires_at ASC, NULL en fin
        $response->assertJsonPath('data.0.id', $soon->uuid)
            ->assertJsonPath('data.1.id', $late->uuid)
            ->assertJsonPath('data.2.id', $pending->uuid);

        // Bloc face (uuid, nom, prénom, username) + paid_at exposé
        $response->assertJsonPath('data.0.face.id', $faceSoon->uuid)
            ->assertJsonPath('data.0.face.nom', 'Adjovi')
            ->assertJsonPath('data.0.face.prenom', 'Awa')
            ->assertJsonPath('data.0.face.username', 'awa-a');
        $this->assertArrayHasKey('paid_at', $response->json('data.0'));
    }

    public function test_list_sort_desc_keeps_nulls_last(): void
    {
        $late = FaceSubscription::factory()->active()->create(['expires_at' => now()->addDays(300)]);
        $soon = FaceSubscription::factory()->active()->create(['expires_at' => now()->addDays(10)]);
        $pending = FaceSubscription::factory()->pendingPayment()->create();

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?sort=expires_at_desc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $late->uuid)
            ->assertJsonPath('data.1.id', $soon->uuid)
            ->assertJsonPath('data.2.id', $pending->uuid);
    }

    public function test_list_filters_by_plan_and_status_combined(): void
    {
        $target = FaceSubscription::factory()->elite()->active()->create();
        FaceSubscription::factory()->elite()->pendingPayment()->create();
        FaceSubscription::factory()->starter()->active()->create();
        FaceSubscription::factory()->pro()->cancelled()->create();

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?plan=elite&status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->uuid)
            ->assertJsonPath('data.0.plan', 'elite')
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_list_status_active_filter_excludes_stale_active_rows(): void
    {
        $live = FaceSubscription::factory()->active()->create();

        // Stale-active : status=active mais expires_at passé (cron d'expiration
        // en retard). Le filtre « active » applique la même sémantique que les
        // KPIs de stats() — la ligne ne doit PAS apparaître, sinon la carte
        // « Actives » et la table filtrée se contredisent.
        FaceSubscription::factory()->create([
            'status' => FaceSubscriptionStatus::Active,
            'starts_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $live->uuid);

        // Sans filtre, la ligne stale reste visible (findable pour correction)
        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_status_expired_filter_includes_stale_active_rows(): void
    {
        // Miroir du filtre « active » : la ligne stale-active exclue d'« active »
        // doit apparaître sous « expirée » — sinon AUCUN filtre ne la montre
        // (statut brut encore « active », mais couverture terminée).
        $genuinelyExpired = FaceSubscription::factory()->expired()->create();

        $staleActive = FaceSubscription::factory()->create([
            'status' => FaceSubscriptionStatus::Active,
            'starts_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);

        FaceSubscription::factory()->active()->create();

        $response = $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?status=expired')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = array_column($response->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$genuinelyExpired->uuid, $staleActive->uuid], $ids);
    }

    public function test_list_ignores_invalid_filter_values(): void
    {
        FaceSubscription::factory()->active()->create();
        FaceSubscription::factory()->pendingPayment()->create();

        // Valeurs hors enum → ignorées (pattern maison), pas de 422 ni de filtre
        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?plan=platinum&status=bogus')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_search_matches_face_nom_prenom_and_username(): void
    {
        $byNom = Face::factory()->create(['nom' => 'Gnonlonfoun', 'prenom' => 'Jean', 'username' => 'jeang']);
        $byPrenom = Face::factory()->create(['nom' => 'Dossou', 'prenom' => 'Gnonlonfa', 'username' => 'dossou-g']);
        $byUsername = Face::factory()->create(['nom' => 'Ahouansou', 'prenom' => 'Marc', 'username' => 'gnonlon-star']);
        $noMatch = Face::factory()->create(['nom' => 'Sagbo', 'prenom' => 'Rita', 'username' => 'rita-s']);

        foreach ([$byNom, $byPrenom, $byUsername, $noMatch] as $face) {
            FaceSubscription::factory()->active()->create(['face_id' => $face->id]);
        }

        $response = $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?search=gnonlon')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $usernames = array_column(array_column($response->json('data'), 'face'), 'username');
        $this->assertEqualsCanonicalizing(['jeang', 'dossou-g', 'gnonlon-star'], $usernames);
    }

    public function test_list_search_escapes_like_wildcards(): void
    {
        $face = Face::factory()->create(['nom' => 'Doe', 'prenom' => 'Jane', 'username' => 'jane100%']);
        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        $other = Face::factory()->create(['nom' => 'Doe', 'prenom' => 'John', 'username' => 'john100x']);
        FaceSubscription::factory()->active()->create(['face_id' => $other->id]);

        // « 100% » littéral : ne doit PAS matcher « 100x » via un % non échappé
        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?search='.urlencode('100%'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.face.username', 'jane100%');

        // Backslash littéral : « \ » doit être échappé AVANT les wildcards —
        // sans ça, « back\ » devient le pattern '%back\%' où '\%' est un %
        // LITTÉRAL, et un username contenant réellement « back\ » devient
        // introuvable.
        $slashFace = Face::factory()->create(['nom' => 'Doe', 'prenom' => 'Jack', 'username' => 'back\\slash']);
        FaceSubscription::factory()->active()->create(['face_id' => $slashFace->id]);

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?search='.urlencode('back\\'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.face.username', 'back\\slash');

        // Underscore littéral : « awa_a » ne doit PAS matcher « awaxa » via un
        // _ non échappé (wildcard un-caractère).
        $underscoreFace = Face::factory()->create(['nom' => 'Doe', 'prenom' => 'Awa', 'username' => 'awa_a']);
        FaceSubscription::factory()->active()->create(['face_id' => $underscoreFace->id]);

        $lookalike = Face::factory()->create(['nom' => 'Doe', 'prenom' => 'Awa', 'username' => 'awaxa']);
        FaceSubscription::factory()->active()->create(['face_id' => $lookalike->id]);

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?search='.urlencode('awa_a'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.face.username', 'awa_a');
    }

    public function test_list_search_trims_surrounding_whitespace(): void
    {
        $face = Face::factory()->create(['nom' => 'Adjovi', 'prenom' => 'Awa', 'username' => 'awa-a']);
        FaceSubscription::factory()->active()->create(['face_id' => $face->id]);

        // Espace de fin typique d'un copier-coller WhatsApp/mail : sans trim,
        // le motif devient '%Adjovi %' et la Face existante est introuvable.
        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?search='.urlencode(' Adjovi '))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.face.nom', 'Adjovi');
    }

    public function test_list_paginates_with_default_15_and_caps_per_page_at_100(): void
    {
        FaceSubscription::factory()->active()->count(16)->create();

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2);

        // per_page capé à 100
        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    // ===================================================================
    // Stats — actives par palier, expirations, compteurs, revenus (D-1)
    // ===================================================================

    public function test_stats_counts_active_by_plan_and_excludes_stale_active(): void
    {
        FaceSubscription::factory()->starter()->active()->create();
        FaceSubscription::factory()->pro()->active()->count(2)->create();
        FaceSubscription::factory()->elite()->active()->create();

        // Stale-active : status=active mais expires_at passé (cron pas encore passé)
        // → hors « actives par palier » ET hors « expirations 30 j »
        FaceSubscription::factory()->pro()->create([
            'status' => FaceSubscriptionStatus::Active,
            'starts_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions/stats')
            ->assertOk()
            ->assertJsonPath('data.active_by_plan.starter', 1)
            ->assertJsonPath('data.active_by_plan.pro', 2)
            ->assertJsonPath('data.active_by_plan.elite', 1)
            ->assertJsonPath('data.active_by_plan.total', 4)
            ->assertJsonPath('data.expiring_within_30_days', 0);
    }

    public function test_stats_counts_expiring_within_30_days_and_pending_failed(): void
    {
        FaceSubscription::factory()->active()->create(['expires_at' => now()->addDays(15)]);
        FaceSubscription::factory()->active()->create(['expires_at' => now()->addDays(29)]);
        FaceSubscription::factory()->active()->create(['expires_at' => now()->addDays(45)]);
        FaceSubscription::factory()->pendingPayment()->count(2)->create(['paid_amount' => null]);
        FaceSubscription::factory()->failed()->create(['paid_amount' => null]);

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions/stats')
            ->assertOk()
            ->assertJsonPath('data.expiring_within_30_days', 2)
            ->assertJsonPath('data.pending_payment_count', 2)
            ->assertJsonPath('data.failed_count', 1);
    }

    public function test_stats_revenue_counts_paid_then_expired_subscription(): void
    {
        // D-1 cas 1 : un abonnement payé puis expiré reste un revenu,
        // indépendamment du statut courant.
        FaceSubscription::factory()->expired()->create([
            'paid_amount' => 50000,
            'paid_at' => now()->subMonths(13),
        ]);

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions/stats')
            ->assertOk()
            ->assertJsonPath('data.revenue.total', 50000)
            ->assertJsonPath('data.revenue.current_month', 0)
            ->assertJsonPath('data.revenue.currency', 'XOF');
    }

    public function test_stats_revenue_excludes_manual_activation_but_counts_it_active(): void
    {
        // D-1 cas 2 : activation manuelle admin = geste commercial
        // (paid_amount NULL) → exclue des revenus, comptée dans les actives.
        FaceSubscription::factory()->pro()->active()->create([
            'paid_amount' => null,
            'paid_at' => null,
        ]);

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions/stats')
            ->assertOk()
            ->assertJsonPath('data.active_by_plan.pro', 1)
            ->assertJsonPath('data.active_by_plan.total', 1)
            ->assertJsonPath('data.revenue.total', 0)
            ->assertJsonPath('data.revenue.current_month', 0);
    }

    public function test_stats_revenue_dates_renewal_at_paid_at_month_not_starts_at(): void
    {
        // D-1 cas 3 : renouvellement payé en juin avec starts_at en août
        // → compté dans les revenus de juin (mois d'encaissement).
        Carbon::setTestNow(Carbon::parse('2026-06-20 12:00:00'));

        try {
            FaceSubscription::factory()->active()->create([
                'starts_at' => Carbon::parse('2026-08-01 00:00:00'),
                'expires_at' => Carbon::parse('2027-08-01 00:00:00'),
                'paid_amount' => 75000,
                'paid_at' => Carbon::parse('2026-06-10 09:00:00'),
            ]);

            // Contrôle : payé en mai → hors mois courant, dans le cumul
            FaceSubscription::factory()->active()->create([
                'paid_amount' => 25000,
                'paid_at' => Carbon::parse('2026-05-02 09:00:00'),
            ]);

            $this->withAdminApiToken($this->admin)
                ->getJson('/api/v1/admin/face-subscriptions/stats')
                ->assertOk()
                ->assertJsonPath('data.revenue.current_month', 75000)
                ->assertJsonPath('data.revenue.total', 100000);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_stats_revenue_current_month_uses_benin_calendar_month(): void
    {
        // Frontière de mois : les mois de revenus sont des mois calendaires
        // Africa/Porto-Novo (UTC+01:00 fixe, pas d'heure d'été). Un paiement
        // à 23:30 UTC le dernier jour du mois UTC précédent = 00:30 au Bénin
        // le 1er du mois courant → compté dans le mois courant.
        Carbon::setTestNow(Carbon::parse('2026-07-15 12:00:00', 'UTC'));

        try {
            FaceSubscription::factory()->active()->create([
                'paid_amount' => 40000,
                'paid_at' => Carbon::parse('2026-06-30 23:30:00', 'UTC'),
            ]);

            // Contrôle : 22:30 UTC le 30 juin = 23:30 au Bénin → mois
            // précédent, hors mois courant, dans le cumul.
            FaceSubscription::factory()->active()->create([
                'paid_amount' => 10000,
                'paid_at' => Carbon::parse('2026-06-30 22:30:00', 'UTC'),
            ]);

            $this->withAdminApiToken($this->admin)
                ->getJson('/api/v1/admin/face-subscriptions/stats')
                ->assertOk()
                ->assertJsonPath('data.revenue.current_month', 40000)
                ->assertJsonPath('data.revenue.total', 50000);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_stats_row_without_paid_at_counts_in_total_but_not_in_period(): void
    {
        // Ligne ancienne payée mais non-datable (backfill impossible) :
        // dans le cumul total, absente des agrégats par période.
        FaceSubscription::factory()->active()->create([
            'paid_amount' => 60000,
            'paid_at' => null,
        ]);

        $this->withAdminApiToken($this->admin)
            ->getJson('/api/v1/admin/face-subscriptions/stats')
            ->assertOk()
            ->assertJsonPath('data.revenue.total', 60000)
            ->assertJsonPath('data.revenue.current_month', 0);
    }
}
