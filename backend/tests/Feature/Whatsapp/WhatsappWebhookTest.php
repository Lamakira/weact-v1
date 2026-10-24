<?php

declare(strict_types=1);

namespace Tests\Feature\Whatsapp;

use App\Models\User;
use App\Models\WhatsappMessage;

/**
 * Webhook Meta : vérification d'abonnement (GET), signature X-Hub-Signature-256, statuts de livraison
 * et désinscription par STOP / ARRÊT.
 */
class WhatsappWebhookTest extends WhatsappTestCase
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function webhook(array $payload, ?string $signature = null): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $signature ??= 'sha256='.hash_hmac('sha256', (string) $body, 'test-app-secret');

        return $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ], (string) $body);
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function statusPayload(array $status): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => ['statuses' => [$status]]]]]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function incomingText(string $from, string $text): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => [
            'messages' => [['from' => $from, 'id' => 'wamid.IN', 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]];
    }

    private function logRow(string $wamid, string $status = 'sent'): WhatsappMessage
    {
        return WhatsappMessage::create([
            'template' => 'weact_booking_request',
            'to' => '2290197451233',
            'wamid' => $wamid,
            'status' => $status,
        ]);
    }

    public function test_verification_echoes_the_challenge_in_plain_text(): void
    {
        $response = $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=1158201444');

        $response->assertOk();
        $this->assertSame('1158201444', $response->getContent());
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
    }

    public function test_verification_refuses_a_wrong_token_or_mode(): void
    {
        $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=nope&hub.challenge=42')->assertForbidden();
        $this->get('/api/v1/webhooks/whatsapp?hub.mode=unsubscribe&hub.verify_token=verify-me&hub.challenge=42')->assertForbidden();
        $this->get('/api/v1/webhooks/whatsapp')->assertForbidden();
    }

    public function test_verification_refuses_everything_when_no_token_is_configured(): void
    {
        config(['whatsapp.webhook_verify_token' => '']);

        $this->get('/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=&hub.challenge=42')->assertForbidden();
    }

    public function test_invalid_or_missing_signature_is_refused_with_403_and_changes_nothing(): void
    {
        $this->logRow('wamid.A');
        $payload = $this->statusPayload(['id' => 'wamid.A', 'status' => 'read']);

        $this->webhook($payload, 'sha256='.hash_hmac('sha256', 'autre corps', 'test-app-secret'))->assertForbidden();
        $this->webhook($payload, 'sha256='.hash_hmac('sha256', (string) json_encode($payload), 'mauvais-secret'))->assertForbidden();
        $this->webhook($payload, '')->assertForbidden();

        $this->assertSame('sent', WhatsappMessage::where('wamid', 'wamid.A')->value('status'));
    }

    public function test_signature_is_refused_when_no_app_secret_is_configured(): void
    {
        config(['whatsapp.app_secret' => '']);
        $payload = $this->statusPayload(['id' => 'wamid.A', 'status' => 'read']);

        $this->webhook($payload, 'sha256='.hash_hmac('sha256', (string) json_encode($payload), ''))->assertForbidden();
    }

    public function test_statuses_update_the_journal_by_wamid_and_never_go_backwards(): void
    {
        $this->logRow('wamid.A');

        $this->webhook($this->statusPayload(['id' => 'wamid.A', 'status' => 'delivered', 'timestamp' => '1900000000']))->assertOk();
        $this->assertSame('delivered', WhatsappMessage::where('wamid', 'wamid.A')->value('status'));

        $this->webhook($this->statusPayload(['id' => 'wamid.A', 'status' => 'read']))->assertOk();
        $this->assertSame('read', WhatsappMessage::where('wamid', 'wamid.A')->value('status'));

        // Un « sent » ou « delivered » en retard ne fait pas redescendre le statut.
        $this->webhook($this->statusPayload(['id' => 'wamid.A', 'status' => 'sent']))->assertOk();
        $this->webhook($this->statusPayload(['id' => 'wamid.A', 'status' => 'delivered']))->assertOk();
        $this->assertSame('read', WhatsappMessage::where('wamid', 'wamid.A')->value('status'));
    }

    public function test_failed_status_records_the_error_code_and_message(): void
    {
        $this->logRow('wamid.B');

        $this->webhook($this->statusPayload([
            'id' => 'wamid.B',
            'status' => 'failed',
            'errors' => [['code' => 131026, 'title' => 'Message Undeliverable', 'message' => 'Message undeliverable', 'error_data' => ['details' => 'Numéro sans WhatsApp']]],
        ]))->assertOk();

        $log = WhatsappMessage::where('wamid', 'wamid.B')->first();
        $this->assertSame('failed', $log->status);
        $this->assertSame('131026', $log->error_code);
        $this->assertSame('Numéro sans WhatsApp', $log->error_message);
        $this->assertNotNull($log->status_updated_at);
    }

    public function test_unknown_wamid_and_garbage_payloads_still_return_200(): void
    {
        $this->webhook($this->statusPayload(['id' => 'wamid.INCONNU', 'status' => 'read']))->assertOk();
        $this->webhook(['entry' => 'nimportequoi'])->assertOk();
        $this->webhook([])->assertOk();
        $this->webhook($this->statusPayload(['id' => 'wamid.A', 'status' => 'statut-bizarre']))->assertOk();
    }

    public function test_stop_unsubscribes_every_account_with_that_number(): void
    {
        $face = $this->makeFace('Awa', '0197451233');
        $sameNumber = $this->makeProducer('Studio', '+229 97 45 12 33');
        $other = $this->makeFace('Binta', '0198000000');

        $this->webhook($this->incomingText('2290197451233', 'STOP'))->assertOk();

        foreach ([$face, $sameNumber] as $user) {
            $this->assertNull($user->refresh()->whatsapp_opt_in_at);
            $this->assertNull($user->whatsapp_opt_in_number);
        }
        $this->assertNotNull($other->refresh()->whatsapp_opt_in_at);
    }

    public function test_stop_variants_are_case_and_space_insensitive_and_accept_arret(): void
    {
        foreach (['stop', '  Stop  ', 'ARRET', 'arrêt', 'Arrêt ', 'ARRÊT'] as $text) {
            $user = $this->makeFace('Awa', '0197451233');

            $this->webhook($this->incomingText('2290197451233', $text))->assertOk();

            $this->assertNull($user->refresh()->whatsapp_opt_in_at, $text);
            User::whereKey($user->id)->delete();
        }
    }

    public function test_stop_from_a_legacy_eight_digit_wa_id_matches_the_normalized_number(): void
    {
        $user = $this->makeFace('Awa', '0197451233');

        // WhatsApp peut identifier l'expéditeur par l'ancien format 229 + 8 chiffres.
        $this->webhook($this->incomingText('22997451233', 'STOP'))->assertOk();

        $this->assertNull($user->refresh()->whatsapp_opt_in_at);
    }

    public function test_other_incoming_texts_do_not_unsubscribe(): void
    {
        $user = $this->makeFace('Awa', '0197451233');

        foreach (['Bonjour', 'STOP svp', 'arrête', ''] as $text) {
            $this->webhook($this->incomingText('2290197451233', $text))->assertOk();
        }

        $this->assertNotNull($user->refresh()->whatsapp_opt_in_at);
    }
}
