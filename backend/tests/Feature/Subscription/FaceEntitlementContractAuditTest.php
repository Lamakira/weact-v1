<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use Tests\TestCase;

/**
 * Capabilities-only contract audit (FP-2.11).
 *
 * Per the FP-2 implementation approach note ("Capabilities matrix is the
 * contract — every entitlement decision in the codebase MUST consume
 * `FaceEntitlementService::capabilities()`. No controller, resource, or
 * service should branch on `plan` strings directly."), this test class
 * grep-scans `backend/app/` for any call-site that reaches for the 5
 * legacy `FaceEntitlementService` transitional FP-1 shims
 * (`isPremium`, `albumUploadLimit`, `publicAlbumPhotoLimit`,
 * `canUploadActingVideo`, `isFeaturedBySubscription`).
 *
 * Only the **direct camelCase method-call** pattern is checked (e.g.
 * `->isPremium\b`). The snake_case property-access form (`->is_premium\b`)
 * was prototyped in Round 2 (B3) but dropped in Round 3 (R3-H4) because
 * it false-positived on any unrelated model with an `is_premium` column
 * (e.g. a future `User::is_premium`, `Producer::is_premium`, etc.) —
 * the audit was name-blind, not namespace-aware. The historical shims
 * are camelCase methods; the hypothetical accessor reintroduction risk
 * (`getIsPremiumAttribute` consumed as `$face->is_premium`) is left to
 * code review rather than this regex sentinel.
 *
 * A separate sentinel test asserts the grep harness itself is not a no-op
 * — without it, a misconfigured `base_path()` would let every audit test
 * pass green on an empty scan (Round 2 code-review B2).
 *
 * The class deliberately does NOT use `RefreshDatabase`, `Storage::fake`,
 * or any fixture — it is a pure source-tree scan.
 */
class FaceEntitlementContractAuditTest extends TestCase
{
    /** @var array<string, list<string>> method-key => grep regex patterns */
    private const LEGACY_METHOD_PATTERNS = [
        'isPremium' => ['->isPremium\b'],
        'albumUploadLimit' => ['->albumUploadLimit\b'],
        'publicAlbumPhotoLimit' => ['->publicAlbumPhotoLimit\b'],
        'canUploadActingVideo' => ['->canUploadActingVideo\b'],
        'isFeaturedBySubscription' => ['->isFeaturedBySubscription\b'],
    ];

    private function servicePath(): string
    {
        $path = realpath(app_path('Services/FaceEntitlementService.php'));
        $this->assertNotFalse(
            $path,
            'FaceEntitlementService.php must exist at app/Services/FaceEntitlementService.php — the audit cannot self-exclude without it.'
        );

        return $path;
    }

    /**
     * Grep `backend/app/` for $pattern (a regex; word boundaries are honored
     * because we use `grep -E`) and return the offending file paths. By
     * default, paths whose realpath equals FaceEntitlementService.php (the
     * legitimate owner of the shim methods) are excluded — use
     * $excludeService=false for the sentinel test.
     *
     * Uses `exec()` with `escapeshellarg` for safe pattern + path injection.
     * `grep -Erln`: -E extended regex (for `\b`), -r recursive, -l file names
     * only, -n line numbers (ignored, kept for future debugging).
     * `--include='*.php'` limits to PHP files. `-- $pattern` ends option
     * parsing so a pattern starting with `-` cannot collide with grep flags.
     * `2>&1` (Round 2 code-review B4) captures stderr inline so silently-
     * skipped subtrees (permission denied, symlink loops) surface as a test
     * failure instead of leaking offenders. Exit code 0 means matches found,
     * 1 means no matches found, anything else is a real error.
     *
     * Round 2 code-review B1: self-exclusion compares realpath equality
     * against FaceEntitlementService.php exactly, NOT substring containment,
     * so sibling files like `LegacyFaceEntitlementService.php` cannot bypass
     * the audit.
     *
     * @return list<string>
     */
    private function grepLegacyCallSites(string $pattern, bool $excludeService = true): array
    {
        $appPath = base_path('app');

        $cmd = 'grep -Erln --include='.escapeshellarg('*.php')
            .' -- '.escapeshellarg($pattern)
            .' '.escapeshellarg($appPath)
            .' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $this->assertContains(
            $exitCode,
            [0, 1],
            "grep failed with exit code {$exitCode} for pattern '{$pattern}' against {$appPath}. Output: ".implode(PHP_EOL, $output)
        );

        // Stderr lines (e.g. "grep: /path: Permission denied") share $output with file matches
        // because of the `2>&1` redirect. Filter them apart so silently-skipped subtrees
        // surface as a loud test failure instead of leaking offenders.
        $stderrLines = array_values(array_filter($output, static fn (string $line): bool => str_starts_with($line, 'grep:')));
        $this->assertSame(
            [],
            $stderrLines,
            'grep emitted warnings/errors that may indicate silently-skipped subtrees: '.implode(PHP_EOL, $stderrLines)
        );

        $matches = array_values(array_filter($output, static fn (string $line): bool => ! str_starts_with($line, 'grep:')));

        if ($excludeService) {
            $servicePath = $this->servicePath();
            $matches = array_values(array_filter(
                $matches,
                static fn (string $path): bool => realpath($path) !== $servicePath
            ));
        }

        return $matches;
    }

    public function test_no_production_call_site_uses_legacy_is_premium(): void
    {
        $this->assertNoOffendersAcrossPatterns('isPremium', self::LEGACY_METHOD_PATTERNS['isPremium']);
    }

    public function test_no_production_call_site_uses_legacy_album_or_video_upload_limit_methods(): void
    {
        foreach (['albumUploadLimit', 'publicAlbumPhotoLimit', 'canUploadActingVideo'] as $methodKey) {
            $this->assertNoOffendersAcrossPatterns($methodKey, self::LEGACY_METHOD_PATTERNS[$methodKey]);
        }
    }

    public function test_no_production_call_site_uses_legacy_is_featured_by_subscription(): void
    {
        $this->assertNoOffendersAcrossPatterns('isFeaturedBySubscription', self::LEGACY_METHOD_PATTERNS['isFeaturedBySubscription']);
    }

    /**
     * Round 2 code-review B2 — Sentinel.
     *
     * Without this test, a misconfigured `base_path()` (Docker volume race,
     * `composer test` invoked from the wrong cwd, an empty `app/` dir) would
     * make grep return exit code 1 across every pattern and every contract-
     * audit test would pass green on a no-op scan.
     *
     * Use a pattern guaranteed to exist somewhere inside `backend/app/` (the
     * `FaceEntitlementService` class name itself appears in the service file
     * AND in any consumer that types `app(FaceEntitlementService::class)`).
     */
    public function test_grep_harness_finds_known_token_inside_app_dir(): void
    {
        $matches = $this->grepLegacyCallSites('FaceEntitlementService', excludeService: false);

        $this->assertNotEmpty(
            $matches,
            'Sentinel failed: grep scanning backend/app/ returned ZERO matches for "FaceEntitlementService". '
            .'The contract-audit harness is broken — base_path() likely resolves to an empty/missing dir, '
            .'and the other tests would silently pass green.'
        );
    }

    /**
     * @param  list<string>  $patterns
     */
    private function assertNoOffendersAcrossPatterns(string $methodKey, array $patterns): void
    {
        foreach ($patterns as $pattern) {
            $offenders = $this->grepLegacyCallSites($pattern);

            $this->assertSame(
                [],
                $offenders,
                "Found legacy {$methodKey} call site (pattern: {$pattern}) outside FaceEntitlementService. "
                .'Migrate the consumer(s) to FaceEntitlementService::capabilities(): '
                .implode(', ', $offenders)
            );
        }
    }
}
