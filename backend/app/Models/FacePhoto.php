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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'face_id',
        'filename',
        'thumbnail',
        'position',
    ];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'photo_url',
        'thumbnail_url',
    ];

    /**
     * Get the face that owns this photo.
     */
    public function face(): BelongsTo
    {
        return $this->belongsTo(Face::class);
    }

    /**
     * Get the photo URL.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->filename
                ? asset('storage/avatars/faces/albums/' . $this->filename)
                : null,
        );
    }

    /**
     * Get the thumbnail URL.
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->thumbnail
                ? asset('storage/avatars/faces/albums/thumbnails/' . $this->thumbnail)
                : null,
        );
    }
}
