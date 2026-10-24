<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Enums\ErrorCodes;
use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook WhatsApp Cloud API (Meta) : vérification d'abonnement (GET), puis statuts de livraison
 * et messages entrants (POST). Pas d'auth : la signature HMAC de Meta fait foi.
 */
class WhatsappWebhookController extends Controller
{
    private const STOP_WORDS = ['STOP', 'ARRET', 'ARRÊT'];

    public function __construct(private readonly WhatsappService $whatsapp) {}

    /**
     * Vérification d'abonnement : renvoie `hub.challenge` en texte brut si le jeton correspond.
     * PHP transforme les points des clés de requête en « _ » (hub_mode), on accepte les deux formes.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));
        $expected = (string) config('whatsapp.webhook_verify_token');

        if ($mode === 'subscribe' && $expected !== '' && is_string($token) && hash_equals($expected, $token) && is_string($challenge)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($expected, $signature)) {
            Log::warning('WhatsApp webhook signature verification failed');

            return response()->json(ErrorCodes::InvalidSignature->envelope('Invalid signature'), 403);
        }

        // Meta réessaie tout ce qui n'est pas un 200 : un contenu inattendu ne doit jamais faire échouer la réponse.
        try {
            foreach ((array) $request->json('entry', []) as $entry) {
                foreach ((array) ($entry['changes'] ?? []) as $change) {
                    $value = $change['value'] ?? null;
                    if (! is_array($value)) {
                        continue;
                    }

                    foreach ((array) ($value['statuses'] ?? []) as $status) {
                        if (is_array($status)) {
                            $this->whatsapp->applyStatus($status);
                        }
                    }

                    foreach ((array) ($value['messages'] ?? []) as $message) {
                        if (is_array($message)) {
                            $this->handleIncoming($message);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsApp webhook processing failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleIncoming(array $message): void
    {
        $from = $message['from'] ?? null;
        $body = $message['text']['body'] ?? null;

        if (($message['type'] ?? null) !== 'text' || ! is_string($from) || ! is_string($body)) {
            return;
        }

        $normalized = mb_strtoupper(trim($body));
        if (in_array($normalized, self::STOP_WORDS, true)) {
            $this->whatsapp->unsubscribeNumber(preg_replace('/\D+/', '', $from) ?? '');
        }
    }
}
