<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Face extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'prenom',
        'username',
        'profile_photo',
        'profile_photo_thumbnail',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
        'thumbnail_url',
    ];

    /**
     * Get the user associated with this Face.
     */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Get the full URL for the profile photo.
     */
    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo
                ? asset('storage/avatars/faces/' . $this->profile_photo)
                : null,
        );
    }

    /**
     * Get the full URL for the profile photo thumbnail.
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->profile_photo_thumbnail
                ? asset('storage/avatars/faces/thumbnails/' . $this->profile_photo_thumbnail)
                : null,
        );
    }

    /**
     * Get the album photos for this Face.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(FacePhoto::class)->orderBy('position');
    }
}
