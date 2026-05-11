<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateFaceSubscriptionRequest;
use App\Http\Requests\Admin\CancelFaceSubscriptionRequest;
use App\Http\Requests\Admin\CorrectFaceSubscriptionRequest;
use App\Http\Requests\Admin\ExtendFaceSubscriptionRequest;
use App\Http\Resources\AdminFaceSubscriptionResource;
use App\Models\Admin;
use App\Models\Face;
use App\Models\FaceSubscription;
use App\Services\FaceSubscriptionAdminService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

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

    public function activate(ActivateFaceSubscriptionRequest $request, Face $face): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $startsAt = $request->filled('starts_at')
            ? Carbon::parse($request->validated('starts_at'))
            : null;
        $durationDays = (int) ($request->validated('duration_days') ?? 365);

        $subscription = $this->service->activate(
            face: $face,
            admin: $admin,
            notes: (string) $request->validated('notes'),
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
}
