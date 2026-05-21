<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\FaceVideoType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $face_id
 * @property \App\Enums\FaceVideoType $type
 * @property string $filename
 * @property string|null $thumbnail
 * @property int $position
 * @property-read string|null $video_url
 * @property-read string|null $thumbnail_url
 * @property-read \App\Models\Face|null $face
 */
class FaceVideo extends Model
{
    use HasFactory;
    use HasRouteUuid;

    protected $fillable = [
        'face_id',
        'type',
        'filename',
        'thumbnail',
        'position',
    ];

    protected $casts = [
        'type' => FaceVideoType::class,
    ];

    protected $appends = [
        'video_url',
        'thumbnail_url',
    ];

    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }

    protected function videoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->filename
                ? asset('storage/videos/faces/'.$this->type->value.'/'.$this->filename)
                : null,
        );
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->thumbnail
                ? asset('storage/videos/faces/'.$this->type->value.'/thumbnails/'.$this->thumbnail)
                : null,
        );
    }
}
