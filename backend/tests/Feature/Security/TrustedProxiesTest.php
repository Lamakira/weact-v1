<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test/client-info', fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'host' => $request->getHost(),
        ]));
    }

    protected function tearDown(): void
    {
        // L'état des proxys de confiance est statique côté Symfony : on le remet à zéro.
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_forwarded_for_is_ignored_when_no_proxy_is_trusted(): void
    {
        config(['app.trusted_proxies' => null]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
            ->getJson('/__test/client-info')
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.9');
    }

    public function test_forwarded_for_is_ignored_when_trusted_proxies_is_an_empty_string(): void
    {
        config(['app.trusted_proxies' => '']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
            ->getJson('/__test/client-info')
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.9');
    }

    public function test_forwarded_for_is_honoured_from_a_trusted_proxy(): void
    {
        config(['app.trusted_proxies' => '127.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
            ->getJson('/__test/client-info')
            ->assertOk()
            ->assertJsonPath('ip', '198.51.100.7');
    }

    public function test_forwarded_for_is_ignored_from_an_untrusted_peer(): void
    {
        config(['app.trusted_proxies' => '127.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
            ->getJson('/__test/client-info')
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.9');
    }

    public function test_forwarded_host_is_never_trusted_even_from_a_trusted_proxy(): void
    {
        config(['app.trusted_proxies' => '*']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Host' => 'evil.example'])
            ->getJson('/__test/client-info')
            ->assertOk();

        $this->assertNotSame('evil.example', $response->json('host'));
    }
}
