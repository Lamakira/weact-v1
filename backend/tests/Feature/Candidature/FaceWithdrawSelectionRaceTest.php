<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Enums\CandidatureStatus;
use App\Enums\EscrowStatus;
use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lot B2 — course réelle entre le retrait d'une Face et la validation d'une sélection qui la
 * contient, sur deux connexions MySQL. Les données doivent être commitées (pas de RefreshDatabase)
 * pour que la seconde connexion les voie.
 */
class FaceWithdrawSelectionRaceTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseTruncation ne vide les tables qu'au début du test suivant qui l'utilise : on les
        // vide aussi en sortie, les tests RefreshDatabase qui suivent supposent une base vide.
        $this->beforeApplicationDestroyed(function (): void {
            DB::disconnect('mysql_race');
            $this->truncateTablesForAllConnections();
        });
    }

    public function test_withdrawal_is_refused_when_a_selection_including_the_face_commits_during_the_withdrawal(): void
    {
        $producer = Producer::factory()->create();
        /** @var Mission $mission */
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => MissionStatus::Published,
        ]);
        $face = Face::factory()->create();
        $faceUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);
        $candidature = Candidature::factory()->create([
            'mission_id' => $mission->id,
            'face_id' => $face->id,
            'status' => CandidatureStatus::Pending,
        ]);

        // Seconde connexion = le Producteur qui valide sa sélection (prepareSelectionForPayment)
        // pendant le retrait : elle commite juste après la première lecture de la transaction de
        // retrait, avant que celle-ci ne verrouille la mission.
        config(['database.connections.mysql_race' => config('database.connections.mysql')]);
        $injected = false;
        DB::listen(function (QueryExecuted $query) use (&$injected, $mission, $producer, $candidature, $face): void {
            if ($injected || $query->connectionName !== 'mysql' || DB::transactionLevel() === 0
                || ! str_contains($query->sql, 'from `mission_payment_candidatures`')) {
                return;
            }

            $injected = true;
            $race = DB::connection('mysql_race');
            $race->table('missions')->where('id', $mission->id)->update(['status' => MissionStatus::PendingPayment->value]);
            $paymentId = $race->table('mission_payments')->insertGetId([
                'mission_id' => $mission->id,
                'producer_id' => $producer->id,
                'nombre_faces_retenues' => 1,
                'budget_par_face' => 100000,
                'montant_sous_total' => 100000,
                'commission_producteur' => 10000,
                'montant_total_producteur' => 110000,
                'commission_faces_total' => 10000,
                'montant_total_faces' => 90000,
                'status' => MissionPaymentStatus::Pending->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $race->table('mission_payment_candidatures')->insert([
                'mission_payment_id' => $paymentId,
                'candidature_id' => $candidature->id,
                'face_id' => $face->id,
                'montant_face_recoit' => 90000,
                'escrow_status' => EscrowStatus::Pending->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $response = $this->actingAs($faceUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/cancel");

        $this->assertTrue($injected, 'La sélection concurrente n\'a pas été injectée.');
        // Sinon le Producteur paierait une place dont la Face s'est retirée : entry Locked sur une
        // candidature Cancelled, jamais confirmée ni expirée, fin de mission bloquée.
        $response->assertStatus(422);
        $this->assertSame(CandidatureStatus::Pending, $candidature->fresh()->status);
    }
}
