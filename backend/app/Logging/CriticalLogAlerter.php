<?php

declare(strict_types=1);

namespace App\Logging;

use App\Notifications\CriticalLogAlertNotification;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Envoie un e-mail aux admins pour chaque log critical/alert/emergency.
 *
 * Enregistré explicitement dans AppServiceProvider (volontairement hors de app/Listeners
 * pour ne pas être doublonné par l'auto-discovery). Ne doit JAMAIS lever ni logger
 * à un niveau critical+ (boucle infinie) : au pire un Log::warning.
 */
class CriticalLogAlerter
{
    private const LEVELS = ['critical', 'alert', 'emergency'];

    private const REDACTED_KEYS = ['password', 'token', 'secret', 'authorization', 'api_key', 'card', 'cvv', 'signature'];

    private static bool $running = false;

    public function handle(MessageLogged $event): void
    {
        if (! in_array($event->level, self::LEVELS, true) || self::$running) {
            return;
        }

        self::$running = true;

        try {
            $this->alert($event);
        } catch (Throwable $e) {
            try {
                Log::warning('Alerte admin critique non envoyée', ['exception' => $e::class, 'message' => $e->getMessage()]);
            } catch (Throwable) {
                // Rien de plus à faire : ne jamais casser l'appelant.
            }
        } finally {
            self::$running = false;
        }
    }

    private function alert(MessageLogged $event): void
    {
        /** @var array<int, string> $recipients */
        $recipients = (array) config('alerts.critical.recipients', []);

        if ($recipients === []) {
            return;
        }

        $message = (string) $event->message;
        $context = $this->sanitize($event->context);

        $fingerprint = hash('sha256', $event->level."\n".$message."\n".json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        ));

        $dedupMinutes = (int) config('alerts.critical.dedup_minutes', 30);

        if (! Cache::add("alerts:critical:dedup:{$fingerprint}", 1, now()->addMinutes($dedupMinutes))) {
            return;
        }

        if (! $this->withinHourlyCap()) {
            return;
        }

        $notification = new CriticalLogAlertNotification(
            $event->level,
            $message,
            $context,
            now()->timezone((string) config('app.business_timezone', config('app.timezone')))->format('d/m/Y H:i:s T'),
        );

        foreach ($recipients as $email) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    private function withinHourlyCap(): bool
    {
        $max = (int) config('alerts.critical.max_per_hour', 20);
        $key = 'alerts:critical:hourly-count';

        Cache::add($key, 0, now()->addHour());
        $count = (int) Cache::increment($key);

        if ($count <= $max) {
            return true;
        }

        if (Cache::add('alerts:critical:throttle-warned', 1, now()->addHour())) {
            Log::warning("Alertes admin critiques limitées : plafond de {$max} e-mails/heure atteint, les suivantes sont ignorées.");
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    private function sanitize(array $context, int $depth = 0): array
    {
        $result = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $result[$key] = '[masqué]';

                continue;
            }

            $result[$key] = match (true) {
                $depth >= 8 => '[profondeur max]',
                is_array($value) => $this->sanitize($value, $depth + 1),
                $value instanceof Throwable => ['class' => $value::class, 'message' => $value->getMessage()],
                is_object($value) => method_exists($value, '__toString') ? (string) $value : $value::class,
                is_resource($value) => '[resource]',
                default => $value,
            };
        }

        return $result;
    }

    private function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::REDACTED_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
