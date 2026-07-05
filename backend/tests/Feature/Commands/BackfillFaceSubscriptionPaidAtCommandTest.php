<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\FaceSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Couvre la logique de backfill paid_at (App\Support\FaceSubscriptionPaidAtBackfill)
 * via la commande re-exécutable — la même logique que la migration exécute, mais
 * testable sur des lignes seedées (la migration tourne toujours sur table vide en CI).
 */
class BackfillFaceSubscriptionPaidAtCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfills_paid_rows_from_confirmed_at_and_counts_non_datable(): void
    {
        // Datable : confirmed_at avec offset non-UTC → converti en UTC
        $datable = FaceSubscription::factory()->create([
            'paid_amount' => 50000,
            'paid_at' => null,
            'metadata' => ['confirmed_at' => '2026-05-10T12:00:00+01:00'],
        ]);

        // Non-datable : confirmed_at illisible
        $garbage = FaceSubscription::factory()->create([
            'paid_amount' => 60000,
            'paid_at' => null,
            'metadata' => ['confirmed_at' => 'not-a-date'],
        ]);

        // Non-datable : pas de metadata du tout
        $noMeta = FaceSubscription::factory()->create([
            'paid_amount' => 70000,
            'paid_at' => null,
            'metadata' => null,
        ]);

        // Hors périmètre : jamais payée
        $unpaid = FaceSubscription::factory()->pendingPayment()->create();

        // Idempotence : déjà datée, ne doit PAS être réécrite
        $alreadyDated = FaceSubscription::factory()->create([
            'paid_amount' => 80000,
            'paid_at' => '2026-04-01 08:00:00',
            'metadata' => ['confirmed_at' => '2026-04-02T09:00:00Z'],
        ]);

        $this->artisan('face-subscriptions:backfill-paid-at')
            ->expectsOutputToContain('1 ligne(s) datée(s), 2 non-datable(s)')
            ->assertSuccessful();

        $this->assertSame(
            '2026-05-10T11:00:00+00:00',
            $datable->fresh()->paid_at?->toIso8601String(),
            'confirmed_at à offset +01:00 doit être converti en UTC'
        );
        $this->assertNull($garbage->fresh()->paid_at);
        $this->assertNull($noMeta->fresh()->paid_at);
        $this->assertNull($unpaid->fresh()->paid_at);
        $this->assertSame(
            '2026-04-01T08:00:00+00:00',
            $alreadyDated->fresh()->paid_at?->toIso8601String(),
            'une ligne déjà datée ne doit jamais être réécrite'
        );
    }

    public function test_rerun_is_idempotent(): void
    {
        $row = FaceSubscription::factory()->create([
            'paid_amount' => 50000,
            'paid_at' => null,
            'metadata' => ['confirmed_at' => '2026-05-10T12:00:00Z'],
        ]);

        $this->artisan('face-subscriptions:backfill-paid-at')->assertSuccessful();
        $firstValue = $row->fresh()->paid_at?->toIso8601String();

        // Second run : plus rien à dater, la valeur ne bouge pas
        $this->artisan('face-subscriptions:backfill-paid-at')
            ->expectsOutputToContain('0 ligne(s) datée(s), 0 non-datable(s)')
            ->assertSuccessful();

        $this->assertSame($firstValue, $row->fresh()->paid_at?->toIso8601String());
    }
}
