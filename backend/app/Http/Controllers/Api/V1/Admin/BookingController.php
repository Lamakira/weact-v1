<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminBookingResource;
use App\Models\Booking;
use App\Support\Sql;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * List every booking with pagination, search, and status filter.
     *
     * Unscoped on purpose: unlike the party-facing BookingController::index
     * (restricted to face_id/producer_id = the current user), an admin sees the
     * whole table. Mirrors Admin\MissionController: raw query params, manual meta.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::with(['face.userable', 'producer.userable', 'escrowTransaction'])
            ->orderBy('updated_at', 'desc');

        // Search by either party's email, or by the booking's lieu / product name.
        // Party names live on the polymorphic userable and are not searched here —
        // email is the canonical admin lookup key.
        if ($request->filled('search') && is_string($request->query('search'))) {
            $search = Sql::escapeLike($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('lieu', 'like', "%{$search}%")
                    ->orWhere('nom_produit', 'like', "%{$search}%")
                    ->orWhereHas('face', function ($fq) use ($search) {
                        $fq->where('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('producer', function ($pq) use ($search) {
                        $pq->where('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by exact status.
        if ($request->filled('status') && is_string($request->query('status'))) {
            $status = BookingStatus::tryFrom($request->query('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        $bookings = $query->paginate(15);

        return response()->json([
            'data' => AdminBookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    /**
     * Show a single booking with full detail (route-model bound by uuid).
     */
    public function show(Booking $booking): JsonResponse
    {
        $booking->load(['face.userable', 'producer.userable', 'escrowTransaction']);

        return response()->json([
            'data' => new AdminBookingResource($booking),
            'message' => 'Réservation récupérée avec succès',
        ]);
    }
}
