<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetOperatingHoursRequest;
use App\Http\Requests\StoreCourtRequest;
use App\Http\Requests\UpdateCourtRequest;
use App\Http\Resources\CourtResource;
use App\Models\Court;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CourtController extends Controller
{
    /**
     * Display a listing of the courts.
     */
    public function index(): JsonResponse
    {
        $courts = Court::paginate(15);

        return response()->json(
            CourtResource::collection($courts)->response()->getData(true)
        );
    }

    /**
     * Store a newly created court in storage.
     */
    public function store(StoreCourtRequest $request): JsonResponse
    {
        $court = Court::create($request->validated());

        return response()->json([
            'court' => new CourtResource($court),
        ], 201);
    }

    /**
     * Display the specified court.
     */
    public function show(string $id): JsonResponse
    {
        $court = Court::findOrFail($id);

        return response()->json([
            'court' => new CourtResource($court),
        ]);
    }

    /**
     * Update the specified court in storage.
     */
    public function update(UpdateCourtRequest $request, string $id): JsonResponse
    {
        $court = Court::findOrFail($id);
        $court->update($request->validated());

        return response()->json([
            'court' => new CourtResource($court),
        ]);
    }

    /**
     * Remove the specified court (soft deactivate).
     */
    public function destroy(string $id): JsonResponse
    {
        $court = Court::findOrFail($id);
        $court->update(['is_active' => false]);

        return response()->json([
            'message' => 'Lapangan berhasil dinonaktifkan.',
        ]);
    }

    /**
     * Set operating hours for the specified court.
     */
    public function setOperatingHours(SetOperatingHoursRequest $request, string $id): JsonResponse
    {
        $court = Court::findOrFail($id);

        DB::transaction(function () use ($court, $request) {
            $court->operatingHours()->delete();

            foreach ($request->validated()['hours'] as $hourData) {
                $court->operatingHours()->create([
                    'day_of_week' => $hourData['day_of_week'],
                    'open_time' => $hourData['open_time'] ?? '08:00',
                    'close_time' => $hourData['close_time'] ?? '22:00',
                    'is_closed' => $hourData['is_closed'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Jadwal operasional lapangan berhasil diperbarui.',
        ]);
    }
}
