<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Face;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\MissionPaymentCandidature
 */
class AdminAttendanceDisputeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mission' => $this->whenLoaded('missionPayment', function (): ?array {
                $payment = $this->missionPayment;
                $mission = $payment?->mission;

                if (! $mission) {
                    return null;
                }

                return [
                    'id' => $mission->uuid,
                    'titre' => $mission->titre,
                    'date_tournage' => $mission->date_tournage?->toIso8601String(),
                    'producer' => $mission->producer
                        ? [
                            'id' => $mission->producer->uuid,
                            'display_name' => $mission->producer->display_name,
                        ]
                        : null,
                ];
            }),
            'face' => $this->whenLoaded('face', function (): array {
                /** @var Face $face */
                $face = $this->face;

                return [
                    'id' => $face->uuid,
                    'display_name' => $face->display_name,
                    'profile_photo_url' => $face->profile_photo_url,
                ];
            }),
            'montant_face_recoit' => (int) $this->montant_face_recoit,
            'attendance_status' => $this->attendance_status->value,
            'escrow_status' => $this->escrow_status->value,
            'notified_at' => $this->dateTimeToIso8601($this->notified_at),
            'disputed_at' => $this->dateTimeToIso8601($this->updated_at),
        ];
    }

    private function dateTimeToIso8601(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return is_string($value) ? $value : null;
    }
}
