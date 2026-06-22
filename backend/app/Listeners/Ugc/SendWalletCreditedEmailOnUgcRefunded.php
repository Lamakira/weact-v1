<?php

declare(strict_types=1);

namespace App\Listeners\Ugc;

use App\Enums\WalletCreditMotif;
use App\Events\UgcCommissionRefunded;
use App\Mail\WalletCreditedMail;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Canal email ADDITIF (ugc-7-5) sur UgcCommissionRefunded : confirme au Producteur le
 * crédit wallet de son remboursement UGC (refus payé / fenêtre expirée / deadline mission).
 * Miroir EXACT de NotifyProducerOnUgcRefunded (amount/producer). Réutilise WalletCreditedMail.
 */
#[AsEventListener(event: UgcCommissionRefunded::class)]
final class SendWalletCreditedEmailOnUgcRefunded
{
    public function handle(UgcCommissionRefunded $event): void
    {
        try {
            $owner = $event->owner;

            if ($owner instanceof Mission) {
                // missions.producer_id = producers.id → Producer direct ; User via morphOne userable.
                $owner->loadMissing('producer.user');
                $producer = $owner->producer;
                $producerUserId = $producer?->user?->getKey();
                $amount = (int) $owner->commission_ugc;
                $motif = WalletCreditMotif::UgcCommissionRefund;
            } else {
                // bookings.producer_id = users.id → producer() renvoie le User ; .userable = Producer.
                $owner->loadMissing('producer.userable');
                $producer = $owner->producer->userable instanceof Producer
                    ? $owner->producer->userable
                    : null;
                $producerUserId = $owner->producer_id;
                $amount = (int) $owner->montant_total_producteur;
                $motif = WalletCreditMotif::UgcSettlementRefund;
            }

            if ($producer === null || $producerUserId === null) {
                return;
            }

            // User::find(int) résout le User typé (booking producer_id=users.id ; mission via
            // getKey()) → email + balance ; larastan type find(int) non-null (cf. 7-4).
            $producerUser = User::find((int) $producerUserId);
            $producerEmail = trim((string) $producerUser->email);
            if ($producerEmail === '') {
                return;
            }

            $newBalance = (int) ($producerUser->balance ?? 0);

            Mail::to($producerEmail)->queue(new WalletCreditedMail(
                producer: $producer,
                amount: $amount,
                motif: $motif,
                newBalance: $newBalance,
            ));
        } catch (\Throwable $e) {
            Log::warning('WalletCreditedMail (ugc refund) queue failed', [
                'owner_type' => $event->owner::class,
                'owner_id' => $event->owner->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
