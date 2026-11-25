<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Face;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Services\Auth\FaceRegistrationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class NotifyLegalTermsUpdateCommand extends Command
{
    private const TYPE = 'legal_terms_updated';

    /**
     * @var string
     */
    protected $signature = 'legal:notify-terms-update {--send : Envoie réellement ; sans cette option, compte seulement}';

    /**
     * @var string
     */
    protected $description = 'Notifie les Faces et Producteurs dont le consentement précède la dernière version des CGU et de la politique de confidentialité (idempotent, re-exécutable)';

    public function handle(): int
    {
        $version = FaceRegistrationService::CONSENT_VERSION;

        if (! $this->option('send')) {
            $this->info(sprintf(
                '%d utilisateur(s) à notifier. Relancez avec --send pour envoyer.',
                $this->recipients($version)->count(),
            ));

            return self::SUCCESS;
        }

        $data = json_encode([
            'message' => "Nos conditions d'utilisation et notre politique de confidentialité ont été mises à jour le 25 novembre 2026. Elles précisent la signature en ligne de l'autorisation d'utilisation de l'image.",
            'url' => '/cgu',
            'version' => $version,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $created = 0;

        // Le filtre exclut les comptes déjà traités au fil des lots : chunkById ne saute rien.
        $this->recipients($version)->chunkById(500, function (Collection $users) use ($data, &$created): void {
            $now = now();
            $rows = $users->map(fn (User $user): array => [
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'type' => self::TYPE,
                'data' => $data,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            // Insertion en lot : contourne VOLONTAIREMENT NotificationObserver
            // (ni diffusion temps réel ni push pour des milliers de comptes).
            Notification::insert($rows);
            $created += count($rows);
        });

        Log::info('legal terms update notifications créées', [
            'version' => $version,
            'created' => $created,
        ]);

        $this->info(sprintf('%d notification(s) créée(s).', $created));

        return self::SUCCESS;
    }

    /**
     * @return Builder<User>
     */
    private function recipients(string $version): Builder
    {
        return User::query()
            ->whereIn('userable_type', [Face::class, Producer::class])
            ->where(function (Builder $query) use ($version): void {
                $query->whereNull('consent_version')
                    ->orWhere('consent_version', '<', $version);
            })
            ->whereNotExists(function (QueryBuilder $query) use ($version): void {
                $query->select(DB::raw(1))
                    ->from('notifications')
                    ->whereColumn('notifications.user_id', 'users.id')
                    ->where('notifications.type', self::TYPE)
                    ->where('notifications.data->version', $version);
            });
    }
}
