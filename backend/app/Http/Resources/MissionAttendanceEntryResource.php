<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AttendanceStatus;
use App\Enums\EscrowStatus;
use App\Models\Face;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MissionPaymentCandidature
 */
class MissionAttendanceEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attendanceStatus = $this->attendance_status ?? AttendanceStatus::Pending;
        $escrowStatus = $this->escrow_status ?? EscrowStatus::Pending;

        return [
            'id' => $this->id,
            'face' => $this->whenLoaded('face', function (): array {
                /** @var Face $face */
                $face = $this->face;

                return [
                    'id' => $face->uuid,
                    'display_name' => $face->display_name,
                    'profile_photo_url' => $face->profile_photo_url,
                    'profile_photo_thumbnail_url' => $face->thumbnail_url,
                ];
            }),
            'montant_face_recoit' => (int) $this->montant_face_recoit,
            'attendance_status' => $attendanceStatus->value,
            'attendance_status_label' => $this->attendanceStatusLabel($attendanceStatus),
            'escrow_status' => $escrowStatus->value,
            'escrow_status_label' => $this->escrowStatusLabel($escrowStatus),
            'released_at' => $this->dateTimeToIso8601($this->released_at),
            'refunded_at' => $this->dateTimeToIso8601($this->refunded_at),
            'notified_at' => $this->dateTimeToIso8601($this->notified_at),
        ];
    }

    private function attendanceStatusLabel(AttendanceStatus $status): string
    {
        return match ($status) {
            AttendanceStatus::Pending => 'En attente',
            AttendanceStatus::Present => 'Présente',
            AttendanceStatus::Absent => 'Absente',
            AttendanceStatus::Disputed => 'Contestée',
        };
    }

    private function escrowStatusLabel(EscrowStatus $status): string
    {
        return match ($status) {
            EscrowStatus::Pending => 'En attente',
            EscrowStatus::Locked => 'Bloqué',
            EscrowStatus::Released => 'Libéré',
            EscrowStatus::Refunded => 'Remboursé',
        };
    }

    private function dateTimeToIso8601(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return is_string($value) ? $value : null;
    }
}
