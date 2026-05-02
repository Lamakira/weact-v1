<?php

declare(strict_types=1);

namespace App\Http\Requests\Mission;

use App\Enums\MissionPaymentStatus;
use App\Enums\MissionStatus;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ValidateMissionAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || $user->userable_type !== Producer::class) {
            return false;
        }

        /** @var Mission $mission */
        $mission = $this->route('mission');

        return $user->userable_id === $mission->producer_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*' => ['required', 'array'],
            'entries.*.entry_id' => ['required', 'integer', 'min:1', 'distinct'],
            'entries.*.status' => ['required', 'string', 'in:present,absent'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entries.required' => 'Au moins une entry doit être fournie.',
            'entries.array' => 'Au moins une entry doit être fournie.',
            'entries.min' => 'Au moins une entry doit être fournie.',
            'entries.*.entry_id.required' => 'L\'identifiant de l\'entry est requis.',
            'entries.*.entry_id.integer' => 'L\'identifiant de l\'entry doit être un entier positif.',
            'entries.*.entry_id.min' => 'L\'identifiant de l\'entry doit être un entier positif.',
            'entries.*.entry_id.distinct' => 'Chaque entry ne peut être fournie qu\'une seule fois.',
            'entries.*.status.required' => 'Le statut de l\'entry est requis.',
            'entries.*.status.in' => 'Les statuts acceptés sont : present, absent.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Mission $mission */
            $mission = $this->route('mission');

            if (! in_array($mission->status, [MissionStatus::Closed, MissionStatus::PendingAttendanceValidation], true)) {
                $validator->errors()->add(
                    'status',
                    'La validation des présences n\'est possible que sur une mission clôturée ou en attente de validation des présences.'
                );

                return;
            }

            /** @var MissionPayment|null $payment */
            $payment = $mission->payment;

            if (! $payment || $payment->status !== MissionPaymentStatus::Paid) {
                $validator->errors()->add(
                    'payment',
                    'La validation des présences requiert un paiement confirmé sur la mission.'
                );

                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var array<int, array{entry_id: int|string, status: string}> $entries */
            $entries = (array) $this->input('entries', []);
            $payloadEntryIds = array_values(array_unique(array_map(
                static fn (array $row): int => (int) $row['entry_id'],
                $entries,
            )));

            if ($payloadEntryIds === []) {
                return;
            }

            $missionEntryIds = MissionPaymentCandidature::query()
                ->whereHas('missionPayment', fn ($query) => $query->where('mission_id', $mission->id))
                ->whereIn('id', $payloadEntryIds)
                ->pluck('id')
                ->map(static fn (mixed $id): int => match (true) {
                    is_int($id) => $id,
                    is_string($id) => (int) $id,
                    default => 0,
                })
                ->all();

            $foreignEntryIds = array_diff($payloadEntryIds, $missionEntryIds);

            if ($foreignEntryIds !== []) {
                $validator->errors()->add(
                    'entries',
                    'Une ou plusieurs entries ne correspondent pas à cette mission.'
                );
            }
        });
    }
}
