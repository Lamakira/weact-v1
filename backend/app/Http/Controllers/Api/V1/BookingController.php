<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\ErrorCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\AcceptBookingRequest;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\ConfirmBookingRequest;
use App\Http\Requests\Booking\ContestBookingRequest;
use App\Http\Requests\Booking\CreateBookingRequest;
use App\Http\Requests\Booking\IndexBookingsRequest;
use App\Http\Requests\Booking\PayBookingRequest;
use App\Http\Requests\Booking\PayUgcCommissionRequest;
use App\Http\Requests\Booking\RefuseBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Face;
use App\Models\User;
use App\Services\BookingService;
use App\Services\FaceEntitlementService;
use App\Services\Ugc\UgcCommissionPaymentService;
use App\Support\LifecycleSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /**
     * Status filter group mapping.
     *
     * @var array<string, list<BookingStatus>>
     */
    private const STATUS_FILTER_MAP = [
        // Lot D7 : une demande UGC dont la commission est réglée attend la réponse de la Face.
        'pending' => [BookingStatus::Pending, BookingStatus::CommissionPaid],
        'active' => [
            BookingStatus::Accepted,
            BookingStatus::Paid,
            BookingStatus::ConfirmedByFace,
            BookingStatus::ConfirmedByProducer,
        ],
        'completed' => [BookingStatus::Completed],
        'cancelled' => [
            BookingStatus::Refused,
            BookingStatus::Expired,
            BookingStatus::CancelledByProducer,
            BookingStatus::CancelledByFace,
            BookingStatus::NoShow,
        ],
    ];

    /** Filtre « À traiter » (lot D7) : pas un groupe de statuts, un scope sur Booking. */
    private const TO_PROCESS_FILTER = 'to_process';

    public function __construct(
        private readonly BookingService $bookingService,
        private readonly UgcCommissionPaymentService $ugcCommissionPaymentService,
        private readonly FaceEntitlementService $entitlement,
    ) {}

    /**
     * List authenticated user's bookings with optional status filter,
     * server-side sort (sort/direction) and page size (per_page).
     */
    public function index(IndexBookingsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Booking::class);

        $user = $request->user();

        $query = Booking::with([...Booking::partiesEagerLoad(), 'shipment.receptionPhotos'])
            // can_rate (policy rate) reads this flag instead of one exists() per completed booking.
            ->withExists(['bookingRatings as viewer_has_rated' => fn ($q) => $q->where('rater_id', $user->id)])
            ->where(function ($q) use ($user) {
                $q->where('face_id', $user->id)
                    ->orWhere('producer_id', $user->id);
            });

        $this->applySort($query, $request, $user);

        // Apply status filter group
        $statusFilter = $request->query('status');
        if ($statusFilter === self::TO_PROCESS_FILTER) {
            // Lot D7 : bookings où l'utilisateur doit agir maintenant (scope Booking::toProcess).
            $query->toProcess($user);
        } elseif ($statusFilter && isset(self::STATUS_FILTER_MAP[$statusFilter])) {
            $statuses = self::STATUS_FILTER_MAP[$statusFilter];

            if ($statusFilter === 'active') {
                // Absence / annulation tardive en fenêtre de contestation : encore « vivant » pour la Face.
                $query->where(function ($q) use ($statuses): void {
                    $q->whereIn('status', $statuses)->orWhere(fn ($p) => $p->pendingSettlement());
                });
            } elseif ($statusFilter === 'cancelled') {
                $query->whereIn('status', $statuses)
                    ->whereNot(fn ($p) => $p->pendingSettlement());
            } else {
                $query->whereIn('status', $statuses);
            }
        }

        $perPage = (int) $request->validated('per_page', IndexBookingsRequest::DEFAULT_PER_PAGE);

        return BookingResource::collection($query->paginate($perPage));
    }

    /**
     * Tri allowlisté (cf. IndexBookingsRequest::SORT_KEYS). `montant` est une clé
     * publique unique : colonne « reçu » pour la Face, « total payé » pour le Producteur.
     * Sans `sort` (lot D7) : « à traiter d'abord » (échéance la plus proche), puis updated_at desc.
     * Un tri explicite reste prioritaire. Tie-breaker `id` toujours ajouté.
     *
     * @param  Builder<Booking>  $query
     */
    private function applySort(Builder $query, IndexBookingsRequest $request, User $user): void
    {
        $viewerIsFace = $user->userable_type === Face::class;
        $sort = $request->validated('sort');
        if ($sort === null) {
            $query->orderToProcessFirst($user)->orderBy('updated_at', 'desc')->orderBy('id', 'desc');

            return;
        }

        $direction = $request->validated('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        if ($sort === 'status') {
            // Ordre de cycle de vie explicite (pas l'ordre alphabétique de la clé anglaise).
            LifecycleSort::apply($query, 'status', BookingStatus::lifecycleOrder(), $direction);
        } else {
            $column = match ($sort) {
                'montant' => $viewerIsFace ? 'montant_face_recoit' : 'montant_total_producteur',
                default => $sort,
            };
            if ($column === 'date_debut') {
                // Bookings UGC sans date de tournage : toujours en fin de liste.
                $query->orderByRaw('`date_debut` IS NULL');
            }
            $query->orderBy($column, $direction);
        }

        $query->orderBy('id', $direction);
    }

    /**
     * Store a newly created booking request.
     */
    public function store(CreateBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'data' => new BookingResource($booking->load(['face', 'producer', 'productPhotos'])),
            'message' => 'Demande de booking envoyee',
        ], 201);
    }

    /**
     * Display the specified booking.
     */
    public function show(Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        // was_paid (timeline) : exists-check unique, jamais calculé sur les listes (pas de N+1).
        $booking->loadExists('escrowTransaction');

        $booking->load([
            ...Booking::partiesEagerLoad(),
            'shipment.receptionPhotos',
            'deliverables',
            'productPhotos',
            'raterBookingRating' => function ($query) {
                $query
                    ->where('rater_id', auth()->id())
                    ->with(['rater.userable', 'rated.userable']);
            },
        ]);

        return response()->json([
            'data' => new BookingResource($booking),
            'message' => 'Détails du booking',
        ]);
    }

    /**
     * Accept a pending booking request (Face only).
     */
    public function accept(AcceptBookingRequest $request, Booking $booking): JsonResponse
    {
        // Lot B10 : échéance dépassée avant le passage du cron → 422 explicite (la policy, qui
        // masque le bouton, répondrait un 403 générique).
        if ($request->user()->id === $booking->face_id
            && $booking->status === BookingStatus::Pending
            && $booking->faceResponseWindowElapsed()) {
            throw ValidationException::withMessages([
                'status' => ['Le délai de réponse est dépassé : cette demande a expiré.'],
            ]);
        }

        Gate::authorize('accept', $booking);

        // Gate FR5 (2.4) : une Face non éligible/suspendue ne peut pas s'engager sur un deal UGC.
        if ($booking->type_contenu === 'UGC'
            && ! $this->entitlement->canAccessUgc($request->user()->userable)) {
            return response()->json(
                ErrorCodes::UgcSubscriptionRequired->envelope(
                    "L'accès aux missions UGC est réservé aux Faces abonnées Pro ou Élite."
                ),
                403
            );
        }

        $booking = $this->bookingService->accept($booking);

        return response()->json([
            'data' => new BookingResource($booking->load(Booking::partiesEagerLoad())),
            'message' => 'Booking accepté',
        ]);
    }

    /**
     * Refuse a booking request (Face only).
     */
    public function refuse(RefuseBookingRequest $request, Booking $booking): JsonResponse
    {
        Gate::authorize('refuse', $booking);

        $booking = $this->bookingService->refuse(
            $booking,
            $request->validated('cancellation_reason')
        );

        return response()->json([
            'data' => new BookingResource($booking->load(Booking::partiesEagerLoad())),
            'message' => 'Booking refusé',
        ]);
    }

    /**
     * Cancel a booking.
     * Producer: pending/accepted/paid
     * Face: accepted/paid
     */
    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $user = $request->user();
        $reason = $request->validated('cancellation_reason');
        $customReason = $request->validated('custom_cancellation_reason');

        if ($user->id === $booking->face_id) {
            Gate::authorize('cancelByFace', $booking);

            $booking = $this->bookingService->cancelByFace($booking, $reason, $customReason);
        } else {
            Gate::authorize('cancel', $booking);

            $booking = $this->bookingService->cancel($booking, $reason, $customReason);
        }

        return response()->json([
            'data' => new BookingResource($booking->load(Booking::partiesEagerLoad())),
            'message' => 'Booking annulé',
        ]);
    }

    /**
     * Confirm booking completion (Face or Producer).
     * First confirmation: transitions to confirmed_by_face or confirmed_by_producer.
     * Second confirmation: completes booking, releases escrow, credits wallet.
     */
    public function confirm(ConfirmBookingRequest $request, Booking $booking): JsonResponse
    {
        Gate::authorize('confirm', $booking);

        $booking = $this->bookingService->confirm($booking, $request->user());

        return response()->json([
            'data' => new BookingResource($booking->load(Booking::partiesEagerLoad())),
            'message' => 'Confirmation enregistrée',
        ]);
    }

    /**
     * Report a Face no-show on a paid booking (Producer only).
     */
    public function reportNoShow(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('reportNoShow', $booking);

        $booking = $this->bookingService->reportNoShow($booking, $request->user());

        return response()->json([
            'data' => new BookingResource($booking->load(Booking::partiesEagerLoad())),
            'message' => 'Absence signalée',
        ]);
    }

    /**
     * Contest a no-show report / late Producer cancellation within the 72 h window (Face only).
     */
    public function contest(ContestBookingRequest $request, Booking $booking): JsonResponse
    {
        Gate::authorize('contest', $booking);

        $booking = $this->bookingService->contest($booking, $request->user(), $request->validated('message'));

        return response()->json([
            'data' => new BookingResource($booking->load(Booking::partiesEagerLoad())),
            'message' => 'Contestation enregistrée',
        ]);
    }

    /**
     * Initiate payment for an accepted booking (Producer only).
     */
    public function pay(PayBookingRequest $request, Booking $booking): JsonResponse
    {
        Gate::authorize('pay', $booking);

        $result = $this->bookingService->initiatePayment($booking);

        return response()->json([
            'data' => new BookingResource($result['booking']->load(Booking::partiesEagerLoad())),
            'checkout_url' => $result['checkout_url'],
            'message' => 'Paiement initié',
        ]);
    }

    /**
     * Check Fedapay transaction status and process payment if approved.
     * Used as fallback polling when webhook delivery is unreliable (e.g. sandbox).
     */
    public function checkPaymentStatus(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $booking = $this->bookingService->checkAndProcessPayment($booking);

        return response()->json([
            'data' => new BookingResource($booking->load(Booking::partiesEagerLoad())),
        ]);
    }

    /**
     * Initiate payment of the WeAct commission for a pending UGC booking (Producer only).
     * Charges `commission_ugc` only — no escrow (D-1.5.a/b).
     */
    public function payCommission(PayUgcCommissionRequest $request, Booking $booking): JsonResponse
    {
        $result = $this->ugcCommissionPaymentService->initiateForBooking($booking);

        return response()->json([
            'data' => new BookingResource($result['booking']->load(Booking::partiesEagerLoad())),
            'checkout_url' => $result['checkout_url'],
            'message' => 'Paiement de la commission initié',
        ]);
    }

    /**
     * Poll Fedapay and settle the UGC commission if approved (fallback when the
     * webhook is delayed). Idempotent.
     */
    public function commissionStatus(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $booking = $this->ugcCommissionPaymentService->checkAndProcessBooking($booking);

        return response()->json([
            'data' => new BookingResource($booking->load(Booking::partiesEagerLoad())),
            'commission_payment_status' => $this->ugcCommissionPaymentService->lastCommissionPaymentStatus(),
        ]);
    }
}
