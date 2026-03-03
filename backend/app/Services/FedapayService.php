<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use FedaPay\FedaPay;
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
}
