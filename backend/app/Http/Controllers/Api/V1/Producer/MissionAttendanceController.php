<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\ValidateMissionAttendanceRequest;
use App\Http\Resources\MissionAttendanceEntryResource;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\Producer;
use App\Models\User;
use App\Services\MissionAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MissionAttendanceController extends Controller
{
    public function __construct(
        private readonly MissionAttendanceService $attendanceService,
    ) {}

    public function show(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || $user->userable_type !== Producer::class || $user->userable_id !== $mission->producer_id) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        if (! in_array($mission->status, [MissionStatus::Closed, MissionStatus::PendingAttendanceValidation], true)) {
            throw ValidationException::withMessages([
                'status' => ['La validation des présences n\'est possible que sur une mission clôturée ou en attente de validation des présences.'],
            ]);
        }

        /** @var MissionPayment|null $payment */
        $payment = $mission->payment;

        if (! $payment || $payment->status !== MissionPaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => ['La validation des présences requiert un paiement confirmé sur la mission.'],
            ]);
        }

        $entries = $payment->entries()
            ->with('face')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => [
                'mission' => $this->missionPayload($mission),
                'payment' => [
                    'montant_total_producteur' => (int) $payment->montant_total_producteur,
                    'nombre_faces_retenues' => (int) $payment->nombre_faces_retenues,
                ],
                'entries' => MissionAttendanceEntryResource::collection($entries),
            ],
        ]);
    }

    public function validate(ValidateMissionAttendanceRequest $request, Mission $mission): JsonResponse
    {
        /** @var array<int, array{entry_id: int|string, status: 'present'|'absent'}> $rawEntries */
        $rawEntries = $request->validated('entries');

        $entryMap = [];
        foreach ($rawEntries as $row) {
            $entryMap[(int) $row['entry_id']] = $row['status'];
        }

        /** @var User $user */
        $user = $request->user();

        $freshMission = $this->attendanceService->markAttendance($mission, $entryMap, $user);

        /** @var MissionPayment|null $payment */
        $payment = $freshMission->payment;
        $entries = $payment
            ? $payment->entries()->with('face')->orderBy('id')->get()
            : new Collection;

        return response()->json([
            'data' => [
                'mission' => $this->missionPayload($freshMission),
                'entries' => MissionAttendanceEntryResource::collection($entries),
            ],
            'message' => 'Présences validées avec succès.',
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function missionPayload(Mission $mission): array
    {
        return [
            'id' => $mission->uuid,
            'titre' => $mission->titre,
            'status' => $mission->status->value,
            'status_label' => $mission->status->label(),
            'date_tournage' => $mission->date_tournage?->toIso8601String(),
        ];
    }
}
