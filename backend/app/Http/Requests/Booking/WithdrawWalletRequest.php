<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Models\WithdrawalRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawWalletRequest extends FormRequest
{
    /** Minimum withdrawal amount in XOF. */
    private const MIN_AMOUNT = 500;

    /**
     * ARCEP Bénin — official 4-digit EZAB prefixes per operator.
     * Source: arcep.bj
     *
     * @var array<string, list<string>>
     */
    private const BENIN_PREFIXES = [
        'mtn' => ['0142', '0146', '0150', '0151', '0152', '0153', '0154', '0156', '0157', '0159', '0161', '0162', '0166', '0167', '0169', '0190', '0191', '0196', '0197'],
        'moov' => ['0145', '0155', '0158', '0160', '0163', '0164', '0165', '0168', '0194', '0195', '0198', '0199'],
        'celtiis' => ['0120', '0121', '0122', '0123', '0124', '0128', '0129', '0140', '0141', '0143', '0144', '0147', '0148', '0149', '0192', '0193'],
    ];

    public function authorize(): bool
    {
        // Authorization handled by 'face_or_producer' middleware on route
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $balance = (int) ($this->user()->balance ?? 0);

        return [
            'amount' => ['required', 'integer', 'min:'.self::MIN_AMOUNT, "max:{$balance}"],
            'payment_mode' => ['required', 'string', 'in:mtn,moov,celtiis'.(app()->environment('local') ? ',momo_test' : '')],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]{8,15}$/'],
            'phone_country' => ['required', 'string', 'in:bj,tg,ci,sn,bf'],
        ];
    }

    /**
     * Cross-field validation: pending withdrawal check + Bénin prefix check.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->checkNoPendingWithdrawal($validator);
            $this->checkBeninPrefix($validator);
        });
    }

    /**
     * Block submission if the user already has a pending withdrawal request.
     * Prevents multiple simultaneous requests from depleting the balance before admin approval.
     */
    private function checkNoPendingWithdrawal(Validator $validator): void
    {
        $user = $this->user();

        if (! $user) {
            return;
        }

        $hasPending = WithdrawalRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            $validator->errors()->add(
                'amount',
                'Vous avez déjà une demande de retrait en attente de traitement. Veuillez patienter avant d\'en soumettre une nouvelle.'
            );
        }
    }

    /**
     * For Bénin numbers, validate that the 4-digit EZAB prefix matches the selected operator.
     * Only applied when phone_country = 'bj' and operator is known.
     */
    private function checkBeninPrefix(Validator $validator): void
    {
        if ($this->input('phone_country') !== 'bj') {
            return;
        }

        $operator = $this->input('payment_mode', '');
        $phone = preg_replace('/\D/', '', (string) $this->input('phone_number', ''));

        if (! isset(self::BENIN_PREFIXES[$operator])) {
            return; // momo_test or unrecognised — skip prefix check
        }

        if (strlen($phone) !== 10) {
            $validator->errors()->add(
                'phone_number',
                'Les numéros béninois doivent comporter 10 chiffres (format EZAB, ex: 0197XXXXXX).'
            );

            return;
        }

        $prefix = substr($phone, 0, 4);

        if (! in_array($prefix, self::BENIN_PREFIXES[$operator], true)) {
            $label = ucfirst($operator);
            $validator->errors()->add(
                'phone_number',
                "Ce numéro ne correspond pas à un préfixe {$label} valide au Bénin."
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $balance = (int) ($this->user()->balance ?? 0);
        $formatted = number_format($balance, 0, ',', ' ');
        $min = number_format(self::MIN_AMOUNT, 0, ',', ' ');

        return [
            'amount.max' => "Solde insuffisant (solde disponible : {$formatted} XOF)",
            'amount.min' => "Le montant minimum de retrait est de {$min} XOF",
        ];
    }
}
