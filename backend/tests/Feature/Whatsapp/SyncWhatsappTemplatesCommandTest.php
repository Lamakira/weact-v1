<?php

declare(strict_types=1);

namespace Tests\Feature\Whatsapp;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * whatsapp:sync-templates : crée chez Meta seulement les modèles manquants (jamais de mise à jour).
 */
class SyncWhatsappTemplatesCommandTest extends WhatsappTestCase
{
    private const TEMPLATES_URL = 'https://graph.facebook.com/v23.0/999888777/message_templates';

    private const EXPECTED_NAMES = [
        'weact_booking_request',
        'weact_booking_reminder',
        'weact_payment_secured',
        'weact_mission_place_paid',
        'weact_booking_accepted',
        'weact_booking_declined',
        'weact_new_candidatures',
        'weact_unread_message',
    ];

    /**
     * @param  list<string>  $existing  noms déjà chez Meta (statut APPROVED)
     */
    private function fakeMeta(array $existing, int $createStatus = 200): void
    {
        $this->replaceMetaFake(function (Request $request) use ($existing, $createStatus) {
            if ($request->method() === 'GET') {
                return Http::response([
                    'data' => array_map(fn (string $name) => ['name' => $name, 'status' => 'APPROVED', 'language' => 'fr'], $existing),
                    'paging' => ['cursors' => ['before' => 'a', 'after' => 'b']],
                ]);
            }

            return $createStatus === 200
                ? Http::response(['id' => '1', 'status' => 'PENDING', 'category' => 'UTILITY'])
                : Http::response(['error' => ['message' => 'Invalid parameter', 'error_user_msg' => 'Corps invalide']], $createStatus);
        });
    }

    /**
     * @return list<Request>
     */
    private function creations(): array
    {
        $created = [];
        foreach (Http::recorded() as [$request]) {
            if ($request->method() === 'POST') {
                $created[] = $request;
            }
        }

        return $created;
    }

    public function test_config_declares_the_eight_templates_with_valid_placeholders(): void
    {
        $templates = config('whatsapp.templates');

        $this->assertSame(self::EXPECTED_NAMES, array_keys($templates));
        foreach ($templates as $name => $definition) {
            preg_match_all('/\{\{(\d+)\}\}/', $definition['body'], $matches);
            $this->assertSame(range(1, count($definition['examples'])), array_map('intval', $matches[1]), $name);
            // Meta refuse un corps qui commence ou finit par une variable.
            $this->assertDoesNotMatchRegularExpression('/^\{\{|\}\}$/', $definition['body'], $name);
            $this->assertStringContainsString('Bonjour {{1}}', $definition['body'], $name);
        }
    }

    public function test_it_creates_every_template_when_none_exists(): void
    {
        $this->fakeMeta([]);

        $this->artisan('whatsapp:sync-templates')->assertSuccessful();

        $names = array_map(fn (Request $r) => $r['name'], $this->creations());
        $this->assertSame(self::EXPECTED_NAMES, $names);
    }

    public function test_it_creates_only_the_missing_templates(): void
    {
        $this->fakeMeta(['weact_booking_request', 'weact_unread_message', 'modele_etranger']);

        $this->artisan('whatsapp:sync-templates')
            ->expectsOutputToContain('déjà présent')
            ->assertSuccessful();

        $names = array_map(fn (Request $r) => $r['name'], $this->creations());
        $this->assertSame([
            'weact_booking_reminder',
            'weact_payment_secured',
            'weact_mission_place_paid',
            'weact_booking_accepted',
            'weact_booking_declined',
            'weact_new_candidatures',
        ], $names);
    }

    public function test_it_creates_nothing_when_everything_exists(): void
    {
        $this->fakeMeta(self::EXPECTED_NAMES);

        $this->artisan('whatsapp:sync-templates')->assertSuccessful();

        $this->assertSame([], $this->creations());
    }

    public function test_created_template_payload_is_a_utility_template_with_examples_and_a_dynamic_url_button(): void
    {
        $this->fakeMeta(array_diff(self::EXPECTED_NAMES, ['weact_booking_request']));

        $this->artisan('whatsapp:sync-templates')->assertSuccessful();

        [$request] = $this->creations();
        $this->assertSame(self::TEMPLATES_URL, $request->url());
        $this->assertSame('Bearer test-token', $request->header('Authorization')[0]);
        $this->assertSame('weact_booking_request', $request['name']);
        $this->assertSame('fr', $request['language']);
        $this->assertSame('UTILITY', $request['category']);

        [$body, $buttons] = $request['components'];
        $this->assertSame('BODY', $body['type']);
        $this->assertSame('Bonjour {{1}}, {{2}} vous propose un booking le {{3}}. Vous avez jusqu\'au {{4}} pour répondre sur WeAct.', $body['text']);
        $this->assertSame([['Awa', 'Studio Kora', 'lundi 27 octobre', 'samedi 25 octobre à 16 h']], $body['example']['body_text']);
        $this->assertSame('BUTTONS', $buttons['type']);
        $this->assertSame('URL', $buttons['buttons'][0]['type']);
        $this->assertSame('Voir la demande', $buttons['buttons'][0]['text']);
        $this->assertSame('https://app.weact.test/{{1}}', $buttons['buttons'][0]['url']);
        $this->assertSame(['https://app.weact.test/face/bookings/00000000-0000-0000-0000-000000000000'], $buttons['buttons'][0]['example']);
    }

    public function test_a_refused_creation_fails_the_command_but_continues_with_the_others(): void
    {
        $this->fakeMeta([], 400);

        $this->artisan('whatsapp:sync-templates')
            ->expectsOutputToContain('Corps invalide')
            ->assertFailed();

        $this->assertCount(8, $this->creations());
    }

    public function test_it_requires_the_account_id_and_the_token(): void
    {
        Http::fake();
        config(['whatsapp.business_account_id' => null]);

        $this->artisan('whatsapp:sync-templates')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_works_before_the_feature_is_enabled(): void
    {
        config(['whatsapp.enabled' => false]);
        $this->fakeMeta([]);

        $this->artisan('whatsapp:sync-templates')->assertSuccessful();

        $this->assertCount(8, $this->creations());
    }
}
