<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetAvailabilityRequest;
use App\Http\Resources\CourtResource;
use App\Http\Resources\TenantResource;
use App\Models\Court;
use App\Models\Tenant;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class PublicVenueController extends Controller
{
    protected AvailabilityService $availabilityService;

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Display the public profile of the venue.
     */
    public function show(): JsonResponse
    {
        $tenant = app(Tenant::class);
        $courts = $tenant->courts()->with('operatingHours')->where('is_active', true)->get();

        return response()->json([
            'tenant' => new TenantResource($tenant),
            'courts' => CourtResource::collection($courts),
        ]);
    }

    /**
     * Get availability for a court on a date.
     */
    public function availability(GetAvailabilityRequest $request): JsonResponse
    {
        $court = Court::findOrFail($request->input('court_id'));
        $date = Carbon::parse($request->input('date'));

        $slots = $this->availabilityService->getAvailability($court, $date);

        return response()->json([
            'date' => $request->input('date'),
            'court_id' => (int) $request->input('court_id'),
            'slots' => $slots,
        ]);
    }
}
