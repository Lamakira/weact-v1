<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CreateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

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
}
