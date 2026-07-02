<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\FaceSubscriptionAdminAction;
use App\Models\Admin;
use App\Models\FaceSubscription;
use App\Models\FaceSubscriptionAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FaceSubscriptionAuditSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns_in_order(): void
    {
        $expected = [
            'id',
            'uuid',
            'face_subscription_id',
            'admin_id',
            'action',
            'notes',
            'previous_state',
            'new_state',
            'created_at',
        ];

        $this->assertSame($expected, Schema::getColumnListing('face_subscription_audits'));
    }

    public function test_model_has_updated_at_disabled(): void
    {
        $this->assertNull(FaceSubscriptionAudit::UPDATED_AT);
    }

    public function test_action_column_casts_to_enum(): void
    {
        $sub = FaceSubscription::factory()->active()->create();
        $admin = Admin::factory()->create();

        $audit = FaceSubscriptionAudit::create([
            'face_subscription_id' => $sub->id,
            'admin_id' => $admin->id,
            'action' => FaceSubscriptionAdminAction::Extend,
            'notes' => 'enum cast test 12345',
            'previous_state' => null,
            'new_state' => [
                'plan' => 'annual_premium',
                'status' => 'active',
                'starts_at' => null,
                'expires_at' => null,
                'cancelled_at' => null,
                'paid_amount' => null,
                'currency' => 'XOF',
            ],
        ]);

        $this->assertInstanceOf(FaceSubscriptionAdminAction::class, $audit->fresh()->action);
        $this->assertSame('extend', $audit->fresh()->action->value);
    }

    public function test_previous_state_and_new_state_cast_to_array(): void
    {
        $sub = FaceSubscription::factory()->active()->create();
        $admin = Admin::factory()->create();

        $audit = FaceSubscriptionAudit::create([
            'face_subscription_id' => $sub->id,
            'admin_id' => $admin->id,
            'action' => FaceSubscriptionAdminAction::Cancel,
            'notes' => 'json cast test 12345',
            'previous_state' => [
                'plan' => 'annual_premium',
                'status' => 'active',
                'starts_at' => null,
                'expires_at' => null,
                'cancelled_at' => null,
                'paid_amount' => null,
                'currency' => 'XOF',
            ],
            'new_state' => [
                'plan' => 'annual_premium',
                'status' => 'cancelled',
                'starts_at' => null,
                'expires_at' => null,
                'cancelled_at' => '2026-05-11T15:00:00+00:00',
                'paid_amount' => null,
                'currency' => 'XOF',
            ],
        ]);

        $fresh = $audit->fresh();
        $this->assertIsArray($fresh->previous_state);
        $this->assertIsArray($fresh->new_state);
        $this->assertSame('active', $fresh->previous_state['status']);
        $this->assertSame('cancelled', $fresh->new_state['status']);
    }

    public function test_face_subscription_audit_cascades_when_face_subscription_is_deleted(): void
    {
        $sub = FaceSubscription::factory()->active()->create();
        FaceSubscriptionAudit::factory()->create(['face_subscription_id' => $sub->id]);

        $this->assertDatabaseCount('face_subscription_audits', 1);
        $sub->delete();
        $this->assertDatabaseCount('face_subscription_audits', 0);
    }

    public function test_admin_id_is_set_null_when_admin_is_deleted(): void
    {
        $admin = Admin::factory()->create();
        $sub = FaceSubscription::factory()->active()->create();
        $audit = FaceSubscriptionAudit::factory()->create([
            'face_subscription_id' => $sub->id,
            'admin_id' => $admin->id,
        ]);

        $admin->delete();
        $audit->refresh();

        $this->assertNull($audit->admin_id);
        $this->assertDatabaseHas('face_subscription_audits', ['id' => $audit->id]);
    }
}
