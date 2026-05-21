<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FaceSubscriptionAdminAction;
use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Events\FaceSubscriptionActivated;
use App\Events\FaceSubscriptionCancelled;
use App\Exceptions\FaceSubscriptionConflictException;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Models\FaceSubscriptionAudit;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class FaceSubscriptionAdminService
{
    public function activate(
        Face $face,
        Admin $admin,
        string $notes,
        ?CarbonInterface $startsAt,
        int $durationDays,
    ): FaceSubscription {
        return DB::transaction(function () use ($face, $admin, $notes, $startsAt, $durationDays): FaceSubscription {
            // Serialize concurrent activations on the same Face — without this, the predicate
            // SELECT FOR UPDATE below can return zero rows and (under READ COMMITTED or with
            // an inadequate gap lock) let two parallel admins both insert an Active row.
            Face::query()->lockForUpdate()->find($face->id);

            $existing = FaceSubscription::query()
                ->where('face_id', $face->id)
                ->whereIn('status', [
                    FaceSubscriptionStatus::Active,
                    FaceSubscriptionStatus::PendingPayment,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($existing as $row) {
                if ($row->status === FaceSubscriptionStatus::Active
                    && $row->expires_at !== null
                    && $row->expires_at->isFuture()
                ) {
                    throw FaceSubscriptionConflictException::alreadyActive();
                }

                if ($row->status === FaceSubscriptionStatus::PendingPayment) {
                    throw FaceSubscriptionConflictException::pendingPaymentExists();
                }
            }

            $resolvedStarts = $startsAt?->copy() ?? Carbon::now();
            $resolvedExpires = $resolvedStarts->copy()->addDays($durationDays);

            $subscription = FaceSubscription::create([
                'face_id' => $face->id,
                'plan' => FaceSubscriptionPlan::Pro,
                'status' => FaceSubscriptionStatus::Active,
                'starts_at' => $resolvedStarts,
                'expires_at' => $resolvedExpires,
                'cancelled_at' => null,
                'paid_amount' => null,
                'currency' => 'XOF',
                'provider' => null,
                'provider_reference' => null,
                'metadata' => null,
            ]);

            $this->writeAudit(
                subscription: $subscription,
                admin: $admin,
                action: FaceSubscriptionAdminAction::ManualActivate,
                notes: $notes,
                previousState: null,
                newState: $this->snapshot($subscription),
            );

            DB::afterCommit(fn (): mixed => FaceSubscriptionActivated::dispatch($subscription));

            return $subscription;
        });
    }

    public function extend(
        FaceSubscription $subscription,
        Admin $admin,
        string $notes,
        int $additionalDays,
    ): FaceSubscription {
        return DB::transaction(function () use ($subscription, $admin, $notes, $additionalDays): FaceSubscription {
            /** @var FaceSubscription $locked */
            $locked = FaceSubscription::lockForUpdate()->findOrFail($subscription->id);

            if ($locked->status !== FaceSubscriptionStatus::Active
                || $locked->expires_at === null
                || ! $locked->expires_at->isFuture()
            ) {
                throw FaceSubscriptionConflictException::notExtendable();
            }

            $previousState = $this->snapshot($locked);
            $locked->expires_at = $locked->expires_at->copy()->addDays($additionalDays);
            $locked->save();

            $this->writeAudit(
                subscription: $locked,
                admin: $admin,
                action: FaceSubscriptionAdminAction::Extend,
                notes: $notes,
                previousState: $previousState,
                newState: $this->snapshot($locked->fresh()),
            );

            return $locked->fresh();
        });
    }

    public function cancel(
        FaceSubscription $subscription,
        Admin $admin,
        string $notes,
    ): FaceSubscription {
        return DB::transaction(function () use ($subscription, $admin, $notes): FaceSubscription {
            /** @var FaceSubscription $locked */
            $locked = FaceSubscription::lockForUpdate()->findOrFail($subscription->id);

            if (! in_array($locked->status, [
                FaceSubscriptionStatus::Active,
                FaceSubscriptionStatus::PendingPayment,
            ], true)) {
                throw FaceSubscriptionConflictException::notCancellable();
            }

            $previousState = $this->snapshot($locked);
            $locked->status = FaceSubscriptionStatus::Cancelled;
            $locked->cancelled_at = Carbon::now();
            $locked->save();

            $this->writeAudit(
                subscription: $locked,
                admin: $admin,
                action: FaceSubscriptionAdminAction::Cancel,
                notes: $notes,
                previousState: $previousState,
                newState: $this->snapshot($locked->fresh()),
            );

            $fresh = $locked->fresh();

            DB::afterCommit(fn (): mixed => FaceSubscriptionCancelled::dispatch($fresh));

            return $fresh;
        });
    }

    public function correct(
        FaceSubscription $subscription,
        Admin $admin,
        string $notes,
        ?CarbonInterface $newStartsAt,
        ?CarbonInterface $newExpiresAt,
    ): FaceSubscription {
        return DB::transaction(function () use ($subscription, $admin, $notes, $newStartsAt, $newExpiresAt): FaceSubscription {
            /** @var FaceSubscription $locked */
            $locked = FaceSubscription::lockForUpdate()->findOrFail($subscription->id);

            $previousState = $this->snapshot($locked);
            if ($newStartsAt !== null) {
                $locked->starts_at = $newStartsAt->copy();
            }
            if ($newExpiresAt !== null) {
                $locked->expires_at = $newExpiresAt->copy();
            }
            $locked->save();

            $this->writeAudit(
                subscription: $locked,
                admin: $admin,
                action: FaceSubscriptionAdminAction::CorrectDates,
                notes: $notes,
                previousState: $previousState,
                newState: $this->snapshot($locked->fresh()),
            );

            return $locked->fresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $previousState
     * @param  array<string, mixed>  $newState
     */
    private function writeAudit(
        FaceSubscription $subscription,
        Admin $admin,
        FaceSubscriptionAdminAction $action,
        string $notes,
        ?array $previousState,
        array $newState,
    ): FaceSubscriptionAudit {
        return FaceSubscriptionAudit::create([
            'face_subscription_id' => $subscription->id,
            'admin_id' => $admin->id,
            'action' => $action,
            'notes' => $notes,
            'previous_state' => $previousState,
            'new_state' => $newState,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(FaceSubscription $subscription): array
    {
        return [
            'plan' => $subscription->plan->value,
            'status' => $subscription->status->value,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'expires_at' => $subscription->expires_at?->toIso8601String(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            'paid_amount' => $subscription->paid_amount,
            'currency' => $subscription->currency,
        ];
    }
}
