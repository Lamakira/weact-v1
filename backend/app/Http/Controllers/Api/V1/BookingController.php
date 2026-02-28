<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\AcceptBookingRequest;
use App\Http\Requests\Booking\CreateBookingRequest;
use App\Http\Requests\Booking\RefuseBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    /**
     * Status filter group mapping.
     *
     * @var array<string, list<BookingStatus>>
     */
    private const STATUS_FILTER_MAP = [
        'pending' => [BookingStatus::Pending],
        'active' => [
            BookingStatus::Accepted,
            BookingStatus::Paid,
            BookingStatus::InProgress,
            BookingStatus::ConfirmedByFace,
            BookingStatus::ConfirmedByProducer,
        ],
        'completed' => [BookingStatus::Completed],
        'cancelled' => [
            BookingStatus::Refused,
            BookingStatus::CancelledByProducer,
            BookingStatus::CancelledByFace,
        ],
    ];

    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    /**
     * List authenticated user's bookings with optional status filter.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Booking::class);

        $user = $request->user();

        $query = Booking::with(['face.userable', 'producer.userable'])
            ->where(function ($q) use ($user) {
                $q->where('face_id', $user->id)
                    ->orWhere('producer_id', $user->id);
            })
            ->orderBy('updated_at', 'desc');

        // Apply status filter group
        $statusFilter = $request->query('status');
        if ($statusFilter && isset(self::STATUS_FILTER_MAP[$statusFilter])) {
            $query->whereIn('status', self::STATUS_FILTER_MAP[$statusFilter]);
        }

        return BookingResource::collection($query->paginate(15));
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
            'data' => new BookingResource($booking->load(['face', 'producer'])),
            'message' => 'Demande de booking envoyee',
        ], 201);
    }

    /**
     * Display the specified booking.
     */
    public function show(Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $booking->load(['face.userable', 'producer.userable']);

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
        Gate::authorize('accept', $booking);

        $booking = $this->bookingService->accept($booking);

        return response()->json([
            'data' => new BookingResource($booking->load(['face.userable', 'producer.userable'])),
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
            'data' => new BookingResource($booking->load(['face.userable', 'producer.userable'])),
            'message' => 'Booking refusé',
        ]);
    }
}
