<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Envoie un modèle WhatsApp déjà paramétré. Le consentement et la disponibilité sont revérifiés à
 * l'envoi (désinscription entre-temps). Seuls les échecs transitoires sont rejoués.
 */
class SendWhatsappMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Sous le timeout du worker : un appel HTTP bloqué ne doit pas figer la file. */
    public int $timeout = 45;

    /**
     * @param  list<string>  $params
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $template,
        public readonly array $params,
        public readonly ?string $urlSuffix,
        public readonly ?string $context,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(WhatsappService $whatsapp): void
    {
        $user = User::with('userable')->find($this->userId);

        if ($user === null || ! $whatsapp->isAvailable() || ! $whatsapp->isOptedIn($user)) {
            return;
        }

        if ($whatsapp->deliver($user, $this->template, $this->params, $this->urlSuffix, $this->context) === WhatsappService::RETRY) {
            throw new RuntimeException("WhatsApp {$this->template} : échec transitoire, nouvelle tentative.");
        }
    }
}
