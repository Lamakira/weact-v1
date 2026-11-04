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
        $this->assertStringContainsString('Article 13. Signature électronique.', $html);
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
}
