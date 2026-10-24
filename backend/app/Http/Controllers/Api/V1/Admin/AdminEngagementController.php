<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Enums\CandidatureStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Candidature;
use App\Services\Messaging\FaceResponsiveness;
use App\Support\Whatsapp;
use Carbon\CarbonInterface;
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
 *
 * Unanswered Faces: each row carries `unanswered_since` (set only when the Face has
 * not answered the Producer for 12 h, 2 h when the shoot is near — rules in
 * FaceResponsiveness, computed in bulk with one aggregated query per source),
 * `shoot_starts_at` and `shoot_soon`. Unanswered rows are sorted first (nearest shoot,
 * then oldest wait); the `type=unanswered` filter keeps only them; `meta.unanswered_count`
 * is computed before the type / status / search filters.
 */
class AdminEngagementController extends Controller
{
    private const PER_PAGE = 20;

    private const MAX_PER_SOURCE = 1000;

    public function __construct(private readonly FaceResponsiveness $responsiveness) {}

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

        // Les deux sources sont toujours chargées : le compteur « sans réponse » ignore le filtre de type
        $bookings = Booking::with(['face.userable', 'producer.userable'])
            ->whereIn('status', array_map(fn (BookingStatus $s) => $s->value, self::BOOKING_STATUSES))
            ->orderByDesc('updated_at')
            ->limit(self::MAX_PER_SOURCE)
            ->get();

        $candidatures = Candidature::with(['face', 'mission.producer', 'paymentEntry'])
            ->whereIn('status', array_map(fn (CandidatureStatus $s) => $s->value, self::CANDIDATURE_STATUSES))
            ->orderByDesc('updated_at')
            ->limit(self::MAX_PER_SOURCE)
            ->get();

        $pendingByBooking = $this->responsiveness->pendingSinceForBookings($bookings->pluck('id')->all());
        $pendingByCandidature = $this->responsiveness->pendingSinceForCandidatures($candidatures->pluck('id')->all());

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $bookings
            ->map(fn (Booking $booking) => $this->mapBooking($booking, $pendingByBooking[$booking->id] ?? null))
            ->concat($candidatures->map(fn (Candidature $candidature) => $this->mapCandidature($candidature, $pendingByCandidature[$candidature->id] ?? null)));

        $unansweredCount = $rows->filter(fn (array $row) => $row['unanswered_since'] !== null)->count();

        if ($type === 'unanswered') {
            $rows = $rows->filter(fn (array $row) => $row['unanswered_since'] !== null);
        } elseif ($type === 'booking' || $type === 'mission') {
            $rows = $rows->filter(fn (array $row) => $row['type'] === $type);
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

        // Sans réponse d'abord (tournage le plus proche, puis attente la plus ancienne), le reste par engagement récent.
        // Les dates ISO UTC de même format se comparent comme des chaînes ; null (pas de tournage) en dernier.
        $unanswered = $rows->filter(fn (array $row) => $row['unanswered_since'] !== null)
            ->sort(fn (array $a, array $b) => [$a['shoot_starts_at'] === null, $a['shoot_starts_at'], $a['unanswered_since']]
                <=> [$b['shoot_starts_at'] === null, $b['shoot_starts_at'], $b['unanswered_since']]);
        $others = $rows->filter(fn (array $row) => $row['unanswered_since'] === null)->sortByDesc('engaged_since');
        $rows = $unanswered->concat($others)->values();

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
                'unanswered_count' => $unansweredCount,
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
    private function mapBooking(Booking $booking, ?CarbonInterface $pendingSince): array
    {
        $faceProfile = $booking->face?->userable;
        $producerProfile = $booking->producer?->userable;
        $whatsapp = $faceProfile?->whatsapp_number;
        $faceName = $faceProfile?->display_name;
        $producerName = $producerProfile?->display_name;

        return $this->withResponsiveness([
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
                'date' => $booking->date_debut?->toISOString(),
                'detail_id' => null,
            ],
        ], $booking->shootingDayStartsAt(), $pendingSince);
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
    private function mapCandidature(Candidature $candidature, ?CarbonInterface $pendingSince): array
    {
        $face = $candidature->face;
        $mission = $candidature->mission;
        $whatsapp = $face?->whatsapp_number;
        $faceName = $face?->display_name;
        $producerName = $mission?->producer?->display_name;

        return $this->withResponsiveness([
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
        ], $mission?->shootingDayStartsAt(), $pendingSince);
    }

    /**
     * Ajoute à une ligne les champs de délai de réponse : `unanswered_since` n'est renseigné
     * que si la Face est en retard (12 h, 2 h si le tournage est proche).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withResponsiveness(array $row, ?CarbonInterface $shootStart, ?CarbonInterface $pendingSince): array
    {
        $urgent = $this->responsiveness->isUrgent($shootStart);

        return $row + [
            'unanswered_since' => $this->responsiveness->isOverdue($pendingSince, $urgent) ? $pendingSince?->toISOString() : null,
            'shoot_starts_at' => $shootStart?->toISOString(),
            'shoot_soon' => $urgent,
        ];
    }

    private function hasDialableWhatsapp(?string $whatsapp): bool
    {
        return Whatsapp::isDialable($whatsapp);
    }
}
