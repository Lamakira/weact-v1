<?php

declare(strict_types=1);

namespace Tests\Feature\ErrorFormat;

use App\Models\Face;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

/**
 * FIX-22.2 — Prove It pattern for the standardized API error envelope.
 *
 * All 7 tests (14.A–14.G) MUST fail against the current code. They should
 * only pass once:
 *  - the 7 controllers (AC #1–#6, #12) emit {error:{message,code}},
 *  - the global handler in bootstrap/app.php::withExceptions() normalizes
 *    the 7 exception types to the same envelope (AC #8, #9, #11),
 *  - the 19 FormRequest::failedValidation() overrides are removed (AC #10),
 *  - and VALIDATION_ERROR (UPPER) replaces the legacy lower-snake code (AC #7).
 */
class StandardizedErrorFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $faceUser;

    protected function setUp(): void
    {
        parent::setUp();

        $face = Face::factory()->create();
        $this->faceUser = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);
        // balance not in $fillable — increment pattern from WalletWithdrawalTest
        $this->faceUser->increment('balance', 50000);

        // Ephemeral routes used by 14.E (HttpException 403) and 14.G
        // (unmapped RuntimeException → 500 fallback). Pattern mirrors
        // tests/Feature/Localization/AppLocaleTest.php:setUp — routes land
        // under the `api` middleware group so they flow through the same
        // exception pipeline as real API endpoints.
        Route::middleware('api')->get('/__forbidden-probe', function (): void {
            abort(403, 'Test forbidden access');
        });

        Route::middleware('api')->get('/__boom-probe', function (): void {
            throw new \RuntimeException('boom');
        });
    }

    private function withApiToken(User $user): static
    {
        return $this->withToken($user->createToken('test-token')->plainTextToken);
    }

    /**
     * 14.A — WithdrawalLockException (409, WITHDRAWAL_LOCK).
     *
     * DB/Cache-driven: acquire the fedapay withdrawal Cache lock in the test
     * body so WithdrawalService::initiateFedapayWithdrawal cannot take it
     * and throws WithdrawalLockException. No Mockery needed.
     * Covers the WalletController 409 branch (AC #1).
     */
    public function test_wallet_withdraw_lock_conflict_returns_standardized_format(): void
    {
        config(['app.withdrawal_mode' => 'fedapay']);

        // Hold the fedapay lock BEFORE the controller runs.
        $lock = Cache::lock("withdrawal_fedapay_{$this->faceUser->id}", 30);
        $this->assertTrue($lock->get(), 'Failed to acquire prime lock in test setup');

        try {
            $this->withApiToken($this->faceUser)
                ->postJson('/api/v1/wallet/withdraw', [
                    'amount' => 20000,
                    'payment_mode' => 'mtn',
                    'phone_number' => '0197000000',
                    'phone_country' => 'bj',
                ])
                ->assertStatus(409)
                ->assertJsonStructure(['error' => ['message', 'code']])
                ->assertJsonPath('error.code', 'WITHDRAWAL_LOCK')
                ->assertJsonPath('error.message', 'Un retrait est déjà en cours pour ce compte. Veuillez patienter.');
        } finally {
            $lock->release();
        }
    }

    /**
     * 14.B — Producer/ProfileController 403 (FORBIDDEN).
     *
     * Face logs in with a valid Sanctum token → auth:sanctum passes →
     * getAuthenticatedProducer() returns null → 403 {message:'Utilisateur
     * non autorisé'}. After AC #2 the envelope becomes {error:{message,code}}.
     */
    public function test_producer_profile_unauthorized_returns_standardized_format(): void
    {
        $this->withApiToken($this->faceUser)
            ->getJson('/api/v1/producer/profile')
            ->assertStatus(403)
            ->assertJsonStructure(['error' => ['message', 'code']])
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertJsonPath('error.message', 'Utilisateur non autorisé');
    }

    /**
     * 14.C — Global handler normalizes NotFoundHttpException (404).
     *
     * Currently Laravel returns {message:''} by default — this test fails
     * on missing error.code. After AC #8 the envelope is {error:{message:
     * 'Ressource introuvable.', code:'NOT_FOUND'}}.
     */
    public function test_global_handler_normalizes_not_found_http_exception(): void
    {
        $this->getJson('/api/v1/__inexistent-route__')
            ->assertStatus(404)
            ->assertJsonStructure(['error' => ['message', 'code']])
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /**
     * 14.D — Global handler normalizes AuthenticationException (401).
     *
     * GET /api/v1/wallet without a Sanctum token → 401. Currently Laravel
     * returns {message:'Unauthenticated.'} (vendor EN). After AC #8 the
     * envelope becomes {error:{message:'Non authentifié.', code:
     * 'UNAUTHENTICATED'}}.
     */
    public function test_global_handler_normalizes_authentication_exception(): void
    {
        $this->getJson('/api/v1/wallet')
            ->assertStatus(401)
            ->assertJsonStructure(['error' => ['message', 'code']])
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * 14.E — Global handler normalizes HttpException from abort(403, …).
     *
     * The ephemeral route /__forbidden-probe calls abort(403, …). The
     * HttpException handler (AC #8) maps status 403 → code FORBIDDEN.
     */
    public function test_global_handler_normalizes_authorization_exception(): void
    {
        $this->getJson('/__forbidden-probe')
            ->assertStatus(403)
            ->assertJsonStructure(['error' => ['message', 'code']])
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * 14.F — ValidationException emits both shapes (AC #9 double-format).
     *
     * New envelope under `error.*` AND legacy top-level `errors` preserved
     * for the 176 assertJsonValidationErrors non-regression. The code
     * upgrades from 'validation_error' (lower snake) to 'VALIDATION_ERROR'
     * (UPPER) per AC #7/#10.
     */
    public function test_global_handler_validation_exception_emits_both_shapes(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.message', 'Les données fournies ne sont pas valides')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => ['email'],
                ],
            ])
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * 14.G — Unmapped Throwable wraps to 500 INTERNAL_ERROR + logs it.
     *
     * APP_DEBUG must be false for the handler to mask the trace. The
     * handler is required to log under the 'api.unhandled_exception' key
     * so operators retain observability of 500s (AC #11).
     */
    public function test_global_handler_wraps_unmapped_throwable_and_logs_it(): void
    {
        config(['app.debug' => false]);
        Log::spy();

        $this->getJson('/__boom-probe')
            ->assertStatus(500)
            ->assertJsonStructure(['error' => ['message', 'code']])
            ->assertJsonPath('error.code', 'INTERNAL_ERROR');

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $key, array $ctx = []): bool => $key === 'api.unhandled_exception')
            ->once();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
