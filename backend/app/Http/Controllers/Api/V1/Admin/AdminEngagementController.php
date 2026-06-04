<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Candidature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Admin "Faces à contacter" — unified, read-only view of the Faces actively
 * engaged on a booking or a mission, so the team can reach out on WhatsApp and
 * push in-flight deals through.
 *
 * Design note — in-memory merge with a per-source cap (self::MAX_PER_SOURCE):
 * active (non-terminal) engagements are bounded because they are live, in-flight
 * deals. Merging two heterogeneous sources (bookings vs candidatures + their
 * mission_payment_candidatures) in memory is simpler and less bug-prone than a
 * SQL UNION across tables with divergent columns, and it sidesteps constraining
 * the `User` morphTo (userable) relation inside whereHas for search. If either
 * source ever approaches the cap, move this to a DB-level union/cursor.
 */
class AdminEngagementController extends Controller
{
    private const PER_PAGE = 20;

    private const MAX_PER_SOURCE = 1000;

    /**
     * Booking statuses that represent an active (non-terminal) engagement.
     */
    private const BOOKING_STATUSES = [
        BookingStatus::Pending,
        BookingStatus::Accepted,
        BookingStatus::Paid,
        BookingStatus::InProgress,
        BookingStatus::ConfirmedByFace,
        BookingStatus::ConfirmedByProducer,
    ];

    /**
     * Candidature statuses that represent an active engagement awaiting / past
     * the Face's action. Raw `pending` candidatures are never exposed.
     */
    private const CANDIDATURE_STATUSES = [
        CandidatureStatus::Accepted,
        CandidatureStatus::Confirmed,
        CandidatureStatus::InProgress,
    ];

    /**
     * List the unified engagement rows (bookings + mission candidatures),
     * filtered, searched, sorted by recency and paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $type = is_string($request->query('type')) ? $request->query('type') : null;
        $statusFilter = $request->filled('status') && is_string($request->query('status'))
            ? $request->query('status')
            : null;
        $search = $request->filled('search') && is_string($request->query('search'))
            ? mb_strtolower(trim($request->query('search')))
            : null;

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect();

        if ($type !== 'mission') {
            $bookings = Booking::with(['face.userable', 'producer.userable'])
                ->whereIn('status', array_map(fn (BookingStatus $s) => $s->value, self::BOOKING_STATUSES))
                ->orderByDesc('updated_at')
                ->limit(self::MAX_PER_SOURCE)
                ->get();

            $rows = $rows->concat($bookings->map(fn (Booking $booking) => $this->mapBooking($booking)));
        }

        if ($type !== 'booking') {
            $candidatures = Candidature::with(['face', 'mission.producer', 'paymentEntry'])
                ->whereIn('status', array_map(fn (CandidatureStatus $s) => $s->value, self::CANDIDATURE_STATUSES))
                ->orderByDesc('updated_at')
                ->limit(self::MAX_PER_SOURCE)
                ->get();

            $rows = $rows->concat($candidatures->map(fn (Candidature $candidature) => $this->mapCandidature($candidature)));
        }

        if ($statusFilter !== null) {
            $rows = $rows->filter(fn (array $row) => $row['status'] === $statusFilter);
        }

        if ($search !== null && $search !== '') {
            $rows = $rows->filter(function (array $row) use ($search): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $row['face']['display_name'] ?? '',
                    $row['face']['whatsapp_number'] ?? '',
                    $row['producer']['display_name'] ?? '',
                ])));

                return str_contains($haystack, $search);
            });
        }

        $rows = $rows->sortByDesc('engaged_since')->values();

        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $lastPage);
        $items = $rows->forPage($page, self::PER_PAGE)->values();

        return response()->json([
            'data' => $items->all(),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => self::PER_PAGE,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Normalize a booking into an engagement row.
     *
     * Booking.face is a User; the Face profile (with whatsapp_number) is behind
     * its morphTo userable. Same hop for the producer.
     *
     * @return array<string, mixed>
     */
    private function mapBooking(Booking $booking): array
    {
        $faceProfile = $booking->face?->userable;
        $producerProfile = $booking->producer?->userable;
        $whatsapp = $faceProfile?->whatsapp_number;
        $faceName = $faceProfile?->display_name;
        $producerName = $producerProfile?->display_name;

        return [
            'id' => "booking:{$booking->uuid}",
            'type' => 'booking',
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'engaged_since' => ($booking->updated_at ?? $booking->created_at)?->toISOString(),
            'montant_face_recoit' => $booking->montant_face_recoit,
            'face' => [
                'id' => $faceProfile?->uuid,
                'display_name' => $faceName ?? '—',
                'whatsapp_number' => $whatsapp,
                'has_whatsapp' => $this->hasDialableWhatsapp($whatsapp),
            ],
            'producer' => [
                'display_name' => $producerName ?? '—',
            ],
            'objet' => [
                'label' => $booking->type_contenu,
                'date' => $booking->date_debut->toISOString(),
                'detail_id' => null,
            ],
        ];
    }

    /**
     * Normalize a mission candidature into an engagement row.
     *
     * Candidature.face is a Face directly. The per-Face amount lives on the
     * paid escrow entry (paymentEntry), not on the candidature. Mission.producer
     * is a Producer directly (no userable hop).
     *
     * @return array<string, mixed>
     */
    private function mapCandidature(Candidature $candidature): array
    {
        $face = $candidature->face;
        $mission = $candidature->mission;
        $whatsapp = $face?->whatsapp_number;
        $faceName = $face?->display_name;
        $producerName = $mission?->producer?->display_name;

        return [
            'id' => "mission:{$candidature->uuid}",
            'type' => 'mission',
            'status' => $candidature->status->value,
            'status_label' => $candidature->status->label(),
            'engaged_since' => ($candidature->updated_at ?? $candidature->created_at)?->toISOString(),
            'montant_face_recoit' => $candidature->paymentEntry?->montant_face_recoit,
            'face' => [
                'id' => $face?->uuid,
                'display_name' => $faceName ?? '—',
                'whatsapp_number' => $whatsapp,
                'has_whatsapp' => $this->hasDialableWhatsapp($whatsapp),
            ],
            'producer' => [
                'display_name' => $producerName ?? '—',
            ],
            'objet' => [
                'label' => $mission?->titre,
                'date' => $mission?->date_tournage?->toISOString(),
                'detail_id' => $mission?->uuid,
            ],
        ];
    }

    private function hasDialableWhatsapp(?string $whatsapp): bool
    {
        if ($whatsapp === null) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $whatsapp) ?? '';

        return $digits !== '';
    }
}
