<?php

declare(strict_types=1);

namespace Tests\Feature\Whatsapp;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Face;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Socle des tests d'alertes WhatsApp : horloge figée, configuration Meta factice, API Meta simulée
 * (Http::fake) et fabriques de Face / Producteur / booking.
 */
abstract class WhatsappTestCase extends TestCase
{
    use RefreshDatabase;

    protected const GRAPH_MESSAGES_URL = 'https://graph.facebook.com/v23.0/1234567890/messages';

    protected function setUp(): void
    {
        parent::setUp();

        // Jeudi 10 octobre 2030, midi UTC.
        Carbon::setTestNow('2030-10-10 12:00:00');
        $this->enableWhatsapp();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function enableWhatsapp(): void
    {
        config([
            'whatsapp.enabled' => true,
            'whatsapp.graph_version' => 'v23.0',
            'whatsapp.phone_number_id' => '1234567890',
            'whatsapp.business_account_id' => '999888777',
            'whatsapp.access_token' => 'test-token',
            'whatsapp.app_secret' => 'test-app-secret',
            'whatsapp.webhook_verify_token' => 'verify-me',
            'whatsapp.public_url' => 'https://app.weact.test',
        ]);
    }

    /** Meta accepte tout et renvoie un wamid unique par message. */
    protected function fakeMetaAccepts(): void
    {
        Http::fake(['graph.facebook.com/*' => fn () => Http::response(['messages' => [['id' => 'wamid.'.Str::uuid()]]], 200)]);
    }

    /**
     * Remplace la simulation de l'API Meta (la première simulation enregistrée l'emporte sinon).
     */
    protected function replaceMetaFake(callable|array $responses): void
    {
        Http::swap(new HttpFactory);
        Http::fake($responses);
    }

    protected function makeFace(string $prenom = 'Awa', ?string $number = '0197451233', bool $optIn = true, string $nom = 'Kone'): User
    {
        $face = Face::factory()->create(['prenom' => $prenom, 'nom' => $nom, 'whatsapp_number' => $number]);
        $user = User::factory()->create(['userable_type' => Face::class, 'userable_id' => $face->id]);

        return $optIn ? $this->optIn($user) : $user;
    }

    protected function makeProducer(string $firstName = 'Studio', ?string $number = '0196000000', bool $optIn = true, string $lastName = 'Kora'): User
    {
        $producer = Producer::factory()->individual()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'whatsapp_number' => $number,
        ]);
        $user = User::factory()->create(['userable_type' => Producer::class, 'userable_id' => $producer->id]);

        return $optIn ? $this->optIn($user) : $user;
    }

    /** Inscrit l'utilisateur au numéro actuel de son profil (comme l'endpoint). */
    protected function optIn(User $user): User
    {
        $user->load('userable');
        $user->forceFill([
            'whatsapp_opt_in_at' => now(),
            'whatsapp_opt_in_number' => \App\Support\Whatsapp::toE164($user->userable->whatsapp_number),
        ])->save();

        return $user->refresh();
    }

    /** Booking cash en attente, créé « maintenant » : échéance de réponse = mardi 13 octobre 12:00 UTC (dimanche 13 h au Bénin). */
    protected function makeBooking(User $face, User $producer, string $dateDebut = '2030-10-15', BookingStatus $status = BookingStatus::Pending, string $type = 'Clip musical'): Booking
    {
        return Booking::factory()->create([
            'face_id' => $face->id,
            'producer_id' => $producer->id,
            'status' => $status,
            'type_contenu' => $type,
            'date_debut' => Carbon::parse($dateDebut, 'UTC'),
        ]);
    }

    /**
     * Requêtes d'envoi de message enregistrées par Http::fake.
     *
     * @return list<Request>
     */
    protected function sentMessages(): array
    {
        $sent = [];
        foreach (Http::recorded() as [$request]) {
            if ($request->url() === self::GRAPH_MESSAGES_URL) {
                $sent[] = $request;
            }
        }

        return $sent;
    }

    /**
     * Corps du modèle envoyé : nom, destinataire, paramètres du corps et suffixe du bouton.
     *
     * @return array{template: mixed, to: mixed, params: list<string>, suffix: mixed}
     */
    protected function describe(Request $request): array
    {
        $data = $request->data();

        return [
            'template' => data_get($data, 'template.name'),
            'to' => data_get($data, 'to'),
            'params' => array_values(array_map(fn ($p) => $p['text'], data_get($data, 'template.components.0.parameters', []))),
            'suffix' => data_get($data, 'template.components.1.parameters.0.text'),
        ];
    }

    /**
     * @return list<array{template: mixed, to: mixed, params: list<string>, suffix: mixed}>
     */
    protected function describeAll(): array
    {
        return array_map(fn (Request $r) => $this->describe($r), $this->sentMessages());
    }
}
