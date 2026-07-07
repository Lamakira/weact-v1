<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasImageVariantUrls;
use App\Concerns\HasRouteUuid;
use App\Support\ImageVariantGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

/**
 * Photo produit d'une dotation UGC — owner polymorphe : Booking (deal direct) |
 * Mission (appel à projets). Uploadée UNIQUEMENT à la création (0-2 photos,
 * kind='product' — la spec B ajoutera `reception`/Shipment).
 *
 * Stockage mixte (décision PO 2026-07-06) : la colonne `disk` est posée à la
 * création et rend la row autoportante — `public` pour une Mission (vitrine
 * visible des candidates, URLs asset directes) ; disque UGC privé pour un
 * Booking (URLs signées via ProductPhotoMediaController, réservées aux deux
 * parties — jamais d'URL publique).
 *
 * @property int $id
 * @property string $uuid
 * @property string $owner_type
 * @property int $owner_id
 * @property string $kind
 * @property int $position
 * @property string $disk
 * @property string $filename
 * @property string|null $grid
 * @property string|null $large
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $photo_url
 * @property-read string|null $grid_url
 * @property-read string|null $large_url
 * @property-read Booking|Mission|null $owner
 */
class ProductPhoto extends Model
{
    use HasFactory;
    use HasImageVariantUrls;
    use HasRouteUuid;

    /**
     * Clés morph volontairement HORS fillable : posées par la relation
     * $owner->productPhotos()->create(), jamais mass-assignées depuis l'input
     * (calque Deliverable/Shipment).
     *
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'position',
        'disk',
        'filename',
        'grid',
        'large',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * URLs disk-aware : rows publiques (Mission) → asset() via le trait partagé
     * (même fallback chain que FacePhoto) ; rows privées (Booking) → routes
     * signées (la signature EST la garde, calque livrables D-4.4.c). Le
     * fallback « variante pas encore générée » d'une row privée pointe la
     * route signée de l'ORIGINAL (jamais d'asset public).
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->isPublicDisk()
            ? $this->resolveOriginalImageUrl()
            : $this->signedMediaUrl('original'));
    }

    protected function gridUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->variantOrFallbackUrl('grid'));
    }

    protected function largeUrl(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->variantOrFallbackUrl('large'));
    }

    private function isPublicDisk(): bool
    {
        return $this->disk === ImageVariantGenerator::DISK;
    }

    private function variantOrFallbackUrl(string $variant): ?string
    {
        if ($this->isPublicDisk()) {
            return $this->resolveVariantUrl($variant);
        }

        $layout = ImageVariantGenerator::layoutFor($this);
        $spec = $layout['variants'][$variant] ?? null;
        $value = $spec !== null ? $this->getAttribute($spec['column']) : null;

        return is_string($value) && $value !== ''
            ? $this->signedMediaUrl($variant)
            : $this->signedMediaUrl('original');
    }

    private function signedMediaUrl(string $variant): string
    {
        $ttl = (int) config('ugc.product_photo_url_ttl_minutes', 30);

        return URL::temporarySignedRoute(
            'product-photos.'.$variant,
            now()->addMinutes($ttl),
            ['productPhoto' => $this->uuid],
        );
    }
}
