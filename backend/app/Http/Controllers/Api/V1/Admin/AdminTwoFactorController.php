<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminTwoFactorCodeRequest;
use App\Http\Requests\Admin\AdminTwoFactorConfirmRequest;
use App\Http\Requests\Admin\AdminTwoFactorEnableRequest;
use App\Models\Admin;
use App\Notifications\AdminTwoFactorChangedNotification;
use App\Services\Admin\AdminAuthThrottle;
use App\Services\Admin\AdminTwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Admin TOTP enrolment + management. These routes sit OUTSIDE the
 * `admin.2fa` enforcement group (an unenrolled admin must reach them).
 */
class AdminTwoFactorController extends Controller
{
    public function __construct(
        private readonly AdminTwoFactorService $twoFactor,
        private readonly AdminAuthThrottle $throttle,
    ) {}

    public function status(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        return response()->json([
            'data' => [
                'enabled' => $admin->hasTwoFactorEnabled(),
                'pending' => $this->twoFactor->hasPendingEnrolment($admin),
                'recovery_codes_remaining' => $admin->hasTwoFactorEnabled()
                    ? count($admin->two_factor_recovery_codes ?? [])
                    : 0,
            ],
            'message' => 'Statut de la double authentification récupéré',
        ]);
    }

    public function enable(AdminTwoFactorEnableRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        if ($admin->hasTwoFactorEnabled()) {
            return $this->error('La double authentification est déjà activée.', 'TWO_FACTOR_ALREADY_ENABLED', 409);
        }

        // A leaked token alone must not allow binding an attacker's authenticator.
        if ($this->throttle->isLocked($admin, $admin->email, (string) $request->ip())) {
            return $this->locked($admin, (string) $request->ip());
        }

        if (! Hash::check($request->validated('password'), $admin->password)) {
            $this->throttle->recordFailure('auth.admin.two_factor.failed', $admin, $admin->email, (string) $request->ip());

            return $this->error('Mot de passe incorrect', 'INVALID_PASSWORD', 422);
        }

        return response()->json([
            'data' => $this->twoFactor->startEnrolment($admin),
            'message' => 'Scannez le QR code avec votre application d\'authentification',
        ]);
    }

    public function confirm(AdminTwoFactorConfirmRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        if (! $this->twoFactor->hasPendingEnrolment($admin)) {
            return $this->error('Aucune activation en cours.', 'TWO_FACTOR_NOT_STARTED', 409);
        }

        $ip = (string) $request->ip();

        if ($this->throttle->isLocked($admin, $admin->email, $ip)) {
            return $this->locked($admin, $ip);
        }

        $codes = $this->twoFactor->confirmEnrolment($admin, $request->validated('code'));

        if ($codes === null) {
            $this->throttle->recordFailure('auth.admin.two_factor.failed', $admin, $admin->email, $ip);

            return $this->error('Code de vérification incorrect', 'TWO_FACTOR_INVALID_CODE', 422);
        }

        $this->throttle->clear($admin, $admin->email, $ip);

        // Every token issued before the enrolment (including the enrolment-limited
        // one used here) is revoked; a fresh token carrying the `2fa` ability is
        // returned. A token stolen before enrolment therefore gains nothing.
        $admin->tokens()->delete();
        $token = $admin->createToken('admin-token', [Admin::ABILITY_TWO_FACTOR])->plainTextToken;

        $admin->notify(new AdminTwoFactorChangedNotification(AdminTwoFactorChangedNotification::EVENT_ENABLED));

        return response()->json([
            'data' => ['recovery_codes' => $codes, 'token' => $token],
            'message' => 'Double authentification activée',
        ]);
    }

    public function disable(AdminTwoFactorCodeRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        if (($failure = $this->reauthenticate($request, $admin)) !== null) {
            return $failure;
        }

        $this->twoFactor->disable($admin);
        $this->twoFactor->revokeOtherTokens($admin);

        Log::warning('audit.admin.two_factor.disabled', [
            'admin_id' => $admin->id,
            'ip' => $request->ip(),
        ]);

        $admin->notify(new AdminTwoFactorChangedNotification(AdminTwoFactorChangedNotification::EVENT_DISABLED));

        return response()->json(['message' => 'Double authentification désactivée']);
    }

    public function regenerateRecoveryCodes(AdminTwoFactorCodeRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        if (! $admin->hasTwoFactorEnabled()) {
            return $this->error('La double authentification n\'est pas activée.', 'TWO_FACTOR_NOT_ENABLED', 409);
        }

        if (($failure = $this->reauthenticate($request, $admin)) !== null) {
            return $failure;
        }

        $codes = $this->twoFactor->regenerateRecoveryCodes($admin);
        $this->twoFactor->revokeOtherTokens($admin);

        $admin->notify(new AdminTwoFactorChangedNotification(AdminTwoFactorChangedNotification::EVENT_RECOVERY_CODES_REGENERATED));

        return response()->json([
            'data' => ['recovery_codes' => $codes],
            'message' => 'Nouveaux codes de secours générés',
        ]);
    }

    /**
     * SuperAdmin only: reset another admin's 2FA (lost device + lost recovery codes).
     * Revokes the target's tokens; the target must enrol again at next login.
     */
    public function reset(Request $request, Admin $admin): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();

        if ($actor->is($admin)) {
            return $this->error('Utilisez la désactivation depuis votre sécurité pour votre propre compte.', 'CANNOT_RESET_SELF', 422);
        }

        $this->twoFactor->disable($admin);
        $admin->tokens()->delete();

        Log::warning('audit.admin.two_factor.reset', [
            'actor_admin_id' => $actor->id,
            'target_admin_id' => $admin->id,
            'ip' => $request->ip(),
        ]);

        $admin->notify(new AdminTwoFactorChangedNotification(AdminTwoFactorChangedNotification::EVENT_RESET));

        return response()->json(['message' => 'Double authentification réinitialisée']);
    }

    /**
     * Current password + (TOTP|recovery) code. Password first, so a wrong
     * password never burns a valid one-time code.
     */
    private function reauthenticate(AdminTwoFactorCodeRequest $request, Admin $admin): ?JsonResponse
    {
        $ip = (string) $request->ip();

        if ($this->throttle->isLocked($admin, $admin->email, $ip)) {
            return $this->locked($admin, $ip);
        }

        if (! Hash::check($request->validated('password'), $admin->password)) {
            $this->throttle->recordFailure('auth.admin.two_factor.failed', $admin, $admin->email, $ip);

            return $this->error('Mot de passe incorrect', 'INVALID_PASSWORD', 422);
        }

        if (! $this->twoFactor->verifyCodeOrRecovery($admin, $request->validated('code'), $request->validated('recovery_code'))) {
            $this->throttle->recordFailure('auth.admin.two_factor.failed', $admin, $admin->email, $ip);

            return $this->error('Code de vérification incorrect', 'TWO_FACTOR_INVALID_CODE', 422);
        }

        $this->throttle->clear($admin, $admin->email, $ip);

        return null;
    }

    private function locked(Admin $admin, string $ip): JsonResponse
    {
        $retryAfter = $this->throttle->retryAfter($admin, $admin->email, $ip);

        return $this->error(
            'Trop de tentatives. Veuillez réessayer dans '.(int) ceil($retryAfter / 60).' minute(s).',
            'THROTTLED',
            429,
        )->header('Retry-After', (string) $retryAfter);
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json(['error' => ['message' => $message, 'code' => $code]], $status);
    }
}
