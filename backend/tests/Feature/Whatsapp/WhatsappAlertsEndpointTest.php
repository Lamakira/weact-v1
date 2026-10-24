<?php

declare(strict_types=1);

namespace Tests\Feature\Whatsapp;

use App\Models\Face;
use Illuminate\Support\Facades\Http;

/**
 * Inscription / désinscription / invitation aux alertes WhatsApp et bloc `whatsapp_alerts` du payload utilisateur.
 */
class WhatsappAlertsEndpointTest extends WhatsappTestCase
{
    public function test_enabling_stores_the_normalized_number_and_returns_the_payload(): void
    {
        $user = $this->makeFace(optIn: false);

        $this->actingAs($user)
            ->putJson('/api/v1/me/whatsapp-alerts', ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.enabled_at', '2030-10-10T12:00:00+00:00')
            ->assertJsonPath('data.number', '+229 01 97 45 12 33')
            ->assertJsonPath('data.has_valid_number', true)
            ->assertJsonPath('data.invite_dismissed', false);

        $user->refresh();
        $this->assertSame('2290197451233', $user->whatsapp_opt_in_number);
        $this->assertNotNull($user->whatsapp_opt_in_at);
        Http::assertNothingSent();
    }

    public function test_producer_can_enable_with_a_legacy_format_number(): void
    {
        $user = $this->makeProducer(number: '+229 96 00 00 00', optIn: false);

        $this->actingAs($user)->putJson('/api/v1/me/whatsapp-alerts', ['enabled' => true])->assertOk();

        $this->assertSame('2290196000000', $user->refresh()->whatsapp_opt_in_number);
    }

    public function test_disabling_clears_the_opt_in(): void
    {
        $user = $this->makeFace();

        $this->actingAs($user)
            ->putJson('/api/v1/me/whatsapp-alerts', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.enabled_at', null)
            ->assertJsonPath('data.has_valid_number', true);

        $user->refresh();
        $this->assertNull($user->whatsapp_opt_in_at);
        $this->assertNull($user->whatsapp_opt_in_number);
    }

    public function test_enabling_without_a_valid_number_is_refused_with_422(): void
    {
        foreach ([null, '', '0197', 'pas de numéro'] as $number) {
            $user = $this->makeFace(number: $number, optIn: false);

            $this->actingAs($user)
                ->putJson('/api/v1/me/whatsapp-alerts', ['enabled' => true])
                ->assertStatus(422)
                ->assertExactJson(['error' => [
                    'code' => 'WHATSAPP_NUMBER_REQUIRED',
                    'message' => 'Ajoutez d\'abord votre numéro WhatsApp.',
                ]]);

            $this->assertNull($user->refresh()->whatsapp_opt_in_at);
        }
    }

    public function test_enabled_flag_is_required_and_boolean(): void
    {
        $user = $this->makeFace(optIn: false);

        $this->actingAs($user)->putJson('/api/v1/me/whatsapp-alerts', [])->assertStatus(422);
        $this->actingAs($user)->putJson('/api/v1/me/whatsapp-alerts', ['enabled' => 'peut-être'])->assertStatus(422);
    }

    public function test_routes_are_404_when_the_feature_is_unavailable(): void
    {
        $user = $this->makeFace(optIn: false);

        foreach ([
            ['enabled' => false],
            ['enabled' => true, 'whatsapp.phone_number_id' => null],
            ['enabled' => true, 'whatsapp.access_token' => ''],
        ] as $override) {
            config(['whatsapp.enabled' => $override['enabled']]);
            config(array_diff_key($override, ['enabled' => 1]));

            $this->actingAs($user)->putJson('/api/v1/me/whatsapp-alerts', ['enabled' => true])->assertNotFound();
            $this->actingAs($user)->postJson('/api/v1/me/whatsapp-alerts/dismiss-invite')->assertNotFound();

            $this->enableWhatsapp();
        }

        $this->assertNull($user->refresh()->whatsapp_opt_in_at);
        $this->assertNull($user->whatsapp_invite_dismissed_at);
    }

    public function test_dismiss_invite_returns_204_and_is_reflected_in_the_payload(): void
    {
        $user = $this->makeFace(optIn: false);

        $this->actingAs($user)->postJson('/api/v1/me/whatsapp-alerts/dismiss-invite')->assertNoContent();

        $this->assertNotNull($user->refresh()->whatsapp_invite_dismissed_at);
        $this->actingAs($user)->getJson('/api/v1/user')->assertJsonPath('data.whatsapp_alerts.invite_dismissed', true);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->putJson('/api/v1/me/whatsapp-alerts', ['enabled' => true])->assertUnauthorized();
        $this->postJson('/api/v1/me/whatsapp-alerts/dismiss-invite')->assertUnauthorized();
    }

    public function test_changing_the_profile_number_makes_the_opt_in_lapse(): void
    {
        $user = $this->makeFace();

        $this->actingAs($user)->getJson('/api/v1/user')
            ->assertJsonPath('data.whatsapp_alerts.enabled', true)
            ->assertJsonPath('data.whatsapp_alerts.enabled_at', '2030-10-10T12:00:00+00:00');

        Face::whereKey($user->userable_id)->update(['whatsapp_number' => '0191111111']);

        $this->actingAs($user->refresh())->getJson('/api/v1/user')
            ->assertJsonPath('data.whatsapp_alerts.enabled', false)
            ->assertJsonPath('data.whatsapp_alerts.enabled_at', null)
            ->assertJsonPath('data.whatsapp_alerts.number', '+229 01 91 11 11 11')
            ->assertJsonPath('data.whatsapp_alerts.has_valid_number', true);

        // Revenir au numéro consenti réactive de fait l'inscription mémorisée (même numéro normalisé).
        Face::whereKey($user->userable_id)->update(['whatsapp_number' => '+229 97 45 12 33']);

        $this->actingAs($user->refresh())->getJson('/api/v1/user')->assertJsonPath('data.whatsapp_alerts.enabled', true);
    }

    public function test_user_payload_reports_a_profile_without_number(): void
    {
        $user = $this->makeProducer(number: null, optIn: false);

        $this->actingAs($user)->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.whatsapp_alerts', [
                'available' => true,
                'enabled' => false,
                'enabled_at' => null,
                'number' => null,
                'has_valid_number' => false,
                'invite_dismissed' => false,
            ]);
    }

    public function test_user_payload_is_blank_when_the_feature_is_unavailable(): void
    {
        $user = $this->makeFace();
        config(['whatsapp.enabled' => false]);

        $this->actingAs($user)->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.whatsapp_alerts', [
                'available' => false,
                'enabled' => false,
                'enabled_at' => null,
                'number' => null,
                'has_valid_number' => false,
                'invite_dismissed' => false,
            ]);
    }

    public function test_login_payload_carries_whatsapp_alerts(): void
    {
        $user = $this->makeProducer();
        $user->update(['password' => 'Secret-pass-123']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Secret-pass-123'])
            ->assertOk()
            ->assertJsonPath('data.user.whatsapp_alerts.enabled', true)
            ->assertJsonPath('data.user.whatsapp_alerts.number', '+229 01 96 00 00 00');
    }

    public function test_booking_payloads_never_carry_the_whatsapp_alerts_block(): void
    {
        $face = $this->makeFace();
        $producer = $this->makeProducer();
        $booking = $this->makeBooking($face, $producer);

        foreach ([$producer, $face] as $viewer) {
            $response = $this->actingAs($viewer)->getJson("/api/v1/bookings/{$booking->uuid}")->assertOk();

            $this->assertStringNotContainsString('whatsapp_alerts', (string) $response->getContent());
        }
    }
}
