<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAdminController extends Controller
{
    /**
     * List all tenants in the platform with pagination.
     */
    public function listTenants(): JsonResponse
    {
        $tenants = Tenant::orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'data' => TenantResource::collection($tenants),
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
            ],
        ]);
    }

    /**
     * Suspend or unsuspend a tenant.
     */
    public function toggleSuspend(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $request->validate([
            'status' => 'required|in:active,suspended',
        ]);

        $tenant->update([
            'status' => $request->status === 'active' ? TenantStatus::ACTIVE : TenantStatus::SUSPENDED,
        ]);

        return response()->json([
            'message' => 'Status tenant berhasil diperbarui.',
            'tenant' => new TenantResource($tenant),
        ]);
    }

    /**
     * Get platform aggregated statistics.
     */
    public function globalStats(): JsonResponse
    {
        $activeTenants = Tenant::where('status', TenantStatus::ACTIVE)->count();
        $suspendedTenants = Tenant::where('status', TenantStatus::SUSPENDED)->count();

        $totalBookings = Booking::count();

        $totalRevenue = Payment::whereIn('transaction_status', ['settlement', 'capture'])->sum('gross_amount');

        return response()->json([
            'active_tenants' => $activeTenants,
            'suspended_tenants' => $suspendedTenants,
            'total_bookings' => $totalBookings,
            'total_revenue' => (float) $totalRevenue,
        ]);
    }
}
