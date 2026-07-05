<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FaceSubscriptionPlan;
use App\Enums\FaceSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateFaceSubscriptionRequest;
use App\Http\Requests\Admin\CancelFaceSubscriptionRequest;
use App\Http\Requests\Admin\ChangeTierFaceSubscriptionRequest;
use App\Http\Requests\Admin\CorrectFaceSubscriptionRequest;
use App\Http\Requests\Admin\ExtendFaceSubscriptionRequest;
use App\Http\Resources\AdminFaceSubscriptionResource;
use App\Http\Resources\AdminFaceSubscriptionStatsResource;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Services\FaceSubscriptionAdminService;
use App\Support\Sql;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFaceSubscriptionController extends Controller
{
    public function __construct(
        private readonly FaceSubscriptionAdminService $service,
    ) {}

    public function index(Face $face): JsonResponse
    {
        $subscriptions = $face->subscriptions()
            ->with(['audits.admin'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'face' => [
                    'id' => $face->uuid,
                    'display_name' => $face->display_name,
                ],
                'subscriptions' => AdminFaceSubscriptionResource::collection($subscriptions),
            ],
        ]);
    }

    /**
     * Cross-Face paginated list of all subscriptions (back-office « Abonnements »).
     *
     * Filters: plan, status, search (nom/prenom/username of the Face, LIKE escaped).
     * Sort: expires_at asc (default) or desc — NULL expirations always last.
     */
    public function list(Request $request): JsonResponse
    {
        $query = FaceSubscription::with('face');

        // Filter by plan (invalid values ignored)
        if ($request->filled('plan') && is_string($request->query('plan'))
            && in_array($request->query('plan'), FaceSubscriptionPlan::values(), true)
        ) {
            $query->where('plan', $request->query('plan'));
        }

        // Filter by status (invalid values ignored). « active » applies the
        // canonical scopeActive semantics (status + unexpired) so the list
        // never contradicts the stats() KPIs when the expiry cron lags — and
        // « expired » MIRRORS it: a stale-active row (status column still
        // active, expires_at past, cron late) surfaces under « expired »,
        // otherwise no status filter could show it at all. Its badge reads
        // « Active » with a visibly past expiry date — cron lag, explicit.
        if ($request->filled('status') && is_string($request->query('status'))
            && in_array($request->query('status'), FaceSubscriptionStatus::values(), true)
        ) {
            if ($request->query('status') === FaceSubscriptionStatus::Active->value) {
                $query->active();
            } elseif ($request->query('status') === FaceSubscriptionStatus::Expired->value) {
                $query->where(function ($q) {
                    $q->where('status', FaceSubscriptionStatus::Expired)
                        ->orWhere(function ($qq) {
                            $qq->where('status', FaceSubscriptionStatus::Active)
                                ->where('expires_at', '<=', now());
                        });
                });
            } else {
                $query->where('status', $request->query('status'));
            }
        }

        // Search by Face nom, prenom, or username (trimmed: a trailing space
        // pasted from WhatsApp/mail must not become a literal LIKE character)
        if ($request->filled('search') && is_string($request->query('search'))) {
            $search = Sql::escapeLike(trim($request->query('search')));
            $query->whereHas('face', function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Sort on expires_at, NULL expirations (pending/failed rows) always last
        $direction = $request->query('sort') === 'expires_at_desc' ? 'desc' : 'asc';
        $query->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at', $direction)
            ->orderBy('id', $direction);

        $perPage = is_numeric($request->query('per_page')) ? (int) $request->query('per_page') : 15;
        if ($perPage < 1) {
            $perPage = 15;
        }
        $perPage = min($perPage, 100);

        $subscriptions = $query->paginate($perPage);

        return response()->json([
            'data' => AdminFaceSubscriptionResource::collection($subscriptions),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    /**
     * KPIs for the subscriptions back-office.
     *
     * Revenue follows decision D-1: SUM(paid_amount) dated by paid_at,
     * independent of the current status (a paid-then-expired/cancelled row
     * remains revenue); manual admin activations record paid_amount = 0
     * (PO decision 2026-07-06, legacy NULL rows backfilled) and thus
     * contribute nothing. Rows paid before the paid_at backfill that
     * could not be dated count in the total, never in period aggregates.
     * Months are Africa/Porto-Novo calendar months (config
     * app.business_timezone): the bounds are computed in the business
     * timezone, then converted back to UTC before binding.
     */
    public function stats(): JsonResponse
    {
        $now = Carbon::now();

        // Bornes du mois courant calculées dans le fuseau métier puis
        // reconverties en UTC avant le binding — paid_at est stocké en UTC,
        // et $now (UTC) reste la référence des comparaisons actives/expirations.
        $businessNow = $now->copy()->setTimezone((string) config('app.business_timezone'));

        $activeByPlan = FaceSubscription::query()
            ->active($now)
            ->selectRaw('plan, COUNT(*) as count')
            ->groupBy('plan')
            ->pluck('count', 'plan')
            ->toArray();

        $revenueCurrentMonth = (int) FaceSubscription::query()
            ->whereBetween('paid_at', [
                $businessNow->copy()->startOfMonth()->utc(),
                $businessNow->copy()->endOfMonth()->utc(),
            ])
            ->sum('paid_amount');

        $revenueTotal = (int) FaceSubscription::query()
            ->whereNotNull('paid_amount')
            ->sum('paid_amount');

        // Both cards share the canonical scopeActive definition AND the same
        // captured $now, making the subset invariant structural: a row
        // expiring at this exact instant is excluded from both counts.
        $expiringWithin30Days = FaceSubscription::query()
            ->active($now)
            ->where('expires_at', '<=', $now->copy()->addDays(30))
            ->count();

        $statusCounts = FaceSubscription::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereIn('status', [FaceSubscriptionStatus::PendingPayment, FaceSubscriptionStatus::Failed])
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $activeStarter = (int) ($activeByPlan[FaceSubscriptionPlan::Starter->value] ?? 0);
        $activePro = (int) ($activeByPlan[FaceSubscriptionPlan::Pro->value] ?? 0);
        $activeElite = (int) ($activeByPlan[FaceSubscriptionPlan::Elite->value] ?? 0);

        $stats = [
            'active_starter' => $activeStarter,
            'active_pro' => $activePro,
            'active_elite' => $activeElite,
            // array_sum over the raw GROUP BY, not the three known plans: a
            // future tier must inflate the total instead of vanishing from it.
            'active_total' => (int) array_sum($activeByPlan),
            'revenue_current_month' => $revenueCurrentMonth,
            'revenue_total' => $revenueTotal,
            'currency' => 'XOF',
            'expiring_within_30_days' => $expiringWithin30Days,
            'pending_payment_count' => (int) ($statusCounts[FaceSubscriptionStatus::PendingPayment->value] ?? 0),
            'failed_count' => (int) ($statusCounts[FaceSubscriptionStatus::Failed->value] ?? 0),
        ];

        return response()->json([
            'data' => new AdminFaceSubscriptionStatsResource($stats),
            'message' => 'Statistiques des abonnements récupérées avec succès',
        ]);
    }

    public function activate(ActivateFaceSubscriptionRequest $request, Face $face): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $plan = FaceSubscriptionPlan::from((string) $request->validated('plan'));

        $startsAt = $request->filled('starts_at')
            ? Carbon::parse($request->validated('starts_at'))
            : null;
        $durationDays = (int) ($request->validated('duration_days') ?? 365);

        $subscription = $this->service->activate(
            face: $face,
            admin: $admin,
            notes: (string) $request->validated('notes'),
            plan: $plan,
            startsAt: $startsAt,
            durationDays: $durationDays,
        );

        $subscription->load(['audits.admin']);

        return response()->json([
            'data' => new AdminFaceSubscriptionResource($subscription),
            'message' => 'Abonnement activé manuellement',
        ], 201);
    }

    public function extend(
        ExtendFaceSubscriptionRequest $request,
        FaceSubscription $subscription,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = $request->user();

        $updated = $this->service->extend(
            subscription: $subscription,
            admin: $admin,
            notes: (string) $request->validated('notes'),
            additionalDays: (int) $request->validated('additional_days'),
        );

        $updated->load(['audits.admin']);

        return response()->json([
            'data' => new AdminFaceSubscriptionResource($updated),
            'message' => 'Abonnement étendu',
        ]);
    }

    public function cancel(
        CancelFaceSubscriptionRequest $request,
        FaceSubscription $subscription,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = $request->user();

        $updated = $this->service->cancel(
            subscription: $subscription,
            admin: $admin,
            notes: (string) $request->validated('notes'),
        );

        $updated->load(['audits.admin']);

        return response()->json([
            'data' => new AdminFaceSubscriptionResource($updated),
            'message' => 'Abonnement annulé',
        ]);
    }

    public function correct(
        CorrectFaceSubscriptionRequest $request,
        FaceSubscription $subscription,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = $request->user();

        $newStartsAt = $request->filled('starts_at')
            ? Carbon::parse($request->validated('starts_at'))
            : null;
        $newExpiresAt = $request->filled('expires_at')
            ? Carbon::parse($request->validated('expires_at'))
            : null;

        $updated = $this->service->correct(
            subscription: $subscription,
            admin: $admin,
            notes: (string) $request->validated('notes'),
            newStartsAt: $newStartsAt,
            newExpiresAt: $newExpiresAt,
        );

        $updated->load(['audits.admin']);

        return response()->json([
            'data' => new AdminFaceSubscriptionResource($updated),
            'message' => 'Dates corrigées',
        ]);
    }

    public function changeTier(
        ChangeTierFaceSubscriptionRequest $request,
        FaceSubscription $subscription,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = $request->user();

        $updated = $this->service->changeTier(
            subscription: $subscription,
            admin: $admin,
            notes: (string) $request->validated('notes'),
            newPlan: FaceSubscriptionPlan::from((string) $request->validated('new_plan')),
        );

        $updated->load(['audits.admin']);

        return response()->json([
            'data' => new AdminFaceSubscriptionResource($updated),
            'message' => 'Palier modifié',
        ]);
    }
}
