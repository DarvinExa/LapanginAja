<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class BookingController extends Controller
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * List bookings scoped to tenant with filters & pagination.
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'court_id' => 'nullable|integer',
            'status' => 'nullable|string',
            'payment_status' => 'nullable|string',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);

        $query = Booking::query();

        if ($request->filled('court_id')) {
            $query->where('court_id', $request->court_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('start_time', '<=', $request->end_date);
        }

        $bookings = $query->orderBy('start_time', 'desc')->paginate(15);

        return response()->json([
            'data' => BookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    /**
     * Store manual walk-in booking.
     */
    public function storeWalkIn(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'court_id' => 'required|exists:courts,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'customer_email' => 'required|email|max:255',
            'start_time' => 'required|date_format:Y-m-d H:i:s',
            'end_time' => 'nullable|date_format:Y-m-d H:i:s|after:start_time',
            'notes' => 'nullable|string',
        ]);

        $booking = $this->bookingService->createWalkInBooking($data);

        return response()->json([
            'message' => 'Booking walk-in berhasil dibuat.',
            'booking' => new BookingResource($booking),
        ], 201);
    }

    /**
     * Update booking status (e.g. completed, cancelled, confirmed).
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', new Enum(BookingStatus::class)],
        ]);

        $booking = Booking::findOrFail($id);

        $booking->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Status booking berhasil diperbarui.',
            'booking' => new BookingResource($booking),
        ]);
    }
}
