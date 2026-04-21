<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canonical API error codes (FIX-22.2).
 *
 * Convention for NEW codes: UPPER_SNAKE_CASE.
 *
 * Legacy codes already used in production keep their historical casing as
 * the enum value (lowercase `gender_mismatch`, `active_missions`,
 * `no_user_account`, `self_demotion`, `self_deletion`, `registration_disabled`)
 * so that `ErrorCodes::X->value` is a faithful mirror of what the wire emits
 * and of the ~50+ existing test assertions. Renaming them is out of scope
 * (follow-up ticket). The PHP case names remain PascalCase for ergonomics.
 *
 * Use the instance helper ->envelope($message, $extra = []) to build the
 * standardized payload at controller call-sites:
 *
 *     return response()->json(
 *         ErrorCodes::Forbidden->envelope('Utilisateur non autorisé'),
 *         403
 *     );
 *
 * Adoption is OPT-IN: existing conforming controllers are not forced to
 * migrate. Only the controllers listed in AC #1–#6 / #12 of FIX-22.2 are
 * refactored in this story.
 */
enum ErrorCodes: string
{
    // --- Auth / access ---
    case AuthFailed = 'AUTH_FAILED';
    case AccountDeactivated = 'ACCOUNT_DEACTIVATED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Unauthorized = 'UNAUTHORIZED';
    case Forbidden = 'FORBIDDEN';
    case InvalidPassword = 'INVALID_PASSWORD';
    case InvalidToken = 'INVALID_TOKEN';

    // --- Throttling / rate limits ---
    case Throttled = 'THROTTLED';

    // --- Resource lookup ---
    case NotFound = 'NOT_FOUND';
    case UserNotFound = 'USER_NOT_FOUND';
    case FaceNotFound = 'FACE_NOT_FOUND';
    case MissionNotFound = 'MISSION_NOT_FOUND';
    case ArticleNotFound = 'ARTICLE_NOT_FOUND';
    case ReportNotFound = 'REPORT_NOT_FOUND';

    // --- Business-rule status ---
    case InvalidStatus = 'INVALID_STATUS';
    case MissionClosed = 'MISSION_CLOSED';
    case AlreadyApplied = 'ALREADY_APPLIED';
    case GenderMismatch = 'gender_mismatch';
    case PaymentNotConfirmed = 'PAYMENT_NOT_CONFIRMED';
    case NotInFinalSelection = 'NOT_IN_FINAL_SELECTION';
    case ChatLocked = 'CHAT_LOCKED';
    case AlreadyRated = 'ALREADY_RATED';
    case RatingNotAllowed = 'RATING_NOT_ALLOWED';
    case BookingRatingNotAllowed = 'BOOKING_RATING_NOT_ALLOWED';
    case ReportDuplicate = 'REPORT_DUPLICATE';

    // --- Email / confirmation ---
    case EmailAlreadyTaken = 'EMAIL_ALREADY_TAKEN';
    case NoPendingEmailChange = 'NO_PENDING_EMAIL_CHANGE';
    case InvalidConfirmationLink = 'INVALID_CONFIRMATION_LINK';
    case ConfirmationLinkExpired = 'CONFIRMATION_LINK_EXPIRED';
    case InvalidVerificationLink = 'INVALID_VERIFICATION_LINK';
    case VerificationLinkExpired = 'VERIFICATION_LINK_EXPIRED';
    case EmailNotVerified = 'EMAIL_NOT_VERIFIED';
    case SendFailed = 'SEND_FAILED';

    // --- Registration toggle (FIX-23 / FIX-3.1) ---
    case RegistrationDisabled = 'registration_disabled';

    // --- Admin self-service guards ---
    case SelfDemotion = 'self_demotion';
    case SelfDeletion = 'self_deletion';
    case NoUserAccount = 'no_user_account';
    case ActiveMissions = 'active_missions';
    case ActiveCandidatures = 'ACTIVE_CANDIDATURES';

    // --- Media / portfolio ---
    case AlbumFull = 'ALBUM_FULL';
    case NoVideo = 'NO_VIDEO';
    case ReorderError = 'REORDER_ERROR';

    // --- New codes introduced by FIX-22.2 ---
    case ValidationError = 'VALIDATION_ERROR';
    case WithdrawalLock = 'WITHDRAWAL_LOCK';
    case InsufficientBalance = 'INSUFFICIENT_BALANCE';
    case WithdrawalFailed = 'WITHDRAWAL_FAILED';
    case PaymentInitiationFailed = 'PAYMENT_INITIATION_FAILED';
    case ContactSendFailed = 'CONTACT_SEND_FAILED';
    case InternalError = 'INTERNAL_ERROR';
    case InvalidSignature = 'INVALID_SIGNATURE';

    // --- HttpException status → code mapping (global handler) ---
    case BadRequest = 'BAD_REQUEST';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case Conflict = 'CONFLICT';
    case Gone = 'GONE';
    case Unprocessable = 'UNPROCESSABLE';
    case HttpError = 'HTTP_ERROR';

    /**
     * Build the standardized error envelope: { error: { message, code, …extra } }.
     *
     * @param  array<string, mixed>  $extra  Additional fields (e.g. `details` for 422).
     * @return array{error: array<string, mixed>}
     */
    public function envelope(string $message, array $extra = []): array
    {
        return [
            'error' => [
                ...$extra,
                'message' => $message,
                'code' => $this->value,
            ],
        ];
    }
}
