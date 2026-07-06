<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $face_id
 * @property string $uuid
 * @property string|null $filename
 * @property string|null $thumbnail
 * @property string|null $medium
 * @property string|null $grid
 * @property string|null $large
 * @property int $position
 * @property-read string|null $photo_url
 * @property-read string|null $thumbnail_url
 * @property-read string|null $medium_url
 * @property-read string|null $grid_url
 * @property-read string|null $large_url
 * @property-read \App\Models\Face|null $face
 */
class FacePhoto extends Model
{
    use HasFactory;
    use HasRouteUuid;

    protected $fillable = [
        'face_id',
        'filename',
        'thumbnail',
        'medium',
        'grid',
        'large',
        'position',
    ];

    protected $appends = [
        'photo_url',
        'thumbnail_url',
        'medium_url',
        'grid_url',
        'large_url',
    ];

    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->filename
                ? asset('storage/avatars/faces/albums/'.$this->filename)
                : null,
        );
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->thumbnail
                ? asset('storage/avatars/faces/albums/thumbnails/'.$this->thumbnail)
                : $this->photo_url, // fallback to original while the variant job is pending
        );
    }

    protected function mediumUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->medium
                ? asset('storage/avatars/faces/albums/medium/'.$this->medium)
                : $this->photo_url, // fallback to original
        );
    }

    protected function gridUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->grid
                ? asset('storage/avatars/faces/albums/grid/'.$this->grid)
                : $this->medium_url, // fallback to medium then original (legacy rows / pending job)
        );
    }

    protected function largeUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->large
                ? asset('storage/avatars/faces/albums/large/'.$this->large)
                : $this->photo_url, // fallback to original
        );
    }
}
