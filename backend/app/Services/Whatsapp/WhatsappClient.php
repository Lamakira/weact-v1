<?php

declare(strict_types=1);

namespace App\Services\Whatsapp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP minimal de la WhatsApp Cloud API (Graph API de Meta). Aucune clé en dur : tout vient
 * de config/whatsapp.php. Sans état, testable avec Http::fake().
 */
class WhatsappClient
{
    private const GRAPH_HOST = 'https://graph.facebook.com';

    /**
     * Fonctionnalité disponible : activée ET identifiants présents.
     */
    public function isAvailable(): bool
    {
        return (bool) config('whatsapp.enabled')
            && filled(config('whatsapp.phone_number_id'))
            && filled(config('whatsapp.access_token'));
    }

    /**
     * Envoie un modèle approuvé : paramètres de corps {{1}}…{{n}} et, si fourni, suffixe du bouton URL dynamique.
     *
     * @param  list<string>  $bodyParams
     */
    public function sendTemplate(string $to, string $template, array $bodyParams, ?string $urlSuffix): Response
    {
        $components = [[
            'type' => 'body',
            'parameters' => array_map(fn (string $text): array => ['type' => 'text', 'text' => $text], $bodyParams),
        ]];

        if ($urlSuffix !== null) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [['type' => 'text', 'text' => $urlSuffix]],
            ];
        }

        return $this->http()->post('/'.config('whatsapp.phone_number_id').'/messages', [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => (string) config('whatsapp.language')],
                'components' => $components,
            ],
        ]);
    }

    /**
     * Modèles déjà présents chez Meta (nom => statut), toutes pages confondues.
     *
     * @return array<string, string>
     */
    public function existingTemplates(): array
    {
        $found = [];
        $after = null;

        do {
            $response = $this->http()->get('/'.config('whatsapp.business_account_id').'/message_templates', array_filter([
                'fields' => 'name,status,language',
                'limit' => 200,
                'after' => $after,
            ]))->throw();

            foreach ((array) $response->json('data', []) as $template) {
                if (is_array($template) && isset($template['name'])) {
                    $found[(string) $template['name']] = (string) ($template['status'] ?? 'UNKNOWN');
                }
            }

            $after = $response->json('paging.next') !== null ? $response->json('paging.cursors.after') : null;
        } while ($after !== null);

        return $found;
    }

    /**
     * Soumet un modèle UTILITY à l'approbation de Meta (corps avec exemples + bouton URL dynamique).
     *
     * @param  array{body: string, button: string, examples: list<string>, url_example: string}  $definition
     */
    public function createTemplate(string $name, array $definition): Response
    {
        $base = rtrim((string) config('whatsapp.public_url'), '/');

        return $this->http()->post('/'.config('whatsapp.business_account_id').'/message_templates', [
            'name' => $name,
            'language' => (string) config('whatsapp.language'),
            'category' => 'UTILITY',
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $definition['body'],
                    'example' => ['body_text' => [$definition['examples']]],
                ],
                [
                    'type' => 'BUTTONS',
                    'buttons' => [[
                        'type' => 'URL',
                        'text' => $definition['button'],
                        'url' => $base.'/{{1}}',
                        'example' => [$base.'/'.$definition['url_example']],
                    ]],
                ],
            ],
        ]);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(self::GRAPH_HOST.'/'.config('whatsapp.graph_version'))
            ->withToken((string) config('whatsapp.access_token'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('whatsapp.timeout', 10));
    }
}
