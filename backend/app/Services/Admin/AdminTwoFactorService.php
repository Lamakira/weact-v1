<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) two-factor authentication for admins.
 */
class AdminTwoFactorService
{
    public const RECOVERY_CODE_COUNT = 8;

    /** ±1 time step (30 s) of tolerance for clock drift. */
    private const WINDOW = 1;

    public function __construct(private readonly Google2FA $google2fa) {}

    /**
     * Start (or restart) enrolment: stores a fresh unconfirmed secret.
     *
     * @return array{secret: string, otpauth_uri: string, qr_svg: string}
     */
    public function startEnrolment(Admin $admin): array
    {
        $secret = $this->google2fa->generateSecretKey();

        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_timestep' => null,
        ])->save();

        $uri = $this->google2fa->getQRCodeUrl((string) config('app.name', 'WEACT').' Admin', $admin->email, $secret);

        return [
            'secret' => $secret,
            'otpauth_uri' => $uri,
            'qr_svg' => $this->qrSvg($uri),
        ];
    }

    public function hasPendingEnrolment(Admin $admin): bool
    {
        return $admin->two_factor_secret !== null && $admin->two_factor_confirmed_at === null;
    }

    /**
     * Confirm enrolment with a valid TOTP code.
     *
     * @return list<string>|null The recovery codes (shown once), null on invalid code
     */
    public function confirmEnrolment(Admin $admin, string $code): ?array
    {
        if (! $this->verifyTotp($admin, $code)) {
            return null;
        }

        $codes = $this->generateRecoveryCodes();

        $admin->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();

        return $codes;
    }

    /**
     * Verify a TOTP code. A time step already consumed is refused (anti-replay).
     */
    public function verifyTotp(Admin $admin, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($admin->two_factor_secret === null || ! ctype_digit($code) || strlen($code) !== 6) {
            return false;
        }

        $timestep = $this->google2fa->verifyKeyNewer(
            $admin->two_factor_secret,
            $code,
            // Never null: with a null "old timestamp" the library returns `true`
            // instead of the matched time step, which would defeat the anti-replay.
            $admin->two_factor_last_used_timestep ?? 0,
            self::WINDOW,
        );

        if ($timestep === false || $timestep === true) {
            return false;
        }

        // Atomic compare-and-set: two concurrent requests with the same code
        // cannot both pass.
        $updated = Admin::query()
            ->whereKey($admin->getKey())
            ->where(function ($query) use ($timestep): void {
                $query->whereNull('two_factor_last_used_timestep')
                    ->orWhere('two_factor_last_used_timestep', '<', (int) $timestep);
            })
            ->update(['two_factor_last_used_timestep' => (int) $timestep]);

        if ($updated === 0) {
            return false;
        }

        $admin->forceFill(['two_factor_last_used_timestep' => (int) $timestep])->syncOriginal();

        return true;
    }

    /**
     * Consume a one-time recovery code.
     */
    public function consumeRecoveryCode(Admin $admin, string $recoveryCode): bool
    {
        $recoveryCode = Str::lower(trim($recoveryCode));
        $codes = $admin->two_factor_recovery_codes ?? [];

        $matched = null;
        foreach ($codes as $index => $stored) {
            if (hash_equals((string) $stored, $recoveryCode)) {
                $matched = $index;
            }
        }

        if ($matched === null) {
            return false;
        }

        unset($codes[$matched]);
        $admin->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }

    /**
     * Accept a TOTP code OR a recovery code (whichever is provided).
     */
    public function verifyCodeOrRecovery(Admin $admin, ?string $code, ?string $recoveryCode): bool
    {
        if ($code !== null && $code !== '') {
            return $this->verifyTotp($admin, $code);
        }

        if ($recoveryCode !== null && $recoveryCode !== '') {
            return $this->consumeRecoveryCode($admin, $recoveryCode);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(Admin $admin): array
    {
        $codes = $this->generateRecoveryCodes();
        $admin->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $codes;
    }

    /**
     * Remove 2FA entirely (self-disable or superadmin reset).
     */
    public function disable(Admin $admin): void
    {
        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_timestep' => null,
        ])->save();
    }

    /**
     * @return list<string> Format xxxxx-xxxxx (lowercase alphanumeric)
     */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = Str::lower(Str::random(5).'-'.Str::random(5));
        }

        return $codes;
    }

    private function qrSvg(string $uri): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 2), new SvgImageBackEnd));

        return $writer->writeString($uri);
    }
}
