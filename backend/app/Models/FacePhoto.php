<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacePhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'face_id',
        'filename',
        'thumbnail',
        'medium',
        'position',
    ];

    protected $appends = [
        'photo_url',
        'thumbnail_url',
        'medium_url',
    ];

    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->filename
                ? asset('storage/avatars/faces/albums/' . $this->filename)
                : null,
        );
    }

    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->thumbnail
                ? asset('storage/avatars/faces/albums/thumbnails/' . $this->thumbnail)
                : null,
        );
    }

    protected function mediumUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->medium
                ? asset('storage/avatars/faces/albums/medium/' . $this->medium)
                : $this->photo_url, // fallback to original
        );
    }
}
