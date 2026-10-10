<?php

declare(strict_types=1);

namespace App\Support\Push;

/**
 * Single source of truth for which in-app notification types also trigger a web push.
 *
 * Every `App\Models\Notification` type emitted in app/ must be either in ALLOWED
 * (type => French push title) or in EXCLUDED (type => reason). A test scans app/
 * so that a new type forces an explicit decision here.
 */
final class PushAllowlist
{
    public const BOOKING = 'Booking';

    public const MISSION = 'Mission';

    public const UGC = 'UGC';

    public const PAYMENT = 'Paiement';

    public const SUBSCRIPTION = 'Abonnement';

    public const REMINDER = 'Rappel';

    /**
     * Action-needed or money events: type => push title.
     *
     * @var array<string, string>
     */
    public const ALLOWED = [
        // Booking lifecycle
        'booking_received' => self::BOOKING,
        'booking_accepted' => self::BOOKING,
        'booking_refused' => self::BOOKING,
        'booking_cancelled' => self::BOOKING,
        'booking_expired' => self::BOOKING,
        'booking_no_show' => self::BOOKING,
        'booking_dispute_opened' => self::BOOKING,
        'booking_dispute_settled' => self::BOOKING,
        'booking_confirmation_pending' => self::BOOKING,
        'booking_completed' => self::BOOKING,

        // Money
        'booking_paid' => self::PAYMENT,
        'booking_wallet_credited' => self::PAYMENT,
        'mission_payment_confirmed' => self::PAYMENT,
        'mission_completed' => self::PAYMENT,
        'mission_candidature_refunded' => self::PAYMENT,
        'mission_candidature_payment_failed' => self::PAYMENT,
        'mission_detached_payment_credited' => self::PAYMENT,
        'ugc_commission_paid' => self::PAYMENT,
        'ugc_commission_refunded' => self::PAYMENT,

        // Reminders
        'booking_payment_reminder' => self::REMINDER,
        'booking_completion_reminder' => self::REMINDER,
        'shooting_day_reminder' => self::REMINDER,
        'ugc_deliverable_deadline_approaching' => self::REMINDER,

        // Missions and candidatures
        'new_candidature' => self::MISSION,
        'candidature_accepted' => self::MISSION,
        'candidature_rejected' => self::MISSION,
        'face_confirmed_participation' => self::MISSION,
        'mission_attendance_absent' => self::MISSION,
        'mission_deleted_candidature_cancelled' => self::MISSION,
        'mission_selection_reset_producer' => self::MISSION,
        'mission_selection_reset' => self::MISSION,
        // The Face loses an acceptance after a failed Producer payment: she must know.
        'candidature_reset_from_accepted' => self::MISSION,
        'mission_completed_producer' => self::MISSION,
        'mission_closed_pending_candidature' => self::MISSION,
        'mission_participation_confirmation_required' => self::MISSION,
        'mission_candidature_slot_released' => self::MISSION,

        // UGC tunnel
        'ugc_deal_accepted' => self::UGC,
        'ugc_shipment_confirmed' => self::UGC,
        'ugc_product_received' => self::UGC,
        'ugc_deliverable_uploaded' => self::UGC,
        'ugc_deliverable_validated' => self::UGC,
        'ugc_deliverable_rejected' => self::UGC,
        'ugc_deliverable_retouche_requested' => self::UGC,
        'ugc_account_suspended' => self::UGC,
        'ugc_account_reactivated' => self::UGC,
        'ugc_face_suspended' => self::UGC,

        // Face subscription
        'face_subscription_activated' => self::SUBSCRIPTION,
        'face_subscription_expired' => self::SUBSCRIPTION,
        'face_subscription_cancelled' => self::SUBSCRIPTION,
        'face_subscription_renewal_reminder_7d' => self::REMINDER,
    ];

    /**
     * Types deliberately kept in-app only: type => reason.
     *
     * @var array<string, string>
     */
    public const EXCLUDED = [
        'booking_rating_received' => 'Purement informatif, aucune action ni argent en jeu.',
        'candidature_reset_from_rejected' => 'La Face n\'avait pas été retenue : le retour en attente n\'appelle aucune action.',
        'face_subscription_renewal_reminder_30d' => 'Rappel trop anticipé, déjà couvert par l\'e-mail et la notification in-app.',
    ];

    public static function titleFor(string $type): ?string
    {
        return self::ALLOWED[$type] ?? null;
    }

    public static function isAllowed(string $type): bool
    {
        return isset(self::ALLOWED[$type]);
    }

    public static function isExcluded(string $type): bool
    {
        return isset(self::EXCLUDED[$type]);
    }
}
