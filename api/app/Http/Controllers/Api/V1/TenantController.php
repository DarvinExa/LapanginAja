<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantMemberRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TenantMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TenantController extends Controller
{
    /**
     * Onboard a new tenant.
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthorized.');
        }

        $tenant = Tenant::create([
            'owner_id' => $user->id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'address' => $validated['address'],
            'phone' => $validated['phone'],
            'timezone' => $validated['timezone'] ?? 'Asia/Makassar',
        ]);

        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        return response()->json([
            'tenant' => new TenantResource($tenant),
        ], 201);
    }

    /**
     * Display the specified tenant.
     */
    public function show(string $id): JsonResponse
    {
        $tenant = app(Tenant::class);

        return response()->json([
            'tenant' => new TenantResource($tenant),
        ]);
    }

    /**
     * Update the specified tenant settings.
     */
    public function update(UpdateTenantRequest $request, string $id): JsonResponse
    {
        $tenant = app(Tenant::class);

        $tenant->update($request->validated());

        return response()->json([
            'tenant' => new TenantResource($tenant),
        ]);
    }

    /**
     * Get statistics for the venue (occupancy and revenue).
     */
    public function stats(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $tenant = app(Tenant::class);

        $startDateStr = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDateStr = $request->input('end_date', now()->format('Y-m-d'));

        $startDate = Carbon::parse($startDateStr)->startOfDay();
        $endDate = Carbon::parse($endDateStr)->endOfDay();

        $startUtc = $startDate->copy()->utc();
        $endUtc = $endDate->copy()->utc();

        $revenue = Booking::where('tenant_id', $tenant->id)
            ->whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::COMPLETED])
            ->where('payment_status', PaymentStatus::PAID)
            ->where('start_time', '>=', $startUtc)
            ->where('start_time', '<=', $endUtc)
            ->sum('price');

        $bookedMinutes = Booking::where('tenant_id', $tenant->id)
            ->whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::COMPLETED])
            ->where('start_time', '>=', $startUtc)
            ->where('start_time', '<=', $endUtc)
            ->get()
            ->sum(function ($b) {
                $start = $b->start_time;
                $end = $b->end_time;
                if ($start instanceof \DateTimeInterface && $end instanceof \DateTimeInterface) {
                    return (new Carbon($start))->diffInMinutes(new Carbon($end));
                }
                return 0;
            });

        $courts = $tenant->courts()->where('is_active', true)->get();
        $courtsCount = $courts->count();
        $totalOperatingMinutes = 0;

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dayOfWeek = $currentDate->dayOfWeek;

            foreach ($courts as $court) {
                /** @var \App\Models\Court $court */
                /** @var \App\Models\OperatingHour|null $opHour */
                $opHour = $court->operatingHours()->where('day_of_week', $dayOfWeek)->first();

                if ($opHour && ! $opHour->is_closed) {
                    $open = Carbon::createFromFormat('H:i:s', $opHour->open_time);
                    $close = Carbon::createFromFormat('H:i:s', $opHour->close_time);
                    $totalOperatingMinutes += $open->diffInMinutes($close);
                } elseif (! $opHour) {
                    $totalOperatingMinutes += 14 * 60;
                }
            }

            $currentDate->addDay();
        }

        $occupancyRate = $totalOperatingMinutes > 0
            ? min(100.0, round(($bookedMinutes / $totalOperatingMinutes) * 100, 2))
            : 0.0;

        return response()->json([
            'start_date' => $startDateStr,
            'end_date' => $endDateStr,
            'revenue' => (float) $revenue,
            'booked_hours' => round($bookedMinutes / 60.0, 1),
            'occupancy_rate' => $occupancyRate,
            'courts_count' => $courtsCount,
        ]);
    }
}
