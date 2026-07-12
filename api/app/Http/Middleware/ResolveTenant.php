<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\BlackoutDate;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');
        $tenantId = $request->route('tenant_id') ?? $request->route('id');

        $tenant = null;

        if ($slug) {
            $tenant = Tenant::where('slug', $slug)->first();
        } elseif ($tenantId && $request->is('api/v1/tenants/*')) {
            // For admin endpoints `/tenants/{id}/...`
            $tenant = Tenant::find($tenantId);
        } elseif ($request->is('api/v1/courts/*')) {
            // For admin endpoints `/courts/{id}/...`
            $courtId = $request->route('court') ?? $request->route('court_id') ?? $request->route('id');
            if ($courtId) {
                $court = Court::withoutGlobalScopes()->find($courtId);
                if ($court) {
                    $tenant = Tenant::find($court->tenant_id);
                }
            }
        } elseif ($request->is('api/v1/blackout-dates/*')) {
            // For admin endpoints `/blackout-dates/{id}/...`
            $blackoutId = $request->route('blackout_date') ?? $request->route('id');
            if ($blackoutId) {
                $blackout = BlackoutDate::find($blackoutId);
                if ($blackout) {
                    $court = Court::withoutGlobalScopes()->find($blackout->court_id);
                    if ($court) {
                        $tenant = Tenant::find($court->tenant_id);
                    }
                }
            }
        } elseif ($request->is('api/v1/bookings/*')) {
            $bookingId = $request->route('booking') ?? $request->route('id');
            if ($bookingId) {
                $booking = Booking::withoutGlobalScopes()->find($bookingId);
                if ($booking) {
                    $tenant = Tenant::find($booking->tenant_id);
                }
            }
        }

        if ($tenant) {
            // If the tenant is suspended, abort with 403 Forbidden
            if ($tenant->status === TenantStatus::SUSPENDED) {
                abort(403, 'Akun Anda ditangguhkan (suspended). Silakan hubungi admin.');
            }

            // Bind the tenant to the service container
            app()->instance(Tenant::class, $tenant);

            // Share the tenant instance globally for this request
            $request->attributes->set('tenant', $tenant);
        } else {
            // Only abort 404 if we are trying to resolve a tenant-scoped route
            if (
                $slug ||
                ($tenantId && $request->is('api/v1/tenants/*')) ||
                $request->is('api/v1/courts/*') ||
                $request->is('api/v1/blackout-dates/*') ||
                $request->is('api/v1/bookings/*')
            ) {
                abort(404, 'Venue, court, atau blackout date tidak ditemukan.');
            }
        }

        return $next($request);
    }
}
