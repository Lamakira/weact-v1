<?php

declare(strict_types=1);

namespace Tests\Feature\Face;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Models\Face;
use App\Models\FaceSubscription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FaceSubscriptionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_face_subscriptions_table_has_all_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('face_subscriptions'));

        $expectedColumns = [
            'id',
            'uuid',
            'face_id',
            'plan',
            'status',
            'starts_at',
            'expires_at',
            'cancelled_at',
            'paid_amount',
            'currency',
            'provider',
            'provider_reference',
            'metadata',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('face_subscriptions', $column),
                "Column '{$column}' should exist in face_subscriptions table"
            );
        }
    }

    public function test_face_subscription_currency_defaults_to_xof(): void
    {
        $face = Face::factory()->create();

        $id = \DB::table('face_subscriptions')->insertGetId([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'face_id' => $face->id,
            'plan' => FaceSubscriptionPlan::AnnualPremium->value,
            'status' => FaceSubscriptionStatus::PendingPayment->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('face_subscriptions', [
            'id' => $id,
            'currency' => 'XOF',
        ]);
    }

    public function test_face_subscription_belongs_to_face(): void
    {
        $face = Face::factory()->create();
        $subscription = FaceSubscription::factory()->create(['face_id' => $face->id]);

        $this->assertInstanceOf(Face::class, $subscription->face);
        $this->assertEquals($face->id, $subscription->face->id);
    }

    public function test_face_has_many_subscriptions(): void
    {
        $face = Face::factory()->create();
        FaceSubscription::factory()->count(3)->create(['face_id' => $face->id]);

        $this->assertCount(3, $face->subscriptions);
        $this->assertInstanceOf(FaceSubscription::class, $face->subscriptions->first());
    }

    public function test_foreign_key_cascades_subscription_when_face_deleted(): void
    {
        $face = Face::factory()->create();
        $subscription = FaceSubscription::factory()->create(['face_id' => $face->id]);
        $subscriptionId = $subscription->id;

        $face->delete();

        $this->assertDatabaseMissing('face_subscriptions', ['id' => $subscriptionId]);
    }

    public function test_status_and_plan_are_cast_to_enums(): void
    {
        $subscription = FaceSubscription::factory()->create([
            'status' => FaceSubscriptionStatus::Active,
            'plan' => FaceSubscriptionPlan::AnnualPremium,
        ]);

        $subscription->refresh();

        $this->assertInstanceOf(FaceSubscriptionStatus::class, $subscription->status);
        $this->assertInstanceOf(FaceSubscriptionPlan::class, $subscription->plan);
        $this->assertEquals(FaceSubscriptionStatus::Active, $subscription->status);
        $this->assertEquals(FaceSubscriptionPlan::AnnualPremium, $subscription->plan);
    }

    public function test_active_scope_returns_only_active_unexpired_subscriptions(): void
    {
        $face = Face::factory()->create();

        $activeSub = FaceSubscription::factory()->active()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->expired()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->cancelled()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->failed()->create(['face_id' => $face->id]);
        FaceSubscription::factory()->pendingPayment()->create(['face_id' => $face->id]);

        $activeRows = FaceSubscription::query()->active()->get();

        $this->assertCount(1, $activeRows);
        $this->assertEquals($activeSub->id, $activeRows->first()->id);
    }

    public function test_active_scope_excludes_active_row_with_past_expiry(): void
    {
        $face = Face::factory()->create();

        FaceSubscription::factory()
            ->active()
            ->create([
                'face_id' => $face->id,
                'expires_at' => now()->subDay(),
            ]);

        $this->assertCount(0, FaceSubscription::query()->active()->get());
    }

    public function test_is_active_helper_matches_scope_semantic(): void
    {
        $face = Face::factory()->create();

        $active = FaceSubscription::factory()->active()->create(['face_id' => $face->id]);
        $expired = FaceSubscription::factory()->expired()->create(['face_id' => $face->id]);
        $cancelled = FaceSubscription::factory()->cancelled()->create(['face_id' => $face->id]);
        $failed = FaceSubscription::factory()->failed()->create(['face_id' => $face->id]);
        $pending = FaceSubscription::factory()->pendingPayment()->create(['face_id' => $face->id]);
        $activeWithoutExpiry = FaceSubscription::factory()->active()->create([
            'face_id' => $face->id,
            'expires_at' => null,
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($expired->isActive());
        $this->assertFalse($cancelled->isActive());
        $this->assertFalse($failed->isActive());
        $this->assertFalse($pending->isActive());
        $this->assertFalse($activeWithoutExpiry->isActive());

        $activeIds = FaceSubscription::query()
            ->where('face_id', $face->id)
            ->active()
            ->pluck('id')
            ->all();

        $this->assertSame([$active->id], $activeIds);
    }

    public function test_provider_reference_unique_constraint_prevents_duplicates(): void
    {
        $face = Face::factory()->create();

        FaceSubscription::factory()->create([
            'face_id' => $face->id,
            'provider_reference' => 'fedapay_txn_123',
        ]);

        $this->expectException(QueryException::class);

        FaceSubscription::factory()->create([
            'face_id' => $face->id,
            'provider_reference' => 'fedapay_txn_123',
        ]);
    }

    public function test_provider_reference_unique_constraint_allows_multiple_null_rows(): void
    {
        $face = Face::factory()->create();

        FaceSubscription::factory()->count(3)->create([
            'face_id' => $face->id,
            'provider_reference' => null,
        ]);

        $this->assertEquals(
            3,
            FaceSubscription::query()->where('face_id', $face->id)->count()
        );
    }
}
