<?php

declare(strict_types=1);

namespace App\Services\Whatsapp;

use App\Jobs\SendWhatsappCandidaturesDigest;
use App\Jobs\SendWhatsappMessage;
use App\Models\Booking;
use App\Models\Candidature;
use App\Models\Face;
use App\Models\Mission;
use App\Models\Notification;
use App\Models\Producer;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Support\Whatsapp;
use App\Support\WhatsappAllowlist;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alertes WhatsApp : consentement, construction des paramètres de chaque modèle et envoi journalisé.
 * Tout est best effort : une panne WhatsApp ne bloque jamais une transition de statut ni un paiement.
 */
class WhatsappService
{
    public const SENT = 'sent';

    /** Échec transitoire (réseau, 5xx, 429) : le job peut réessayer. */
    public const RETRY = 'retry';

    /** Refus définitif de Meta (numéro invalide, modèle non approuvé…) ou envoi impossible. */
    public const REJECTED = 'rejected';

    private const PARAM_LIMIT = 80;

    public function __construct(private readonly WhatsappClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isAvailable();
    }

    // ------------------------------------------------------------------ Consentement

    /**
     * Numéro du profil (texte libre) de l'utilisateur, null pour un compte sans profil Face/Producteur.
     */
    public function profileNumber(User $user): ?string
    {
        $user->loadMissing('userable');
        $profile = $user->userable;

        return $profile instanceof Face || $profile instanceof Producer ? $profile->whatsapp_number : null;
    }

    /** Numéro actuel du profil au format de l'API, null si non envoyable. */
    public function currentNumber(User $user): ?string
    {
        return Whatsapp::toE164($this->profileNumber($user));
    }

    /**
     * Inscrit = consentement posé ET numéro consenti identique au numéro actuel du profil
     * (changer de numéro rend l'inscription caduque).
     */
    public function isOptedIn(User $user): bool
    {
        if ($user->whatsapp_opt_in_at === null || $user->whatsapp_opt_in_number === null) {
            return false;
        }

        return $user->whatsapp_opt_in_number === $this->currentNumber($user);
    }

    /**
     * Active (numéro actuel normalisé mémorisé) ou désactive les alertes. Retourne false si l'activation
     * est impossible faute de numéro valide.
     */
    public function setEnabled(User $user, bool $enabled): bool
    {
        if (! $enabled) {
            $user->forceFill(['whatsapp_opt_in_at' => null, 'whatsapp_opt_in_number' => null])->save();

            return true;
        }

        $number = $this->currentNumber($user);
        if ($number === null) {
            return false;
        }

        // Réactivation au même numéro : on conserve la date d'origine.
        $user->forceFill([
            'whatsapp_opt_in_at' => $this->isOptedIn($user) ? $user->whatsapp_opt_in_at : now(),
            'whatsapp_opt_in_number' => $number,
        ])->save();

        return true;
    }

    public function dismissInvite(User $user): void
    {
        $user->forceFill(['whatsapp_invite_dismissed_at' => now()])->save();
    }

    /**
     * Désinscrit tous les comptes dont le numéro consenti est celui de l'expéditeur d'un « STOP ».
     * Retourne le nombre de comptes désinscrits.
     */
    public function unsubscribeNumber(string $from): int
    {
        $candidates = array_values(array_unique(array_filter([
            $from,
            Whatsapp::toE164('+'.$from),
        ])));

        return User::query()
            ->whereIn('whatsapp_opt_in_number', $candidates)
            ->update(['whatsapp_opt_in_at' => null, 'whatsapp_opt_in_number' => null]);
    }

    /**
     * Bloc `whatsapp_alerts` du payload utilisateur.
     *
     * @return array{available: bool, enabled: bool, enabled_at: string|null, number: string|null, has_valid_number: bool, invite_dismissed: bool}
     */
    public function alertsPayload(User $user): array
    {
        if (! $this->isAvailable() || ! in_array($user->userable_type, [Face::class, Producer::class], true)) {
            return [
                'available' => false,
                'enabled' => false,
                'enabled_at' => null,
                'number' => null,
                'has_valid_number' => false,
                'invite_dismissed' => false,
            ];
        }

        $display = Whatsapp::formatForDisplay($this->profileNumber($user));
        $enabled = $this->isOptedIn($user);

        return [
            'available' => true,
            'enabled' => $enabled,
            'enabled_at' => $enabled ? $user->whatsapp_opt_in_at?->toIso8601String() : null,
            'number' => $display,
            'has_valid_number' => $display !== null,
            'invite_dismissed' => $user->whatsapp_invite_dismissed_at !== null,
        ];
    }

    // ------------------------------------------------------------------ Déclencheurs

    /**
     * File l'alerte WhatsApp d'une notification in-app allowlistée (sans effet sinon).
     */
    public function queueForNotification(Notification $notification): void
    {
        if (! $this->isAvailable() || ! WhatsappAllowlist::isAllowed($notification->type)) {
            return;
        }

        $user = User::with('userable')->find($notification->user_id);
        if ($user === null || ! $this->matchesRole($user, WhatsappAllowlist::roleFor($notification->type)) || ! $this->isOptedIn($user)) {
            return;
        }

        $firstName = $this->firstName($user);
        if ($firstName === null) {
            Log::info('WhatsApp skipped: recipient has no display name', ['user_id' => $user->id, 'type' => $notification->type]);

            return;
        }

        if ($notification->type === 'new_candidature') {
            $this->queueNewCandidature($notification, $user, $firstName);

            return;
        }

        $payload = $this->payloadFor($notification, $firstName);
        if ($payload === null) {
            Log::info('WhatsApp skipped: incomplete data for notification', ['notification_id' => $notification->id, 'type' => $notification->type]);

            return;
        }

        SendWhatsappMessage::dispatch(
            $user->id,
            (string) WhatsappAllowlist::templateFor($notification->type),
            $payload['params'],
            $payload['suffix'],
            $payload['context'],
        )->afterCommit();
    }

    /**
     * Message « non lu » : envoi synchrone (délai HTTP court) à la place de l'e-mail. Retourne true
     * si Meta a accepté le message ; sinon l'appelant envoie l'e-mail.
     */
    public function sendUnreadMessage(User $recipient, string $senderName, string $contextLabel, string $urlSuffix, string $context): bool
    {
        if (! $this->isAvailable() || ! $this->isOptedIn($recipient)) {
            return false;
        }

        $firstName = $this->firstName($recipient);
        if ($firstName === null) {
            return false;
        }

        try {
            return $this->deliver($recipient, 'weact_unread_message', [
                $firstName,
                $this->param($senderName),
                $this->param($contextLabel),
            ], $urlSuffix, $context) === self::SENT;
        } catch (Throwable $e) {
            Log::warning('WhatsApp unread message failed', ['user_id' => $recipient->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Envoi unitaire d'un modèle, journalisé : queued puis sent (wamid) ou failed (erreur Meta).
     *
     * @param  list<string>  $params
     */
    public function deliver(User $user, string $template, array $params, ?string $urlSuffix, ?string $context): string
    {
        $to = $this->currentNumber($user);
        if ($to === null) {
            return self::REJECTED;
        }

        $log = WhatsappMessage::create([
            'user_id' => $user->id,
            'template' => $template,
            'to' => $to,
            'context' => $context,
            'status' => WhatsappMessage::QUEUED,
        ]);

        try {
            $response = $this->client->sendTemplate($to, $template, $params, $urlSuffix);
        } catch (Throwable $e) {
            $log->update([
                'status' => WhatsappMessage::FAILED,
                'error_message' => Str::limit($e->getMessage(), 500, ''),
                'status_updated_at' => now(),
            ]);
            Log::warning('WhatsApp send failed (network)', ['user_id' => $user->id, 'template' => $template, 'error' => $e->getMessage()]);

            return self::RETRY;
        }

        $wamid = $response->json('messages.0.id');
        if ($response->successful() && is_string($wamid) && $wamid !== '') {
            $log->update([
                'status' => WhatsappMessage::SENT,
                'wamid' => $wamid,
                'status_updated_at' => now(),
            ]);

            return self::SENT;
        }

        $code = $response->json('error.code');
        $message = $response->json('error.error_data.details') ?? $response->json('error.message') ?? 'HTTP '.$response->status();
        $log->update([
            'status' => WhatsappMessage::FAILED,
            'error_code' => $code !== null ? Str::limit((string) $code, 32, '') : null,
            'error_message' => Str::limit((string) $message, 500, ''),
            'status_updated_at' => now(),
        ]);
        Log::warning('WhatsApp send rejected', [
            'user_id' => $user->id,
            'template' => $template,
            'status' => $response->status(),
            'error_code' => $code,
        ]);

        $status = $response->status();

        return $status === 429 || $status >= 500 ? self::RETRY : self::REJECTED;
    }

    /**
     * Applique un statut de livraison du webhook au journal (jamais de régression sent -> queued).
     *
     * @param  array<string, mixed>  $status
     */
    public function applyStatus(array $status): void
    {
        $wamid = $status['id'] ?? null;
        $new = $status['status'] ?? null;
        $rank = ['queued' => 0, 'failed' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

        if (! is_string($wamid) || ! is_string($new) || ! isset($rank[$new])) {
            return;
        }

        $log = WhatsappMessage::where('wamid', $wamid)->first();
        if ($log === null) {
            return;
        }

        $current = $rank[$log->status] ?? 0;
        if ($new === WhatsappMessage::FAILED ? $current >= 2 : $rank[$new] <= $current) {
            return;
        }

        $changes = ['status' => $new, 'status_updated_at' => $this->statusTime($status)];
        if ($new === WhatsappMessage::FAILED) {
            $error = is_array($status['errors'][0] ?? null) ? $status['errors'][0] : [];
            $changes['error_code'] = isset($error['code']) ? Str::limit((string) $error['code'], 32, '') : null;
            $changes['error_message'] = Str::limit((string) ($error['error_data']['details'] ?? $error['message'] ?? $error['title'] ?? ''), 500, '') ?: null;
        }

        $log->update($changes);
    }

    // ------------------------------------------------------------------ Candidatures groupées

    private function queueNewCandidature(Notification $notification, User $user, string $firstName): void
    {
        $mission = Mission::find($notification->data['mission_id'] ?? null);
        if ($mission === null) {
            return;
        }

        [$windowKey, $pendingKey] = self::candidatureKeys($user->id, $mission->id);
        $hours = (int) config('whatsapp.candidatures_window_hours', 6);

        // Fenêtre déjà ouverte : la candidature est cumulée pour le message différé.
        if (! Cache::add($windowKey, true, now()->addHours($hours))) {
            Cache::add($pendingKey, 0, now()->addHours($hours * 2));
            Cache::increment($pendingKey);

            return;
        }

        // 1re candidature de la fenêtre : part tout de suite et ouvre la fenêtre ; le cumul suit à la fermeture.
        SendWhatsappMessage::dispatch(
            $user->id,
            'weact_new_candidatures',
            [$firstName, $this->param($mission->titre), self::candidaturesLabel(1)],
            self::missionCandidaturesSuffix($mission),
            "new_candidatures:{$mission->id}",
        )->afterCommit();

        SendWhatsappCandidaturesDigest::dispatch($user->id, $mission->id)
            ->delay(now()->addHours($hours))
            ->afterCommit();
    }

    /**
     * @return array{0: string, 1: string} clé de fenêtre, clé du cumul
     */
    public static function candidatureKeys(int $userId, int $missionId): array
    {
        return ["whatsapp:candidatures:window:{$userId}:{$missionId}", "whatsapp:candidatures:pending:{$userId}:{$missionId}"];
    }

    public static function candidaturesLabel(int $count): string
    {
        return $count === 1 ? '1 nouvelle candidature' : "{$count} nouvelles candidatures";
    }

    public static function missionCandidaturesSuffix(Mission $mission): string
    {
        return "producer/missions/{$mission->uuid}?tab=candidatures";
    }

    public function firstNameFor(User $user): ?string
    {
        return $this->firstName($user);
    }

    public function paramFor(string $value): string
    {
        return $this->param($value);
    }

    // ------------------------------------------------------------------ Paramètres par type

    /**
     * @return array{params: list<string>, suffix: string, context: string}|null null = données incomplètes, rien n'est envoyé
     */
    private function payloadFor(Notification $notification, string $firstName): ?array
    {
        $data = $notification->data;
        $suffix = $this->urlSuffix($data['url'] ?? null);
        if ($suffix === null) {
            return null;
        }

        if ($notification->type === 'candidature_accepted') {
            // Seule l'acceptation émise après paiement de la place (hybride) est annoncée ainsi.
            if (($data['place_paid'] ?? false) !== true) {
                return null;
            }
            $candidature = Candidature::with('mission.producer')->find($data['candidature_id'] ?? null);
            $mission = $candidature?->mission;
            if ($mission === null) {
                return null;
            }

            return [
                'params' => [$firstName, $this->param((string) $mission->producer?->display_name), $this->param($mission->titre)],
                'suffix' => $suffix,
                'context' => "candidature_accepted:{$candidature->id}",
            ];
        }

        $booking = Booking::with(['face.userable', 'producer.userable'])->find($data['booking_id'] ?? null);
        if ($booking === null) {
            return null;
        }

        $producerName = $this->param((string) data_get($booking, 'producer.userable.display_name'));
        $faceName = $this->param((string) data_get($booking, 'face.userable.display_name'));
        $context = "{$notification->type}:{$booking->id}";

        $params = match ($notification->type) {
            'booking_received' => $this->bookingRequestParams($booking, $firstName, $producerName),
            'booking_response_reminder' => $this->bookingReminderParams($booking, $firstName, $producerName),
            'booking_paid', 'ugc_commission_paid' => [$firstName, $producerName, $this->param((string) $booking->type_contenu)],
            'booking_accepted' => $this->bookingOutcomeParams($booking, $firstName, $faceName),
            'booking_refused' => ($data['automatic'] ?? false) === true ? null : $this->bookingOutcomeParams($booking, $firstName, $faceName),
            default => null,
        };

        return $params === null ? null : ['params' => $params, 'suffix' => $suffix, 'context' => $context];
    }

    /**
     * @return list<string>|null
     */
    private function bookingRequestParams(Booking $booking, string $firstName, string $producerName): ?array
    {
        // Pas d'échéance ni de date de tournage (dotation UGC) : le modèle n'a pas de sens, on ne l'envoie pas.
        $deadline = $booking->faceResponseDeadline();
        if ($deadline === null || $booking->date_debut === null) {
            return null;
        }

        return [$firstName, $producerName, $this->dayLabel($booking->date_debut), $this->deadlineLabel($deadline)];
    }

    /**
     * @return list<string>|null
     */
    private function bookingReminderParams(Booking $booking, string $firstName, string $producerName): ?array
    {
        $deadline = $booking->faceResponseDeadline();

        return $deadline === null ? null : [$firstName, $producerName, $this->deadlineLabel($deadline)];
    }

    /**
     * @return list<string>|null
     */
    private function bookingOutcomeParams(Booking $booking, string $firstName, string $faceName): ?array
    {
        return $booking->date_debut === null ? null : [$firstName, $faceName, $this->dayLabel($booking->date_debut)];
    }

    // ------------------------------------------------------------------ Aides

    private function matchesRole(User $user, ?string $role): bool
    {
        return match ($role) {
            WhatsappAllowlist::FACE => $user->userable_type === Face::class,
            WhatsappAllowlist::PRODUCER => $user->userable_type === Producer::class,
            default => false,
        };
    }

    /** Prénom = premier mot du nom affiché ; null s'il est vide. */
    private function firstName(User $user): ?string
    {
        $user->loadMissing('userable');
        $name = trim((string) $user->userable?->display_name);
        if ($name === '') {
            return null;
        }

        return $this->param(explode(' ', $name)[0]);
    }

    /** Paramètre de corps accepté par Meta : une ligne, sans espaces multiples, non vide, borné. */
    private function param(string $value): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $clean === '' ? '-' : Str::limit($clean, self::PARAM_LIMIT, '…');
    }

    /** Suffixe du bouton URL : le chemin de la notification sans « / » initial, uniquement un chemin interne sûr. */
    private function urlSuffix(mixed $url): ?string
    {
        if (! is_string($url) || preg_match('#^/[A-Za-z0-9][A-Za-z0-9/_\-.?=&]*$#', $url) !== 1) {
            return null;
        }

        return ltrim($url, '/');
    }

    /** « lundi 27 octobre » (une date de booking est un jour calendaire, sans conversion de fuseau). */
    private function dayLabel(CarbonInterface $date): string
    {
        return Carbon::parse($date->toDateString(), (string) config('app.business_timezone'))
            ->locale('fr')
            ->isoFormat('dddd D MMMM');
    }

    /** « samedi 25 octobre à 16 h » (fuseau métier ; « 16 h 30 » si les minutes ne sont pas nulles). */
    private function deadlineLabel(CarbonInterface $moment): string
    {
        $local = Carbon::instance($moment)->setTimezone((string) config('app.business_timezone'))->locale('fr');

        return $local->isoFormat('dddd D MMMM').' à '.$local->format('G').' h'.($local->minute !== 0 ? ' '.$local->format('i') : '');
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function statusTime(array $status): Carbon
    {
        $timestamp = $status['timestamp'] ?? null;

        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : now();
    }
}
