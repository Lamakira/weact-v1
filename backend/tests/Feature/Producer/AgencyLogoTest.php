<?php

declare(strict_types=1);

namespace Tests\Feature\Producer;

use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgencyLogoTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    private Producer $agencyProducer;

    private User $particulierUser;

    private Producer $particulierProducer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Create Agency Producer user
        $this->agencyProducer = Producer::factory()->agency()->create();
        $this->agencyUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->agencyProducer->id,
        ]);

        // Create Particulier Producer user
        $this->particulierProducer = Producer::factory()->individual()->create();
        $this->particulierUser = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $this->particulierProducer->id,
        ]);
    }

    public function test_agency_can_upload_jpg_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg', 500, 500);

        $response = $this->actingAs($this->agencyUser)
            ->postJson('/api/v1/producer/profile/logo', [
                'logo' => $file,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'display_name',
                    'agency_logo_url',
                    'agency_logo_thumbnail_url',
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Logo de l\'agence mis à jour',
            ]);

        $this->agencyProducer->refresh();
        $this->assertNotNull($this->agencyProducer->agency_logo);
        $this->assertNotNull($this->agencyProducer->agency_logo_thumbnail);

        // Verify files exist in storage
        Storage::disk('public')->assertExists('logos/agencies/'.$this->agencyProducer->agency_logo);
        Storage::disk('public')->assertExists('logos/agencies/thumbnails/'.$this->agencyProducer->agency_logo_thumbnail);
    }

    public function test_agency_can_upload_png_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 500, 500);

        $response = $this->actingAs($this->agencyUser)
            ->postJson('/api/v1/producer/profile/logo', [
                'logo' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Logo de l\'agence mis à jour');

        $this->agencyProducer->refresh();
        $this->assertNotNull($this->agencyProducer->agency_logo);
    }

    public function test_thumbnail_is_generated_on_logo_upload(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg', 1000, 1000);

        $response = $this->actingAs($this->agencyUser)
            ->postJson('/api/v1/producer/profile/logo', [
                'logo' => $file,
            ]);

        $response->assertOk();

        $this->agencyProducer->refresh();
        $this->assertNotNull($this->agencyProducer->agency_logo_thumbnail);

        // Verify thumbnail exists
        Storage::disk('public')->assertExists('logos/agencies/thumbnails/'.$this->agencyProducer->agency_logo_thumbnail);
    }

    public function test_old_logo_is_deleted_when_uploading_new_one(): void
    {
        // Upload first logo
        $firstFile = UploadedFile::fake()->image('first.jpg', 500, 500);
        $this->actingAs($this->agencyUser)
            ->postJson('/api/v1/producer/profile/logo', ['logo' => $firstFile]);

        $this->agencyProducer->refresh();
        $oldLogoPath = 'logos/agencies/'.$this->agencyProducer->agency_logo;
        $oldThumbnailPath = 'logos/agencies/thumbnails/'.$this->agencyProducer->agency_logo_thumbnail;

        // Verify first logo exists
        Storage::disk('public')->assertExists($oldLogoPath);
        Storage::disk('public')->assertExists($oldThumbnailPath);

        // Upload second logo
        $secondFile = UploadedFile::fake()->image('second.jpg', 500, 500);
        $response = $this->actingAs($this->agencyUser)
            ->postJson('/api/v1/producer/profile/logo', ['logo' => $secondFile]);

        $response->assertOk();

        // Old logo should be deleted
        Storage::disk('public')->assertMissing($oldLogoPath);
        Storage::disk('public')->assertMissing($oldThumbnailPath);

        // New logo should exist
        $this->agencyProducer->refresh();
        Storage::disk('public')->assertExists('logos/agencies/'.$this->agencyProducer->agency_logo);
    }

    public function test_agency_can_delete_logo(): void
    {
        // First upload a logo
        $file = UploadedFile::fake()->image('logo.jpg', 500, 500);
        $this->actingAs($this->agencyUser)
            ->postJson('/api/v1/producer/profile/logo', ['logo' => $file]);

        $this->agencyProducer->refresh();
        $logoPath = 'logos/agencies/'.$this->agencyProducer->agency_logo;
        $thumbnailPath = 'logos/agencies/thumbnails/'.$this->agencyProducer->agency_logo_thumbnail;

        // Verify logo exists
        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertExists($thumbnailPath);

        // Delete the logo
        $response = $this->actingAs($this->agencyUser)
            ->deleteJson('/api/v1/producer/profile/logo');

        $response->assertOk()
            ->assertJson([
                'message' => 'Logo de l\'agence supprimé',
            ]);

        // Verify files are deleted
        Storage::disk('public')->assertMissing($logoPath);
        Storage::disk('public')->assertMissing($thumbnailPath);

        // Verify database is updated
        $this->agencyProducer->refresh();
        $this->assertNull($this->agencyProducer->agency_logo);
        $this->assertNull($this->agencyProducer->agency_logo_thumbnail);
    }

    public function test_rejects_invalid_file_type_gif(): void
    {
        $file = UploadedFile::fake()->image('logo.gif', 500, 500);

        $response = $this->actingAs($this->agencyUser)
            ->postJson('/api/v1/producer/profile/logo', [
                'logo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_rejects_oversized_file_over_2mb(): void
    {
        // Create a file larger than 2MB (2048KB)
        $file = UploadedFile::fake()->image('logo.jpg')->size(3 * 1024);

        $response = $this->actingAs($this->agencyUser)
            ->postJson('/api/v1/producer/profile/logo', [
                'logo' => $file,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_particulier_cannot_upload_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg', 500, 500);

        $response = $this->actingAs($this->particulierUser)
            ->postJson('/api/v1/producer/profile/logo', [
                'logo' => $file,
            ]);

        $response->assertForbidden();
    }

    public function test_particulier_cannot_delete_logo(): void
    {
        $response = $this->actingAs($this->particulierUser)
            ->deleteJson('/api/v1/producer/profile/logo');

        $response->assertForbidden();
    }

    public function test_particulier_cannot_get_logo(): void
    {
        $response = $this->actingAs($this->particulierUser)
            ->getJson('/api/v1/producer/profile/logo');

        $response->assertForbidden();
    }

    public function test_face_cannot_access_producer_logo_endpoints(): void
    {
        $face = Face::factory()->create();
        $faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        $file = UploadedFile::fake()->image('logo.jpg', 500, 500);

        $response = $this->actingAs($faceUser)
            ->postJson('/api/v1/producer/profile/logo', [
                'logo' => $file,
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_upload_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.jpg', 500, 500);

        $response = $this->postJson('/api/v1/producer/profile/logo', [
            'logo' => $file,
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_get_logo(): void
    {
        $response = $this->getJson('/api/v1/producer/profile/logo');

        $response->assertUnauthorized();
    }

    public function test_agency_can_get_logo_urls(): void
    {
        // First upload a logo
        $file = UploadedFile::fake()->image('logo.jpg', 500, 500);
        $this->actingAs($this->agencyUser)
            ->postJson('/api/v1/producer/profile/logo', ['logo' => $file]);

        $response = $this->actingAs($this->agencyUser)
            ->getJson('/api/v1/producer/profile/logo');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'agency_logo_url',
                    'agency_logo_thumbnail_url',
                ],
            ]);

        $this->assertNotNull($response->json('data.agency_logo_url'));
        $this->assertNotNull($response->json('data.agency_logo_thumbnail_url'));
    }

    public function test_agency_gets_null_urls_when_no_logo(): void
    {
        $response = $this->actingAs($this->agencyUser)
            ->getJson('/api/v1/producer/profile/logo');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'agency_logo_url' => null,
                    'agency_logo_thumbnail_url' => null,
                ],
            ]);
    }
}
