<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MissionAttendanceEntryResource;
use App\Models\Mission;
use App\Models\MissionPaymentCandidature;
use App\Models\User;
use App\Services\MissionAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MissionAttendanceController extends Controller
{
    public function __construct(
        private readonly MissionAttendanceService $attendanceService,
    ) {}

    public function dispute(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        /** @var MissionPaymentCandidature|null $entry */
        $entry = MissionPaymentCandidature::query()
            ->whereHas('missionPayment', fn ($query) => $query->where('mission_id', $mission->id))
            ->where('face_id', $user->userable_id)
            ->first();

        if (! $entry || $entry->attendance_status !== AttendanceStatus::Absent) {
            throw ValidationException::withMessages([
                'attendance' => ['Aucune absence à contester sur cette mission.'],
            ]);
        }

        if ($entry->notified_at === null) {
            throw ValidationException::withMessages([
                'attendance' => ['Aucune absence à contester sur cette mission.'],
            ]);
        }

        if ($entry->notified_at->copy()->addHours(72)->isPast()) {
            throw ValidationException::withMessages([
                'attendance' => ['Le délai de contestation (72h) est dépassé.'],
            ]);
        }

        $entry->loadMissing('face');
        $freshEntry = $this->attendanceService->disputeAttendance($entry, $user);
        $freshEntry->loadMissing('face');

        $mission->refresh();

        return response()->json([
            'data' => [
                'mission' => [
                    'id' => $mission->uuid,
                    'titre' => $mission->titre,
                    'status' => $mission->status->value,
                    'status_label' => $mission->status->label(),
                ],
                'entry' => new MissionAttendanceEntryResource($freshEntry),
            ],
            'message' => 'Votre contestation a bien été enregistrée.',
        ]);
    }
}
