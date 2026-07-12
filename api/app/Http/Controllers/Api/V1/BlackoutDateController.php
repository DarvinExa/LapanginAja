<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlackoutDateRequest;
use App\Http\Resources\BlackoutDateResource;
use App\Models\BlackoutDate;
use App\Models\Court;
use Illuminate\Http\JsonResponse;

class BlackoutDateController extends Controller
{
    /**
     * Display a listing of the blackout dates for a court.
     */
    public function index(string $courtId): JsonResponse
    {
        $court = Court::findOrFail($courtId);
        $blackoutDates = $court->blackoutDates()->orderBy('date')->get();

        return response()->json([
            'blackout_dates' => BlackoutDateResource::collection($blackoutDates),
        ]);
    }

    /**
     * Store a newly created blackout date in storage.
     */
    public function store(StoreBlackoutDateRequest $request, string $courtId): JsonResponse
    {
        $court = Court::findOrFail($courtId);

        $blackoutDate = $court->blackoutDates()->create($request->validated());

        return response()->json([
            'blackout_date' => new BlackoutDateResource($blackoutDate),
        ], 201);
    }

    /**
     * Remove the specified blackout date from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $blackoutDate = BlackoutDate::findOrFail($id);
        $blackoutDate->delete();

        return response()->json([
            'message' => 'Tanggal blackout berhasil dihapus.',
        ]);
    }
}
