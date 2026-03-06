<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use FedaPay\FedaPay;
use FedaPay\Payout;
use FedaPay\Transaction;
use FedaPay\Webhook;

class FedapayService
{
    public function __construct()
    {
        FedaPay::setApiKey(config('services.fedapay.secret_key'));
        FedaPay::setEnvironment(config('services.fedapay.environment'));
    }

    /**
     * Initiate a Mobile Money payment for a booking.
     *
     * @param  array{number: string, country: string}  $phoneData
     * @return array{fedapay_transaction_id: int, status: string}
     */
    public function initiatePayment(Booking $booking, string $mode, string $idempotencyKey, array $phoneData): array
    {
        $producer = $booking->producer;

        $transaction = Transaction::create([
            'description' => "Booking #{$booking->id}",
            'amount' => $booking->montant_total_producteur,
            'currency' => ['iso' => 'XOF'],
            'callback_url' => route('webhooks.fedapay'),
            'custom_metadata' => [
                'booking_id' => $booking->id,
                'idempotency_key' => $idempotencyKey,
            ],
            'customer' => [
                'firstname' => $producer->name,
                'email' => $producer->email,
                'phone_number' => [
                    'number' => $phoneData['number'],
                    'country' => $phoneData['country'],
                ],
            ],
        ]);

        $token = $transaction->generateToken()->token;
        $transaction->sendNowWithToken($mode, $token, [
            'number' => $phoneData['number'],
            'country' => $phoneData['country'],
        ]);

        return [
            'fedapay_transaction_id' => (int) $transaction->id,
            'status' => $transaction->status,
        ];
    }

    /**
     * Initiate a partial refund for a cancelled paid booking.
     *
     * @return array{fedapay_refund_id: int, status: string}
     */
    public function initiateRefund(Booking $booking, int $refundAmount, string $idempotencyKey): array
    {
        if ($booking->fedapay_transaction_id === null) {
            throw new \RuntimeException('Aucune transaction Fedapay liée au booking pour effectuer un remboursement.');
        }

        $transaction = Transaction::retrieve($booking->fedapay_transaction_id);
        $refund = $transaction->refund([
            'amount' => $refundAmount,
            'custom_metadata' => [
                'booking_id' => $booking->id,
                'idempotency_key' => $idempotencyKey,
            ],
        ]);

        return [
            'fedapay_refund_id' => (int) $refund->id,
            'status' => (string) ($refund->status ?? 'pending'),
        ];
    }

    /**
     * Verify a Fedapay webhook signature and return the event.
     *
     * @throws \FedaPay\Error\SignatureVerification
     */
    public function verifyWebhookSignature(string $payload, string $signature): object
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            config('services.fedapay.webhook_secret')
        );
    }

    /**
     * Retrieve a Fedapay transaction by ID.
     */
    public function retrieveTransaction(int $id): Transaction
    {
        return Transaction::retrieve($id);
    }

    /**
     * Initiate a Mobile Money payout (withdrawal) to a Face.
     * Uses Fedapay Payout API: Payout::create() + sendNow().
     *
     * @param  array{number: string, country: string}  $phoneData
     * @return array{fedapay_payout_id: int, status: string}
     * @throws \FedaPay\Error\ApiError on Fedapay failure
     */
    public function initiateWithdrawal(int $amount, string $mode, string $idempotencyKey, array $phoneData, User $user): array
    {
        $payout = Payout::create([
            'amount'   => $amount,
            'currency' => ['iso' => 'XOF'],
            'mode'     => $mode,
            'customer' => [
                'firstname' => $user->name,
                'email'     => $user->email,
                'phone_number' => [
                    'number'  => $phoneData['number'],
                    'country' => $phoneData['country'],
                ],
            ],
            'metadata' => [
                'user_id'         => $user->id,
                'idempotency_key' => $idempotencyKey,
            ],
        ]);

        $payout->sendNow([
            'phone_number' => [
                'number'  => $phoneData['number'],
                'country' => $phoneData['country'],
            ],
        ]);

        return [
            'fedapay_payout_id' => (int) $payout->id,
            'status'            => $payout->status,
        ];
    }
}
