<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Exemption TEMPORAIRE de limite de requêtes pour des IP de test de charge
 * (config app.throttle_bypass_ips, vide par défaut).
 */
class ThrottleBypassTest extends TestCase
{
    private const IP = '203.0.113.7';

    private const OTHER_IP = '198.51.100.9';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private const ATTEMPTS = 20;

    /**
     * Nombre de requêtes non limitées (hors 429) sur ATTEMPTS essais depuis $ip.
     * Formulaire de contact : `throttle:5,1` (+ `throttle:60,1` du groupe, même clé de compteur).
     */
    private function unthrottledContactHits(string $ip): int
    {
        $ok = 0;
        for ($i = 0; $i < self::ATTEMPTS; $i++) {
            $status = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/v1/public/contact', [])
                ->getStatusCode();
            $status === 429 || $ok++;
        }

        return $ok;
    }

    /** Idem pour `throttle:forgot-password` (limiteur nommé, 5/min par IP). */
    private function unthrottledForgotPasswordHits(string $ip): int
    {
        $ok = 0;
        for ($i = 0; $i < self::ATTEMPTS; $i++) {
            $status = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/v1/auth/forgot-password', [])
                ->getStatusCode();
            $status === 429 || $ok++;
        }

        return $ok;
    }

    public function test_empty_list_still_throttles_numeric_limit(): void
    {
        $this->assertSame([], config('app.throttle_bypass_ips'));
        $this->assertLessThan(self::ATTEMPTS, $this->unthrottledContactHits(self::IP));
    }

    public function test_listed_ip_is_never_throttled_on_numeric_limit(): void
    {
        config(['app.throttle_bypass_ips' => [self::IP]]);

        $this->assertSame(self::ATTEMPTS, $this->unthrottledContactHits(self::IP));
    }

    public function test_other_ip_is_still_throttled_when_list_contains_another_ip(): void
    {
        config(['app.throttle_bypass_ips' => [self::IP]]);

        $this->assertLessThan(self::ATTEMPTS, $this->unthrottledContactHits(self::OTHER_IP));
    }

    public function test_listed_ip_is_never_throttled_on_named_limiter(): void
    {
        $this->assertLessThan(self::ATTEMPTS, $this->unthrottledForgotPasswordHits(self::OTHER_IP));

        config(['app.throttle_bypass_ips' => [self::IP]]);

        $this->assertSame(self::ATTEMPTS, $this->unthrottledForgotPasswordHits(self::IP));
    }
}
