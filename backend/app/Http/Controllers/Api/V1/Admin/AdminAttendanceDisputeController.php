<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Enums\EscrowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAttendanceDisputesRequest;
use App\Http\Requests\Admin\ResolveAttendanceDisputeRequest;
use App\Http\Resources\AdminAttendanceDisputeResource;
use App\Models\Admin;
use App\Models\MissionPaymentCandidature;
use App\Services\MissionAttendanceService;
use Illuminate\Http\JsonResponse;

class AdminAttendanceDisputeController extends Controller
{
    public function __construct(
        private readonly MissionAttendanceService $attendanceService,
    ) {}

    public function index(IndexAttendanceDisputesRequest $request): JsonResponse
    {
        $disputes = MissionPaymentCandidature::query()
            ->where('escrow_status', EscrowStatus::Locked)
            ->where('attendance_status', AttendanceStatus::Disputed)
            ->with(['face', 'missionPayment.mission.producer'])
            ->orderBy('updated_at')
            ->paginate(20);

        return response()->json([
            'data' => AdminAttendanceDisputeResource::collection($disputes->items()),
            'meta' => [
                'current_page' => $disputes->currentPage(),
                'last_page' => $disputes->lastPage(),
                'per_page' => $disputes->perPage(),
                'total' => $disputes->total(),
            ],
            'message' => 'Litiges récupérés avec succès',
        ]);
    }

    public function resolve(
        ResolveAttendanceDisputeRequest $request,
        MissionPaymentCandidature $entry,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = $request->user();

        $outcome = $request->validated('outcome') === 'face'
            ? DisputeResolutionOutcome::FavorFace
            : DisputeResolutionOutcome::FavorProducer;

        /** @var string $notes */
        $notes = $request->validated('notes');
        $notes = trim($notes);

        $resolved = $this->attendanceService->resolveDispute($entry, $outcome, $admin, $notes);
        $resolved->loadMissing(['face', 'missionPayment.mission.producer']);

        return response()->json([
            'data' => new AdminAttendanceDisputeResource($resolved),
            'message' => 'Litige résolu avec succès',
        ]);
    }
}
