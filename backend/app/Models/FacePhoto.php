<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasImageVariantUrls;
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
    use HasImageVariantUrls;
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

    /**
     * Album-photo URLs — original + 150/800/400/1600 variants. Storage layout
     * and fallback policy live in the shared HasImageVariantUrls trait (driven
     * by the ImageVariantGenerator catalog); these accessors only name the
     * appended attributes (the original is `photo_url` here, not
     * `profile_photo_url`).
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->resolveOriginalImageUrl());
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->resolveVariantUrl('thumbnail'));
    }

    protected function mediumUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->resolveVariantUrl('medium'));
    }

    protected function gridUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->resolveVariantUrl('grid'));
    }

    protected function largeUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->resolveVariantUrl('large'));
    }
}
