<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FP-2.12 — public profile detail emits has_elite_badge driven by the FP-2.1
 * capabilities matrix. Only an Active Élite subscription produces a true value.
 */
class PublicFaceProfileEliteBadgeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a Face + linked User + (optionally) one FaceSubscription at a given paid tier + state.
     *
     * @param  null|'starter'|'pro'|'elite'  $tier
     * @param  string  $stateFactory  one of 'active' (default), 'expired', 'cancelled', 'pendingPayment', 'failed'
     */
    private function makeFace(?string $tier = null, string $stateFactory = 'active'): Face
    {
        $face = Face::factory()->create();
        User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
        ]);

        if ($tier !== null) {
            FaceSubscription::factory()->{$tier}()->{$stateFactory}()->create(['face_id' => $face->id]);
        }

        return $face;
    }

    public function test_public_profile_has_elite_badge_is_false_for_free_face(): void
    {
        $face = $this->makeFace();

        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', false);
    }

    public function test_public_profile_has_elite_badge_is_false_for_starter_face(): void
    {
        $face = $this->makeFace('starter');

        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', false);
    }

    public function test_public_profile_has_elite_badge_is_false_for_pro_face(): void
    {
        $face = $this->makeFace('pro');

        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', false);
    }

    public function test_public_profile_has_elite_badge_is_true_for_elite_face(): void
    {
        $face = $this->makeFace('elite');

        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', true);
    }

    public function test_public_profile_has_elite_badge_is_false_for_expired_elite_face(): void
    {
        $face = $this->makeFace('elite', 'expired');

        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', false);
    }

    // AC #11 — Cancelled Élite avec `expires_at` futur : la souscription est dans
    // la grace period (l'utilisateur a annulé mais la période payée court encore),
    // mais `scopeActive` filtre sur `status = Active` donc le badge doit tomber.
    public function test_public_profile_has_elite_badge_is_false_for_cancelled_elite_face(): void
    {
        $face = $this->makeFace('elite', 'cancelled');

        $this->getJson("/api/v1/public/faces/{$face->username}")
            ->assertOk()
            ->assertJsonPath('data.has_elite_badge', false);
    }
}
