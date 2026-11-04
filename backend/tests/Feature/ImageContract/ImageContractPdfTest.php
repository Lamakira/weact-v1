<?php

declare(strict_types=1);

namespace Tests\Feature\ImageContract;

use App\Jobs\GenerateImageContractPdf;
use App\Mail\ImageContractSignedMail;
use App\Mail\SignatureCodeMail;
use App\Models\Booking;
use App\Models\ImageContract;
use App\Support\ImageContractTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\ImageContract\Concerns\BuildsImageContracts;
use Tests\TestCase;

class ImageContractPdfTest extends TestCase
{
    use BuildsImageContracts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->setUpParties();
    }

    private function signedContract(): ImageContract
    {
        return $this->makeSignedContract($this->pendingCashBooking());
    }

    public function test_the_job_writes_the_pdf_records_its_sha256_and_mails_both_parties(): void
    {
        Mail::fake();
        $contract = $this->signedContract();

        (new GenerateImageContractPdf($contract->id))->handle();

        $contract->refresh();
        $this->assertSame('image-contracts/'.$contract->uuid.'.pdf', $contract->pdf_path);
        Storage::disk('local')->assertExists($contract->pdf_path);

        $bytes = Storage::disk('local')->get($contract->pdf_path);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(hash('sha256', $bytes), $contract->pdf_sha256);
        $this->assertSame(64, strlen($contract->pdf_sha256));

        Mail::assertSent(ImageContractSignedMail::class, 2);
        Mail::assertSent(ImageContractSignedMail::class, fn (ImageContractSignedMail $m) => $m->hasTo('aicha@gmail.com'));
        Mail::assertSent(ImageContractSignedMail::class, fn (ImageContractSignedMail $m) => $m->hasTo('producteur@example.com'));
    }

    public function test_the_job_is_idempotent(): void
    {
        Mail::fake();
        $contract = $this->signedContract();

        (new GenerateImageContractPdf($contract->id))->handle();
        $first = $contract->fresh();
        $this->travel(5)->minutes();
        (new GenerateImageContractPdf($contract->id))->handle();
        $second = $contract->fresh();

        $this->assertSame($first->pdf_path, $second->pdf_path);
        $this->assertSame($first->pdf_sha256, $second->pdf_sha256);
        $this->assertSame(1, count(Storage::disk('local')->allFiles('image-contracts')));
        Mail::assertSent(ImageContractSignedMail::class, 2);
    }

    public function test_the_job_regenerates_a_missing_file(): void
    {
        Mail::fake();
        $contract = $this->signedContract();
        (new GenerateImageContractPdf($contract->id))->handle();
        Storage::disk('local')->delete($contract->fresh()->pdf_path);

        (new GenerateImageContractPdf($contract->id))->handle();

        Storage::disk('local')->assertExists($contract->fresh()->pdf_path);
    }

    public function test_the_job_ignores_a_contract_not_yet_signed_by_the_face_or_gone(): void
    {
        Mail::fake();
        $unsigned = $this->makeContract($this->pendingCashBooking());

        (new GenerateImageContractPdf($unsigned->id))->handle();
        (new GenerateImageContractPdf(999999))->handle();

        $this->assertNull($unsigned->fresh()->pdf_path);
        Mail::assertNothingSent();
    }

    public function test_a_minor_face_guardian_receives_a_copy(): void
    {
        Mail::fake();
        $contract = $this->signedContract();
        $contract->update(['guardian_name' => 'Fatou Bello', 'guardian_relation' => 'mere']);
        $this->makeChallenge($this->faceUser, $this->acceptPurpose($contract->booking), '654321', [
            'email' => 'fatou@example.com',
            'guardian_name' => 'Fatou Bello',
            'guardian_relation' => 'mere',
            'consumed_at' => now(),
        ]);

        (new GenerateImageContractPdf($contract->id))->handle();

        Mail::assertSent(ImageContractSignedMail::class, 3);
        Mail::assertSent(ImageContractSignedMail::class, fn (ImageContractSignedMail $m) => $m->hasTo('fatou@example.com'));
    }

    public function test_a_mail_failure_never_fails_the_job(): void
    {
        $contract = $this->signedContract();
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('smtp down'));

        (new GenerateImageContractPdf($contract->id))->handle();

        $this->assertNotNull($contract->fresh()->pdf_path);
        $this->assertNotNull($contract->fresh()->pdf_sha256);
        // Envoi raté : les copies ne sont pas marquées parties, une relance les renverra.
        $this->assertNull($contract->fresh()->copies_sent_at);
    }

    public function test_a_failed_write_throws_and_records_neither_path_nor_hash(): void
    {
        Mail::fake();
        $contract = $this->signedContract();
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('put')->andReturn(false);
        Storage::shouldReceive('disk')->andReturn($disk);

        try {
            (new GenerateImageContractPdf($contract->id))->handle();
            $this->fail('Une écriture ratée doit lever une exception.');
        } catch (\RuntimeException) {
            // attendu
        }

        $fresh = $contract->fresh();
        $this->assertNull($fresh->pdf_path);
        $this->assertNull($fresh->pdf_sha256);
        $this->assertNull($fresh->copies_sent_at);
        Mail::assertNothingSent();
    }

    public function test_copies_are_marked_sent_and_a_failed_send_is_retried_without_regenerating(): void
    {
        $contract = $this->signedContract();
        Mail::fake();
        (new GenerateImageContractPdf($contract->id))->handle();
        $this->assertNotNull($contract->fresh()->copies_sent_at);
        Mail::assertSent(ImageContractSignedMail::class, 2);

        // Envoi des copies raté la première fois : le PDF existe, seules les copies repartent.
        $contract->update(['copies_sent_at' => null]);
        $first = $contract->fresh();
        (new GenerateImageContractPdf($contract->id))->handle();

        $second = $contract->fresh();
        $this->assertSame($first->pdf_sha256, $second->pdf_sha256);
        $this->assertNotNull($second->copies_sent_at);
        Mail::assertSent(ImageContractSignedMail::class, 4);

        // Une fois les copies parties, plus rien ne repart.
        (new GenerateImageContractPdf($contract->id))->handle();
        Mail::assertSent(ImageContractSignedMail::class, 4);
    }

    public function test_the_failed_hook_logs(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        (new GenerateImageContractPdf(12))->failed(new \RuntimeException('boom'));

        \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->once();
    }

    public function test_the_pdf_view_shows_the_conditions_the_articles_and_no_fingerprint_nor_dash(): void
    {
        $contract = $this->signedContract();
        $contract->update(['guardian_name' => 'Fatou Bello', 'guardian_relation' => 'mere', 'pdf_sha256' => str_repeat('ab', 32)]);

        $data = (new GenerateImageContractPdf($contract->id))->viewData($contract->fresh());
        $html = view('contracts.image-authorization', $data)->render();

        $this->assertStringContainsString($contract->reference(), $html);
        $this->assertStringContainsString('Campagne réseaux sociaux de la marque', $html);
        $this->assertStringContainsString('Réseaux sociaux, Site web', $html);
        $this->assertStringContainsString('Article 13 : Signature électronique.', $html);
        $this->assertStringContainsString('Fatou Bello', $html);
        $this->assertStringContainsString('a•••••@gmail.com', $html);
        $this->assertStringNotContainsString(str_repeat('ab', 32), $html);
        $this->assertStringNotContainsString('—', $html);
        $this->assertDoesNotMatchRegularExpression('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', $html);
    }

    public function test_no_quadratic_dash_in_the_template_nor_in_the_contract_mails(): void
    {
        $this->assertSame(13, count(ImageContractTemplate::articles()));
        $this->assertStringNotContainsString('—', json_encode(ImageContractTemplate::articles(), JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('—', (string) file_get_contents(base_path('app/Support/ImageContractTemplate.php')));

        $code = (new SignatureCodeMail('123456', 10, 'Aïcha Bello', true, 'Fatou Bello'))->render();
        $signed = (new ImageContractSignedMail(1, 'WEACT-IMG-AAAA', 'x.pdf'))->render();

        $this->assertStringNotContainsString('—', $code);
        $this->assertStringNotContainsString('—', $signed);
        $this->assertStringContainsString('123456', $code);
    }

    public function test_both_parties_download_the_pdf_as_an_attachment(): void
    {
        Mail::fake();
        $contract = $this->signedContract();
        (new GenerateImageContractPdf($contract->id))->handle();
        $uuid = $contract->booking->uuid;

        foreach ([$this->faceUser, $this->producerUser] as $user) {
            $response = $this->actingAs($user)->get("/api/v1/bookings/{$uuid}/image-contract/pdf");

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        }

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$uuid}")
            ->assertJsonPath('data.image_contract.pdf_ready', true);
    }

    public function test_a_third_party_cannot_download_the_pdf(): void
    {
        Mail::fake();
        $contract = $this->signedContract();
        (new GenerateImageContractPdf($contract->id))->handle();

        $stranger = \App\Models\User::factory()->create([
            'userable_type' => \App\Models\Producer::class,
            'userable_id' => \App\Models\Producer::factory()->create()->id,
        ]);

        $this->actingAs($stranger)
            ->getJson("/api/v1/bookings/{$contract->booking->uuid}/image-contract/pdf")
            ->assertForbidden();
    }

    public function test_the_pdf_answers_404_when_not_ready_or_when_there_is_no_contract(): void
    {
        $contract = $this->signedContract();
        $withoutContract = $this->pendingCashBooking();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$contract->booking->uuid}/image-contract/pdf")
            ->assertNotFound();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$withoutContract->uuid}/image-contract/pdf")
            ->assertNotFound();

        // Chemin enregistré mais fichier absent.
        $contract->update(['pdf_path' => 'image-contracts/missing.pdf']);
        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/bookings/{$contract->booking->uuid}/image-contract/pdf")
            ->assertNotFound();
    }

    public function test_no_ip_address_is_stored_anywhere(): void
    {
        foreach (['image_contracts', 'signature_challenges'] as $table) {
            foreach (\Illuminate\Support\Facades\Schema::getColumnListing($table) as $column) {
                $this->assertDoesNotMatchRegularExpression('/(^|_)ip(_|$)/i', $column, "{$table}.{$column}");
            }
        }

        $this->assertInstanceOf(Booking::class, $this->signedContract()->booking);
    }

    // ===================================================================
    // Contrats de mission (candidature)
    // ===================================================================

    private function candidatureContract(): ImageContract
    {
        [, $candidature] = $this->cashMissionWithAcceptedFace();

        return $this->makeCandidatureContract($candidature);
    }

    public function test_a_candidature_contract_is_mailed_to_the_face_and_the_producer(): void
    {
        Mail::fake();
        $contract = $this->candidatureContract();

        (new GenerateImageContractPdf($contract->id))->handle();

        Storage::disk('local')->assertExists($contract->fresh()->pdf_path);
        Mail::assertSent(ImageContractSignedMail::class, 2);
        Mail::assertSent(ImageContractSignedMail::class, fn (ImageContractSignedMail $m) => $m->hasTo('aicha@gmail.com'));
        Mail::assertSent(ImageContractSignedMail::class, fn (ImageContractSignedMail $m) => $m->hasTo('producteur@example.com'));
        $this->assertNotNull($contract->fresh()->copies_sent_at);
    }

    public function test_a_candidature_contract_of_a_minor_is_also_mailed_to_the_guardian(): void
    {
        Mail::fake();
        $contract = $this->candidatureContract();
        $contract->update(['guardian_name' => 'Fatou Bello', 'guardian_relation' => 'mere']);
        $this->makeChallenge($this->faceUser, $this->candidatePurpose($contract->candidature), '654321', [
            'email' => 'fatou@example.com',
            'guardian_name' => 'Fatou Bello',
            'guardian_relation' => 'mere',
            'consumed_at' => now(),
        ]);

        (new GenerateImageContractPdf($contract->id))->handle();

        Mail::assertSent(ImageContractSignedMail::class, 3);
        Mail::assertSent(ImageContractSignedMail::class, fn (ImageContractSignedMail $m) => $m->hasTo('fatou@example.com'));
    }

    public function test_a_contract_whose_candidature_is_gone_still_gets_its_pdf_and_crashes_nothing(): void
    {
        Mail::fake();
        $contract = $this->candidatureContract();
        $contract->candidature->mission->delete();

        $this->assertNull($contract->fresh()->candidature_id);
        (new GenerateImageContractPdf($contract->id))->handle();

        $this->assertNotNull($contract->fresh()->pdf_path);
        Mail::assertNothingSent();
    }

    public function test_the_pdf_view_of_a_mission_contract_shows_the_title(): void
    {
        $contract = $this->candidatureContract();
        $contract->update(['snapshot' => array_replace_recursive($contract->snapshot, ['projet' => ['titre' => 'Publicité lait local']])]);

        $html = view('contracts.image-authorization', (new GenerateImageContractPdf($contract->id))->viewData($contract->fresh()))->render();

        $this->assertStringContainsString('Publicité lait local', $html);
        $this->assertStringNotContainsString('—', $html);

        // Un booking (sans titre) s'affiche toujours.
        $bookingHtml = view('contracts.image-authorization', (new GenerateImageContractPdf($this->signedContract()->id))->viewData($this->signedContract()->fresh()))->render();
        $this->assertStringNotContainsString('Publicité lait local', $bookingHtml);
    }

    public function test_the_face_and_the_producer_download_the_candidature_pdf_and_a_third_party_is_refused(): void
    {
        Mail::fake();
        $contract = $this->candidatureContract();
        (new GenerateImageContractPdf($contract->id))->handle();
        $uuid = $contract->candidature->uuid;

        foreach ([$this->faceUser, $this->producerUser] as $user) {
            $response = $this->actingAs($user)->get("/api/v1/candidatures/{$uuid}/image-contract/pdf");

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        }

        $strangerProducer = \App\Models\User::factory()->create([
            'userable_type' => \App\Models\Producer::class,
            'userable_id' => \App\Models\Producer::factory()->create()->id,
        ]);
        $strangerFace = \App\Models\User::factory()->create([
            'userable_type' => \App\Models\Face::class,
            'userable_id' => \App\Models\Face::factory()->create()->id,
        ]);

        foreach ([$strangerProducer, $strangerFace] as $user) {
            $this->actingAs($user)->getJson("/api/v1/candidatures/{$uuid}/image-contract/pdf")->assertForbidden();
        }

        $this->assertSame('candidatures.image-contract.pdf', \Illuminate\Support\Facades\Route::getRoutes()->match(\Illuminate\Http\Request::create("/api/v1/candidatures/{$uuid}/image-contract/pdf"))->getName());
    }

    public function test_the_candidature_pdf_answers_404_when_not_ready_or_absent(): void
    {
        $contract = $this->candidatureContract();
        [, $withoutContract] = $this->ugcMissionWithAcceptedFace();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/candidatures/{$contract->candidature->uuid}/image-contract/pdf")
            ->assertNotFound();

        $this->actingAs($this->faceUser)
            ->getJson("/api/v1/candidatures/{$withoutContract->uuid}/image-contract/pdf")
            ->assertNotFound();

        $contract->update(['pdf_path' => 'image-contracts/missing.pdf']);
        $this->actingAs($this->producerUser)
            ->getJson("/api/v1/candidatures/{$contract->candidature->uuid}/image-contract/pdf")
            ->assertNotFound();
    }
}
