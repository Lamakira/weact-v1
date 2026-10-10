<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A push endpoint is a URL the SERVER will POST to: only the browsers' push
 * services are accepted (SSRF guard). https, no userinfo, default port, and a
 * host equal to / subdomain of the allowlist.
 */
class PushEndpointHost implements ValidationRule
{
    /**
     * @var list<string>
     */
    public const ALLOWED_HOSTS = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'push.services.mozilla.com',
        'notify.windows.com',
        'web.push.apple.com',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isAllowed($value)) {
            $fail('Le service de notifications de cet appareil n\'est pas pris en charge.');
        }
    }

    public static function isAllowed(string $endpoint): bool
    {
        $parts = parse_url($endpoint);

        if ($parts === false
            || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || ! isset($parts['host'])) {
            return false;
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        foreach (self::ALLOWED_HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
