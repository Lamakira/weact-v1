<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Face;
use App\Models\FacePhoto;
use App\Models\Producer;
use App\Support\ImageVariantGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates the missing image variants (150 thumb / 800 medium / 400 grid /
 * 1600 large) for a Face profile photo, a Producer profile photo or a Face
 * album photo, from the original stored on disk.
 *
 * The target is carried as (type, id) instead of a serialized model on
 * purpose: a model deleted between dispatch and run must be a logged no-op,
 * not a ModelNotFoundException on unserialize (anti queue-poison convention).
 */
class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    public const TYPE_FACE = 'face';

    public const TYPE_PRODUCER = 'producer';

    public const TYPE_FACE_PHOTO = 'face_photo';

    public function __construct(
        public readonly string $targetType,
        public readonly int $targetId,
    ) {
        // Wait for the enclosing DB transaction (album upload) to commit before
        // queueing: the job re-reads the row by id, which must be visible.
        $this->afterCommit = true;
    }

    public static function forModel(Face|Producer|FacePhoto $model): self
    {
        $type = match (true) {
            $model instanceof Face => self::TYPE_FACE,
            $model instanceof Producer => self::TYPE_PRODUCER,
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

    private function resolveTarget(): Face|Producer|FacePhoto|null
    {
        return match ($this->targetType) {
            self::TYPE_FACE => Face::find($this->targetId),
            self::TYPE_PRODUCER => Producer::find($this->targetId),
            self::TYPE_FACE_PHOTO => FacePhoto::find($this->targetId),
            default => null,
        };
    }
}
