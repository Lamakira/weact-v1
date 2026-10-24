<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Whatsapp\WhatsappClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Crée chez Meta les modèles WhatsApp déclarés dans config/whatsapp.php qui n'y existent pas encore
 * (jamais de mise à jour ni de suppression), puis affiche le statut de chacun (PENDING, APPROVED…).
 * Utilisable avant d'activer la fonctionnalité : seuls les identifiants Meta sont requis.
 */
class SyncWhatsappTemplatesCommand extends Command
{
    protected $signature = 'whatsapp:sync-templates';

    protected $description = 'Create the missing WhatsApp message templates at Meta and show their status';

    public function handle(WhatsappClient $client): int
    {
        if (blank(config('whatsapp.business_account_id')) || blank(config('whatsapp.access_token'))) {
            $this->error('WHATSAPP_BUSINESS_ACCOUNT_ID et WHATSAPP_ACCESS_TOKEN sont requis.');

            return self::FAILURE;
        }

        try {
            $existing = $client->existingTemplates();
        } catch (Throwable $e) {
            $this->error("Lecture des modèles impossible : {$e->getMessage()}");

            return self::FAILURE;
        }

        $failed = 0;
        $rows = [];

        foreach ((array) config('whatsapp.templates') as $name => $definition) {
            if (isset($existing[$name])) {
                $rows[] = [$name, $existing[$name], 'déjà présent'];

                continue;
            }

            try {
                $response = $client->createTemplate($name, $definition);
            } catch (Throwable $e) {
                $rows[] = [$name, 'ERREUR', $e->getMessage()];
                $failed++;

                continue;
            }

            if ($response->successful()) {
                $rows[] = [$name, (string) ($response->json('status') ?? 'PENDING'), 'créé'];
            } else {
                $rows[] = [$name, 'ERREUR', (string) ($response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'HTTP '.$response->status())];
                $failed++;
            }
        }

        $this->table(['Modèle', 'Statut', 'Résultat'], $rows);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
