<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Producer;
use App\Models\ProductPhoto;
use App\Support\ImageVariantGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates the missing image variants (150 thumb / 800 medium / 400 grid /
 * 1600 large — grid/large only for a ProductPhoto) for a Face profile photo,
 * a Producer profile photo, a Face album photo or a UGC product photo, from
 * the original stored on the model's disk (ImageVariantGenerator::diskFor).
 *
 * The target is carried as (type, id) instead of a serialized model on
 * purpose: a model deleted between dispatch and run must be a logged no-op,
 * not a ModelNotFoundException on unserialize (anti queue-poison convention).
 */
class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    /**
     * Borne explicite (secondes), STRICTEMENT inférieure à `retry_after` de la connexion de
     * queue (sinon un job encore en cours serait repris en double). Sur dépassement, le job
     * est marqué en échec (`failOnTimeout`) et n'est jamais rejoué (`tries = 1`) : sans cela,
     * le worker se tue puis le job revient après `retry_after` jusqu'à `--tries`. Les erreurs
     * de décodage sont de toute façon absorbées (warning, pas de retry).
     */
    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $tries = 1;

    public const TYPE_FACE = 'face';

    public const TYPE_PRODUCER = 'producer';

    public const TYPE_FACE_PHOTO = 'face_photo';

    public const TYPE_PRODUCT_PHOTO = 'product_photo';

    public function __construct(
        public readonly string $targetType,
        public readonly int $targetId,
    ) {
        // Wait for the enclosing DB transaction (album upload) to commit before
        // queueing: the job re-reads the row by id, which must be visible.
        $this->afterCommit = true;
    }

    public static function forModel(Face|Producer|FacePhoto|ProductPhoto $model): self
    {
        $type = match (true) {
            $model instanceof Face => self::TYPE_FACE,
            $model instanceof Producer => self::TYPE_PRODUCER,
            $model instanceof ProductPhoto => self::TYPE_PRODUCT_PHOTO,
            default => self::TYPE_FACE_PHOTO,
        };

        return new self($type, (int) $model->getKey());
    }

    public function handle(ImageVariantGenerator $generator): void
    {
        $model = $this->resolveTarget();

        if (! $model) {
            // Model deleted between dispatch and run (or unknown type) —
            // expected lifecycle race, log + no-op.
            Log::info('GenerateImageVariants: cible introuvable, job ignoré', [
                'target_type' => $this->targetType,
                'target_id' => $this->targetId,
            ]);

            return;
        }

        try {
            $result = $generator->generate($model);
        } catch (QueryException $e) {
            // Infra failure — rethrow so the queue can retry.
            throw $e;
        } catch (\Throwable $e) {
            // Corrupt/unreadable source or encoder failure: deterministic, a
            // retry cannot fix it — log + no-op instead of poisoning the queue.
            Log::warning('GenerateImageVariants: échec de génération, job abandonné', [
                'target_type' => $this->targetType,
                'target_id' => $this->targetId,
                'exception' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return;
        }

        if ($result['missing_source']) {
            Log::info('GenerateImageVariants: fichier original absent, job ignoré', [
                'target_type' => $this->targetType,
                'target_id' => $this->targetId,
            ]);
        }
    }

    private function resolveTarget(): Face|Producer|FacePhoto|ProductPhoto|null
    {
        return match ($this->targetType) {
            self::TYPE_FACE => Face::find($this->targetId),
            self::TYPE_PRODUCER => Producer::find($this->targetId),
            self::TYPE_FACE_PHOTO => FacePhoto::find($this->targetId),
            self::TYPE_PRODUCT_PHOTO => ProductPhoto::find($this->targetId),
            default => null,
        };
    }
}
