<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for which in-app notification types also trigger a WhatsApp alert.
 *
 * Every `App\Models\Notification` type emitted in app/ must be either in ALLOWED
 * (type => [template, recipient role]) or in EXCLUDED (type => reason). A test scans app/
 * so that a new type forces an explicit decision here (même garde-fou que PushAllowlist).
 */
final class WhatsappAllowlist
{
    public const FACE = 'Face';

    public const PRODUCER = 'Producer';

    public const NOT_IN_SCOPE = 'Hors périmètre : aucune alerte WhatsApp pour ce type (notification in-app, push et e-mail suffisent).';

    /**
     * type => [template, rôle du destinataire]. Le rôle filtre les types émis vers les deux parties
     * (`booking_paid` : WhatsApp pour la Face seulement, jamais pour le Producteur).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const ALLOWED = [
        'booking_received' => ['weact_booking_request', self::FACE],
        'booking_response_reminder' => ['weact_booking_reminder', self::FACE],
        'booking_paid' => ['weact_payment_secured', self::FACE],
        'ugc_commission_paid' => ['weact_payment_secured', self::FACE],
        // Seulement quand la place est payée (marqueur `place_paid` posé par MissionPaymentService).
        'candidature_accepted' => ['weact_mission_place_paid', self::FACE],
        'booking_accepted' => ['weact_booking_accepted', self::PRODUCER],
        'booking_refused' => ['weact_booking_declined', self::PRODUCER],
        // Groupée : au plus un message par mission et par Producteur toutes les 6 h.
        'new_candidature' => ['weact_new_candidatures', self::PRODUCER],
    ];

    /**
     * Types deliberately kept off WhatsApp: type => reason.
     *
     * @var array<string, string>
     */
    public const EXCLUDED = [
        'booking_cancelled' => self::NOT_IN_SCOPE,
        'booking_expired' => self::NOT_IN_SCOPE,
        'booking_no_show' => self::NOT_IN_SCOPE,
        'booking_dispute_opened' => self::NOT_IN_SCOPE,
        'booking_dispute_settled' => self::NOT_IN_SCOPE,
        'booking_confirmation_pending' => self::NOT_IN_SCOPE,
        'booking_completed' => self::NOT_IN_SCOPE,
        'booking_wallet_credited' => self::NOT_IN_SCOPE,
        'mission_payment_confirmed' => self::NOT_IN_SCOPE,
        'mission_completed' => self::NOT_IN_SCOPE,
        'mission_candidature_refunded' => self::NOT_IN_SCOPE,
        'mission_face_confirmation_expired' => self::NOT_IN_SCOPE,
        'mission_candidature_payment_failed' => self::NOT_IN_SCOPE,
        'mission_detached_payment_credited' => self::NOT_IN_SCOPE,
        'ugc_commission_refunded' => self::NOT_IN_SCOPE,
        'booking_payment_reminder' => self::NOT_IN_SCOPE,
        'mission_payment_reminder' => self::NOT_IN_SCOPE,
        'mission_confirmation_reminder' => self::NOT_IN_SCOPE,
        'booking_completion_reminder' => self::NOT_IN_SCOPE,
        'shooting_day_reminder' => self::NOT_IN_SCOPE,
        'ugc_deliverable_deadline_approaching' => self::NOT_IN_SCOPE,
        'candidature_rejected' => self::NOT_IN_SCOPE,
        'face_confirmed_participation' => self::NOT_IN_SCOPE,
        'mission_attendance_absent' => self::NOT_IN_SCOPE,
        'mission_attendance_disputed' => self::NOT_IN_SCOPE,
        'mission_attendance_dispute_resolved' => self::NOT_IN_SCOPE,
        'mission_deleted_candidature_cancelled' => self::NOT_IN_SCOPE,
        'mission_selection_reset_producer' => self::NOT_IN_SCOPE,
        'mission_selection_reset' => self::NOT_IN_SCOPE,
        'candidature_reset_from_accepted' => self::NOT_IN_SCOPE,
        'mission_completed_producer' => self::NOT_IN_SCOPE,
        'mission_closed_pending_candidature' => self::NOT_IN_SCOPE,
        'mission_participation_confirmation_required' => self::NOT_IN_SCOPE,
        'mission_candidature_slot_released' => self::NOT_IN_SCOPE,
        'candidature_expired_no_response' => self::NOT_IN_SCOPE,
        'mission_place_freed' => self::NOT_IN_SCOPE,
        'mission_place_freed_selected' => self::NOT_IN_SCOPE,
        'mission_freed_place_refunded' => self::NOT_IN_SCOPE,
        'mission_all_places_refunded' => self::NOT_IN_SCOPE,
        'mission_candidatures_response_reminder' => self::NOT_IN_SCOPE,
        'mission_confirmation_expired' => self::NOT_IN_SCOPE,
        'ugc_deal_accepted' => self::NOT_IN_SCOPE,
        'ugc_shipment_confirmed' => self::NOT_IN_SCOPE,
        'ugc_product_received' => self::NOT_IN_SCOPE,
        'ugc_deliverable_uploaded' => self::NOT_IN_SCOPE,
        'ugc_deliverable_validated' => self::NOT_IN_SCOPE,
        'ugc_deliverable_rejected' => self::NOT_IN_SCOPE,
        'ugc_deliverable_retouche_requested' => self::NOT_IN_SCOPE,
        'ugc_account_suspended' => self::NOT_IN_SCOPE,
        'ugc_account_reactivated' => self::NOT_IN_SCOPE,
        'ugc_face_suspended' => self::NOT_IN_SCOPE,
        'face_subscription_activated' => self::NOT_IN_SCOPE,
        'face_subscription_expired' => self::NOT_IN_SCOPE,
        'face_subscription_cancelled' => self::NOT_IN_SCOPE,
        'face_subscription_renewal_reminder_7d' => self::NOT_IN_SCOPE,
        'booking_rating_received' => self::NOT_IN_SCOPE,
        'candidature_reset_from_rejected' => self::NOT_IN_SCOPE,
        'face_subscription_renewal_reminder_30d' => self::NOT_IN_SCOPE,
    ];

    public static function templateFor(string $type): ?string
    {
        return self::ALLOWED[$type][0] ?? null;
    }

    public static function roleFor(string $type): ?string
    {
        return self::ALLOWED[$type][1] ?? null;
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
