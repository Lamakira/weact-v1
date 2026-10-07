<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingCancellationReason;
use App\Enums\BookingStatus;
use App\Enums\DisputeResolutionOutcome;
use App\Enums\EscrowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveBookingDisputeRequest;
use App\Models\Admin;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBookingDisputeController extends Controller
{
    /** Au-delà de ce délai après date_fin, un booking payé sans suite est listé en lecture seule. */
    private const STALE_PAID_AFTER_DAYS = 7;

    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    /**
     * Litiges ouverts par la Face (à trancher) + bookings payés sans suite (lecture seule).
     */
    public function index(Request $request): JsonResponse
    {
        $disputes = Booking::query()
            ->whereIn('status', [BookingStatus::NoShow->value, BookingStatus::CancelledByProducer->value])
            ->whereNotNull('settlement_due_at')
            ->whereNotNull('disputed_at')
            ->whereNull('dispute_resolved_at')
            ->whereHas('escrowTransaction', fn ($query) => $query->where('status', EscrowStatus::Locked->value))
            ->with(['face.userable', 'producer.userable'])
            ->orderBy('disputed_at')
            ->get();

        $stalePaid = Booking::query()
            ->where('status', BookingStatus::Paid->value)
            ->whereRaw("BINARY type_contenu != 'UGC'")
            ->whereNotNull('date_fin')
            ->where('date_fin', '<', now()->subDays(self::STALE_PAID_AFTER_DAYS))
            ->with(['face.userable', 'producer.userable'])
            ->orderBy('date_fin')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => [
                'disputes' => $disputes->map(fn (Booking $booking): array => $this->disputeItem($booking))->values(),
                'stale_paid' => $stalePaid->map(fn (Booking $booking): array => $this->stalePaidItem($booking))->values(),
            ],
            'message' => 'Litiges récupérés avec succès',
        ]);
    }

    public function resolve(ResolveBookingDisputeRequest $request, Booking $booking): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $outcome = DisputeResolutionOutcome::from($request->validated('outcome'));
        $notes = trim((string) $request->validated('notes'));

        $resolved = $this->bookingService->settleDispute($booking, $outcome, $admin, $notes);
        $resolved?->loadMissing(['face.userable', 'producer.userable']);

        return response()->json([
            'data' => $resolved === null ? null : $this->disputeItem($resolved),
            'message' => 'Litige résolu avec succès',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function disputeItem(Booking $booking): array
    {
        return [
            'id' => $booking->uuid,
            'status' => $booking->status->value,
            'face' => ['display_name' => (string) data_get($booking, 'face.userable.display_name', 'Face')],
            'producer' => ['display_name' => (string) data_get($booking, 'producer.userable.display_name', 'Producteur')],
            'date_debut' => $booking->date_debut?->toIso8601String(),
            'date_fin' => $booking->date_fin?->toIso8601String(),
            'montant_total_producteur' => $booking->montant_total_producteur,
            'montant_face_recoit' => $booking->montant_face_recoit,
            // Pas de colonne dédiée : la fenêtre est ouverte pour 72 h au signalement / à l'annulation.
            'reported_at' => $booking->settlement_due_at?->copy()->subHours(BookingService::DISPUTE_WINDOW_HOURS)->toIso8601String(),
            'settlement_due_at' => $booking->settlement_due_at?->toIso8601String(),
            'disputed_at' => $booking->disputed_at?->toIso8601String(),
            'dispute_message' => $booking->dispute_message,
            'cancellation_reason' => $this->cancellationReason($booking),
            'dispute_outcome' => $booking->dispute_outcome,
            'dispute_resolved_at' => $booking->dispute_resolved_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stalePaidItem(Booking $booking): array
    {
        return [
            'id' => $booking->uuid,
            'face' => ['display_name' => (string) data_get($booking, 'face.userable.display_name', 'Face')],
            'producer' => ['display_name' => (string) data_get($booking, 'producer.userable.display_name', 'Producteur')],
            'date_debut' => $booking->date_debut?->toIso8601String(),
            'date_fin' => $booking->date_fin?->toIso8601String(),
            'montant_total_producteur' => $booking->montant_total_producteur,
            'montant_face_recoit' => $booking->montant_face_recoit,
            'days_since_date_fin' => (int) $booking->date_fin?->copy()->startOfDay()->diffInDays(now()->startOfDay()),
        ];
    }

    private function cancellationReason(Booking $booking): ?string
    {
        if ($booking->cancellation_reason === null) {
            return null;
        }

        $label = BookingCancellationReason::tryFrom($booking->cancellation_reason)?->label() ?? $booking->cancellation_reason;
        $custom = trim((string) $booking->custom_cancellation_reason);

        return $custom !== '' ? "{$label} : {$custom}" : $label;
    }
}
