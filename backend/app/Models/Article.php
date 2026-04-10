<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasRouteUuid;
use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $admin_id
 * @property string $uuid
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $excerpt
 * @property ArticleCategory $category
 * @property ArticleStatus $status
 * @property string|null $featured_image
 * @property-read string|null $featured_image_url
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property Admin|null $admin
 */
class Article extends Model
{
    use HasFactory, HasRouteUuid;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'admin_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'category',
        'status',
        'featured_image',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ArticleCategory::class,
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Article $article): void {
            if (empty($article->slug)) {
                $article->slug = self::generateUniqueSlug($article->title);
            }
        });

        static::updating(function (Article $article): void {
            if ($article->isDirty('title')) {
                $article->slug = self::generateUniqueSlug($article->title, $article->id);
            }
        });
    }

    private const FEATURED_IMAGE_PATH = 'articles/featured';

    /**
     * Get the full URL for the featured image.
     */
    protected function featuredImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->featured_image
                ? asset('storage/'.self::FEATURED_IMAGE_PATH.'/'.$this->featured_image)
                : null,
        );
    }

    /**
     * Get the admin that authored the article.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Scope a query to only include published articles.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Published);
    }

    /**
     * Scope a query to only include draft articles.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Draft);
    }

    /**
     * Scope a query to only include articles in a specific category.
     */
    public function scopeInCategory(Builder $query, ArticleCategory $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Generate a unique slug from the given title.
     */
    public static function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);

        if ($slug === '') {
            $slug = 'article';
        }

        $original = $slug;
        $counter = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = "{$original}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
