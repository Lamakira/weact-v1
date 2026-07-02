<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceVideoType;
use App\Models\Face;
use App\Models\FaceVideo;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FaceVideoSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_face_videos_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('face_videos', [
            'id', 'uuid', 'face_id', 'type', 'filename', 'thumbnail', 'position', 'created_at', 'updated_at',
        ]));
    }

    public function test_faces_table_no_longer_has_acting_video_columns(): void
    {
        $this->assertFalse(Schema::hasColumn('faces', 'acting_video'));
        $this->assertFalse(Schema::hasColumn('faces', 'acting_video_thumbnail'));
    }

    public function test_faces_table_still_has_presentation_video_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('faces', 'presentation_video'));
        $this->assertTrue(Schema::hasColumn('faces', 'presentation_video_thumbnail'));
    }

    public function test_face_video_belongs_to_face(): void
    {
        $video = FaceVideo::factory()->create();

        $this->assertInstanceOf(Face::class, $video->face);
    }

    public function test_face_video_type_is_cast_to_enum(): void
    {
        $video = FaceVideo::factory()->ugc()->create();

        $this->assertInstanceOf(FaceVideoType::class, $video->type);
        $this->assertSame(FaceVideoType::Ugc, $video->type);
    }

    public function test_face_video_generates_uuid_on_create(): void
    {
        $face = Face::factory()->create();

        $video = FaceVideo::create([
            'face_id' => $face->id,
            'type' => FaceVideoType::Acting,
            'filename' => 'sample.mp4',
            'thumbnail' => 'sample.jpg',
            'position' => 1,
        ]);

        $this->assertNotNull($video->uuid);
        $this->assertNotSame('', $video->uuid);
    }

    public function test_face_video_url_accessors_resolve_per_type(): void
    {
        $acting = FaceVideo::factory()->acting()->create(['filename' => 'act.mp4', 'thumbnail' => 'act.jpg']);
        $ugc = FaceVideo::factory()->ugc()->create(['filename' => 'ugc.mp4', 'thumbnail' => 'ugc.jpg']);

        $this->assertStringContainsString('videos/faces/acting/act.mp4', $acting->video_url);
        $this->assertStringContainsString('videos/faces/acting/thumbnails/act.jpg', $acting->thumbnail_url);
        $this->assertStringContainsString('videos/faces/ugc/ugc.mp4', $ugc->video_url);
        $this->assertStringContainsString('videos/faces/ugc/thumbnails/ugc.jpg', $ugc->thumbnail_url);
    }

    public function test_face_videos_cascade_delete_with_face(): void
    {
        $face = Face::factory()->create();
        FaceVideo::factory()->acting()->create(['face_id' => $face->id]);

        $face->delete();

        $this->assertSame(0, FaceVideo::where('face_id', $face->id)->count());
    }

    public function test_face_video_type_enum_values(): void
    {
        $this->assertSame(['acting', 'ugc'], FaceVideoType::values());
    }

    public function test_position_is_unique_per_face_and_type(): void
    {
        $face = Face::factory()->create();
        FaceVideo::factory()->acting()->create(['face_id' => $face->id, 'position' => 1]);

        $this->expectException(QueryException::class);
        FaceVideo::factory()->acting()->create(['face_id' => $face->id, 'position' => 1]);
    }

    public function test_same_position_is_allowed_across_different_types(): void
    {
        $face = Face::factory()->create();
        FaceVideo::factory()->acting()->create(['face_id' => $face->id, 'position' => 1]);
        FaceVideo::factory()->ugc()->create(['face_id' => $face->id, 'position' => 1]);

        $this->assertSame(2, FaceVideo::where('face_id', $face->id)->count());
    }

    public function test_videos_relation_orders_by_type_then_position(): void
    {
        $face = Face::factory()->create();
        FaceVideo::factory()->ugc()->create(['face_id' => $face->id, 'position' => 1]);
        FaceVideo::factory()->acting()->create(['face_id' => $face->id, 'position' => 2]);
        FaceVideo::factory()->acting()->create(['face_id' => $face->id, 'position' => 1]);

        $ordered = $face->videos->map(fn (FaceVideo $v): string => $v->type->value.':'.$v->position)->all();

        $this->assertSame(['acting:1', 'acting:2', 'ugc:1'], $ordered);
    }
}
