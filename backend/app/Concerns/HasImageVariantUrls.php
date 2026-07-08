<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Support\ImageVariantGenerator;

/**
 * Shared image-variant URL resolution for the models that store a photo with
 * generated variants (Face, Producer, FacePhoto).
 *
 * The storage layout (dir + column per variant) comes from the single catalog
 * in ImageVariantGenerator::layoutFor(); this trait owns only the URL
 * construction and the fallback policy, in one place — so the coming R2
 * migration rewrites the asset() base here once instead of across 15
 * hand-written accessors.
 *
 * Fallback chains (a variant not generated yet serves a smaller one, never a
 * blank): thumbnail → original, medium → original, grid → medium → original,
 * large → medium → original.
 *
 * The host model keeps its own thin *_url accessors that delegate here, so the
 * appended attribute names (profile_photo_url vs photo_url, thumbnail_url, …)
 * stay exactly as the Resources and the frontend expect.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasImageVariantUrls
{
    protected function resolveVariantUrl(string $variant): ?string
    {
        $layout = ImageVariantGenerator::layoutFor($this);
        $spec = $layout['variants'][$variant] ?? null;

        if ($spec !== null) {
            $value = $this->getAttribute($spec['column']);
            if (is_string($value) && $value !== '') {
                return asset('storage/'.$spec['dir'].'/'.$value);
            }
        }

        // Not generated yet (legacy row / pending job): fall down the chain.
        // resolveVariantUrl('medium') itself falls back to the original.
        return match ($variant) {
            'grid', 'large' => $this->resolveVariantUrl('medium'),
            default => $this->resolveOriginalImageUrl(),
        };
    }

    protected function resolveOriginalImageUrl(): ?string
    {
        $layout = ImageVariantGenerator::layoutFor($this);
        $original = $this->getAttribute($layout['original_column']);

        return is_string($original) && $original !== ''
            ? asset('storage/'.$layout['dir'].'/'.$original)
            : null;
    }
}
