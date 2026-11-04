<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use App\Enums\CandidatureStatus;
use App\Enums\CompensationType;
use App\Jobs\GenerateImageContractPdf;
use App\Mail\SignatureCodeMail;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\ImageContract;
use App\Models\Notification;
use App\Models\SignatureChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

class ConfirmCandidatureWithImageContractTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake([GenerateImageContractPdf::class]);
        $this->setUpParties();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function signature(array $extra = []): array
    {
        return ['signature' => array_merge(['name' => 'Aïcha Bello', 'code' => '123456'], $extra)];
    }

    private function confirm(Candidature $candidature, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->faceUser)
            ->withHeaders(['User-Agent' => 'FaceBrowser/2.0'])
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/confirm", $body);
    }

    private function reconfirm(Candidature $candidature, array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->faceUser)
            ->withHeaders(['User-Agent' => 'FaceBrowser/2.0'])
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/reconfirm", $body);
    }

    // ===================================================================
    // Confirm classique
    // ===================================================================

    public function test_a_classic_confirm_with_a_signature_creates_the_contract_and_dispatches_the_pdf(): void
    {
        [$mission, $candidature] = $this->cashMissionWithAcceptedFace();
        $challenge = $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456');

        $this->confirm($candidature, $this->signature())
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.image_contract.status', 'signed')
            ->assertJsonPath('data.image_contract.face_signed_name', 'Aïcha Bello')
            ->assertJsonPath('data.image_contract.producer_signed_name', 'Jean Producteur')
            ->assertJsonPath('data.image_contract.pdf_ready', false);

        $contract = ImageContract::firstOrFail();
        $this->assertSame($candidature->id, $contract->candidature_id);
        $this->assertNull($contract->booking_id);
        $this->assertSame('v1', $contract->template_version);
        $this->assertSame(['social', 'web'], $contract->supports);
        $this->assertSame('benin', $contract->territoire);
        $this->assertSame(3, $contract->duree_annees);
        $this->assertSame('Jean Producteur', $contract->producer_signed_name);
        $this->assertSame('p•••••@example.com', $contract->producer_signed_email_masked);
        $this->assertSame('TestBrowser/1.0', $contract->producer_user_agent);
        $this->assertSame('a•••••@gmail.com', $contract->face_signed_email_masked);
        $this->assertSame('FaceBrowser/2.0', $contract->face_user_agent);
        $this->assertNull($contract->guardian_name);
        $this->assertNull($contract->effective_at);
        $this->assertNotNull($challenge->fresh()->consumed_at);

        $snapshot = $contract->snapshot;
        $this->assertSame('Publicité lait local', $snapshot['projet']['titre']);
        $this->assertSame($mission->type_mission->label(), $snapshot['projet']['type_contenu']);
        $this->assertFalse($snapshot['projet']['is_ugc']);
        $this->assertSame($mission->date_tournage->toDateString(), $snapshot['projet']['date_debut']);
        $this->assertNull($snapshot['projet']['date_fin']);
        $this->assertSame(['kind' => 'cash', 'montant_face_recoit' => 90000], $snapshot['contrepartie']);
        $this->assertSame('Aïcha Bello', $snapshot['parties']['face']['name']);
        $this->assertSame($this->producer->display_name, $snapshot['parties']['producer']['name']);

        $this->assertDatabaseHas('candidatures', ['id' => $candidature->id, 'status' => CandidatureStatus::InProgress->value]);
        Queue::assertPushed(GenerateImageContractPdf::class, fn (GenerateImageContractPdf $job) => $job->contractId === $contract->id);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->producerUser->id, 'type' => 'face_confirmed_participation']);
    }

    public function test_a_classic_confirm_without_signature_is_refused_and_nothing_changes(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();

        $this->confirm($candidature)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signature']);

        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
        $this->assertSame(0, ImageContract::count());
        Queue::assertNothingPushed();
        $this->assertSame(0, Notification::where('type', 'face_confirmed_participation')->count());
    }

    public function test_a_wrong_code_changes_no_status_sends_no_notification_and_creates_no_contract(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $challenge = $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456');

        $this->confirm($candidature, $this->signature(['code' => '000000']))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
        $this->assertSame(0, ImageContract::count());
        $this->assertSame(0, Notification::where('type', 'face_confirmed_participation')->count());
        Queue::assertNothingPushed();
        // Le compteur de tentatives est bien persisté.
        $this->assertSame(1, $challenge->fresh()->attempts);
        $this->assertNull($challenge->fresh()->consumed_at);
    }

    public function test_an_expired_code_and_a_consumed_code_are_refused(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456', ['expires_at' => now()->subMinute()]);

        $this->confirm($candidature, $this->signature())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_EXPIRED');

        SignatureChallenge::query()->delete();
        $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456', ['consumed_at' => now()]);

        $this->confirm($candidature, $this->signature())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
        $this->assertSame(0, ImageContract::count());
    }

    public function test_the_sixth_attempt_is_refused_even_with_the_right_code(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456');

        for ($i = 0; $i < 5; $i++) {
            $this->confirm($candidature, $this->signature(['code' => '000000']))
                ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');
        }

        $this->confirm($candidature, $this->signature())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_TOO_MANY_ATTEMPTS');

        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
    }

    public function test_a_code_issued_for_another_candidature_does_not_sign(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $this->makeChallenge($this->faceUser, 'candidature_confirm:'.\Illuminate\Support\Str::uuid(), '123456');

        $this->confirm($candidature, $this->signature())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(0, ImageContract::count());
    }

    public function test_a_second_confirm_creates_no_second_contract(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456');

        $this->confirm($candidature, $this->signature())->assertOk();
        $this->confirm($candidature, $this->signature())->assertStatus(400)->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(1, ImageContract::count());
        Queue::assertPushed(GenerateImageContractPdf::class, 1);
    }

    public function test_an_existing_contract_means_no_signature_is_asked_again(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $this->makeCandidatureContract($candidature);

        $this->confirm($candidature)->assertOk();

        $this->assertSame(1, ImageContract::count());
        Queue::assertNothingPushed();
    }

    public function test_a_mission_without_terms_confirms_as_before(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace(withTerms: false);

        $this->confirm($candidature)
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.image_contract', null);

        $this->assertSame(0, ImageContract::count());
        Queue::assertNothingPushed();
    }

    public function test_a_stranger_gets_the_original_403_even_with_terms(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $other = Face::factory()->create();
        $otherUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $other->id]);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/face/candidatures/{$candidature->uuid}/confirm")
            ->assertForbidden();
    }

    public function test_a_non_accepted_candidature_keeps_the_original_400(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $candidature->update(['status' => CandidatureStatus::Pending]);

        $this->confirm($candidature)
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATUS');
    }

    // ===================================================================
    // Reconfirm UGC
    // ===================================================================

    public function test_a_product_only_ugc_reconfirm_signs_and_arms_the_tunnel(): void
    {
        [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456');

        $this->reconfirm($candidature, $this->signature())
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.image_contract.status', 'signed');

        $contract = ImageContract::firstOrFail();
        $snapshot = $contract->snapshot;
        $this->assertTrue($snapshot['projet']['is_ugc']);
        $this->assertNull($snapshot['projet']['date_debut']);
        $this->assertSame('UGC', $snapshot['projet']['type_contenu']);
        $this->assertSame('Tenue Shade Fit', $snapshot['projet']['nom_produit']);
        $this->assertSame(
            ['kind' => 'ugc_product', 'nom_produit' => 'Tenue Shade Fit', 'valeur_produit' => 20000],
            $snapshot['contrepartie'],
        );
        $this->assertSame(CandidatureStatus::Confirmed, $candidature->fresh()->status);
        Queue::assertPushed(GenerateImageContractPdf::class, 1);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->producerUser->id, 'type' => 'ugc_deal_accepted']);
    }

    public function test_a_hybrid_ugc_reconfirm_snapshots_the_cash_and_the_product(): void
    {
        [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Hybrid);
        $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456');

        $this->reconfirm($candidature, $this->signature())->assertOk();

        $this->assertSame(
            ['kind' => 'ugc_hybrid', 'nom_produit' => 'Tenue Shade Fit', 'valeur_produit' => 20000, 'montant_face_recoit' => 13500],
            ImageContract::firstOrFail()->snapshot['contrepartie'],
        );
    }

    public function test_a_reconfirm_without_signature_or_with_a_wrong_code_changes_nothing(): void
    {
        [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $challenge = $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456');

        $this->reconfirm($candidature)->assertUnprocessable()->assertJsonValidationErrors(['signature']);
        $this->reconfirm($candidature, $this->signature(['code' => '000000']))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');

        $this->assertSame(CandidatureStatus::Accepted, $candidature->fresh()->status);
        $this->assertSame(0, ImageContract::count());
        $this->assertSame(0, Notification::where('type', 'ugc_deal_accepted')->count());
        Queue::assertNothingPushed();
        $this->assertSame(1, $challenge->fresh()->attempts);
    }

    public function test_a_ugc_mission_without_terms_reconfirms_as_before(): void
    {
        [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Product, withTerms: false);

        $this->reconfirm($candidature)->assertOk()->assertJsonPath('data.image_contract', null);

        $this->assertSame(0, ImageContract::count());
    }

    // ===================================================================
    // Face mineure
    // ===================================================================

    public function test_a_minor_needs_a_code_that_went_to_a_guardian(): void
    {
        $this->face->update(['date_naissance' => now()->subYears(16)->toDateString()]);
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        // Code parti à la Face elle-même : refusé.
        $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456');

        $this->confirm($candidature, $this->signature())
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SIGNATURE_CODE_INVALID');
        $this->assertSame(0, ImageContract::count());

        SignatureChallenge::query()->delete();
        $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456', [
            'email' => 'fatou@example.com',
            'guardian_name' => 'Fatou Bello',
            'guardian_relation' => 'mere',
        ]);

        $this->confirm($candidature, $this->signature())->assertOk();

        $contract = ImageContract::firstOrFail();
        $this->assertSame('Fatou Bello', $contract->guardian_name);
        $this->assertSame('mere', $contract->guardian_relation);
        $this->assertSame('f•••••@example.com', $contract->face_signed_email_masked);
    }

    // ===================================================================
    // Envoi du code
    // ===================================================================

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function codeBody(Candidature $candidature, array $extra = []): array
    {
        return array_merge(['purpose' => 'candidature_confirm', 'candidature_id' => $candidature->uuid], $extra);
    }

    public function test_the_face_receives_a_code_for_her_candidature(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', $this->codeBody($candidature))
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'a•••••@gmail.com');

        $challenge = SignatureChallenge::firstOrFail();
        $this->assertSame('candidature_confirm:'.$candidature->uuid, $challenge->purpose);
        $this->assertSame($this->faceUser->id, $challenge->user_id);
        Mail::assertSent(SignatureCodeMail::class, fn (SignatureCodeMail $mail) => $mail->hasTo('aicha@gmail.com') && $mail->summary === null);
    }

    public function test_another_face_cannot_request_a_code_for_this_candidature(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $other = Face::factory()->create(['date_naissance' => now()->subYears(30)->toDateString()]);
        $otherUser = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $other->id]);

        $this->actingAs($otherUser)
            ->postJson('/api/v1/image-contract/code', $this->codeBody($candidature))
            ->assertForbidden();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', $this->codeBody($candidature))
            ->assertForbidden();

        $this->assertSame(0, SignatureChallenge::count());
        Mail::assertNothingSent();
    }

    public function test_no_code_when_there_is_nothing_to_sign(): void
    {
        [, $withoutTerms] = $this->cashMissionWithAcceptedFace(withTerms: false);
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', $this->codeBody($withoutTerms))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS')
            ->assertJsonPath('error.message', 'Aucun contrat à signer pour cette candidature.');

        [, $pending] = $this->ugcMissionWithAcceptedFace();
        $pending->update(['status' => CandidatureStatus::Pending]);
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', $this->codeBody($pending))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        [, $signed] = $this->ugcMissionWithAcceptedFace();
        $this->makeCandidatureContract($signed);
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', $this->codeBody($signed))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertSame(0, SignatureChallenge::count());
    }

    public function test_the_candidature_id_is_required_and_the_mission_publish_code_is_producer_only(): void
    {
        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'candidature_confirm'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('candidature_id');

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'mission_publish'])
            ->assertForbidden();

        $this->actingAs($this->producerUser)
            ->postJson('/api/v1/image-contract/code', ['purpose' => 'mission_publish'])
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'p•••••@example.com');

        $this->assertSame('mission_publish', SignatureChallenge::firstOrFail()->purpose);
        Mail::assertSent(SignatureCodeMail::class, fn (SignatureCodeMail $mail) => $mail->hasTo('producteur@example.com'));
    }

    public function test_a_face_without_birth_date_is_asked_for_it(): void
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();
        $this->face->update(['date_naissance' => null]);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', $this->codeBody($candidature))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'BIRTH_DATE_REQUIRED');
    }

    public function test_a_minor_must_name_a_guardian_and_the_code_goes_to_the_guardian_with_the_summary(): void
    {
        $this->face->update(['date_naissance' => now()->subYears(16)->toDateString()]);
        [, $candidature] = $this->ugcMissionWithAcceptedFace(CompensationType::Hybrid);

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', $this->codeBody($candidature))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guardian_name');
        $this->assertSame(0, SignatureChallenge::count());

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', $this->codeBody($candidature, [
                'guardian_name' => 'Fatou Bello',
                'guardian_relation' => 'mere',
                'guardian_email' => 'fatou@example.com',
            ]))
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'f•••••@example.com');

        $challenge = SignatureChallenge::firstOrFail();
        $this->assertSame('Fatou Bello', $challenge->guardian_name);
        $this->assertSame('fatou@example.com', $challenge->email);

        // Le résumé se construit sans contrat existant : producteur, conditions et contrepartie.
        Mail::assertSent(SignatureCodeMail::class, function (SignatureCodeMail $mail): bool {
            $html = $mail->render();

            return $mail->hasTo('fatou@example.com')
                && $mail->summary !== null
                && str_contains($html, $this->producer->display_name)
                && str_contains($html, 'Campagne réseaux sociaux de la marque')
                && str_contains($html, 'Réseaux sociaux, Site web')
                && str_contains($html, 'Bénin')
                && str_contains($html, '3 ans')
                && str_contains($html, 'Produit remis : Tenue Shade Fit')
                && str_contains($html, 'Rémunération reçue par la Face')
                && ! str_contains($html, '—');
        });
    }

    public function test_a_minor_cannot_use_her_own_email_as_guardian_email_for_a_candidature(): void
    {
        $this->face->update(['date_naissance' => now()->subYears(16)->toDateString()]);
        [, $candidature] = $this->cashMissionWithAcceptedFace();

        $this->actingAs($this->faceUser)
            ->postJson('/api/v1/image-contract/code', $this->codeBody($candidature, [
                'guardian_name' => 'Fatou Bello',
                'guardian_relation' => 'mere',
                'guardian_email' => 'aicha@gmail.com',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guardian_email');
    }

    // ===================================================================
    // must_sign_image_contract (liste « Mes candidatures » de la Face)
    // ===================================================================

    /**
     * @return array<int, array<string, mixed>>
     */
    private function faceList(): array
    {
        return $this->actingAs($this->faceUser)
            ->getJson('/api/v1/face/candidatures')
            ->assertOk()
            ->json('data');
    }

    public function test_the_face_list_says_whether_a_signature_is_needed(): void
    {
        [, $withTerms] = $this->cashMissionWithAcceptedFace();
        [, $withoutTerms] = $this->ugcMissionWithAcceptedFace(CompensationType::Product, withTerms: false);
        [, $signed] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $this->makeCandidatureContract($signed);
        [, $pending] = $this->ugcMissionWithAcceptedFace(CompensationType::Product);
        $pending->update(['status' => CandidatureStatus::Pending]);

        $byId = collect($this->faceList())->keyBy('id');

        $this->assertTrue($byId[$withTerms->uuid]['must_sign_image_contract']);
        $this->assertFalse($byId[$withoutTerms->uuid]['must_sign_image_contract']);
        $this->assertFalse($byId[$signed->uuid]['must_sign_image_contract']);
        $this->assertFalse($byId[$pending->uuid]['must_sign_image_contract']);
    }

    public function test_the_flag_is_absent_when_disabled_and_the_list_has_no_n_plus_one(): void
    {
        foreach (range(1, 3) as $i) {
            $face = Face::factory()->create();
            $this->cashMissionWithAcceptedFace(face: $face);
            Candidature::query()->where('face_id', $face->id)->update(['face_id' => $this->face->id]);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $list = $this->faceList();
        $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->pluck('query');
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertCount(3, $list);
        $this->assertCount(1, $queries->filter(fn (string $q) => str_contains($q, 'from `image_contracts`')));
        $this->assertCount(1, $queries->filter(fn (string $q) => str_contains($q, 'from `mission_image_terms`')));

        config(['image_contracts.enabled' => false]);
        $this->assertArrayNotHasKey('must_sign_image_contract', $this->faceList()[0]);
    }

    public function test_the_mission_detail_and_the_confirm_response_carry_the_flag(): void
    {
        [$mission, $candidature] = $this->cashMissionWithAcceptedFace();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/face/missions/{$mission->uuid}")
            ->assertOk()
            ->assertJsonPath('candidature.must_sign_image_contract', true);

        $this->makeChallenge($this->faceUser, $this->candidatePurpose($candidature), '123456');
        $this->confirm($candidature, $this->signature())
            ->assertOk()
            ->assertJsonPath('data.must_sign_image_contract', false);
    }
}
