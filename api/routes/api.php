<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlackoutDateController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CourtController;
use App\Http\Controllers\Api\V1\PlatformAdminController;
use App\Http\Controllers\Api\V1\PublicBookingController;
use App\Http\Controllers\Api\V1\PublicVenueController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\TenantStaffController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Guest routes
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/webhooks/midtrans', [WebhookController::class, 'midtrans']);

    // Public booking & venue routes (no auth)
    Route::middleware('tenant')->group(function () {
        Route::get('/public/{slug}', [PublicVenueController::class, 'show']);
        Route::get('/public/{slug}/availability', [PublicVenueController::class, 'availability']);
        Route::post('/public/{slug}/bookings', [PublicBookingController::class, 'store']);
        Route::get('/public/{slug}/bookings/{code}', [PublicBookingController::class, 'show']);
        Route::post('/public/{slug}/bookings/{code}/pay', [PublicBookingController::class, 'pay']);
        Route::get('/public/{slug}/bookings/{code}/payment-status', [PublicBookingController::class, 'paymentStatus']);
        Route::post('/public/{slug}/bookings/{code}/simulate-payment', [PublicBookingController::class, 'simulatePayment']);
        Route::post('/public/{slug}/bookings/{code}/cancel', [PublicBookingController::class, 'cancel']);
    });

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Onboarding route (create tenant)
        Route::post('/tenants', [TenantController::class, 'store']);

        // Tenant-scoped routes
        Route::middleware(['tenant', 'tenant.member'])->group(function () {
            Route::get('/tenants/{id}', [TenantController::class, 'show']);
            Route::put('/tenants/{id}', [TenantController::class, 'update'])->middleware('tenant.member:owner');

            // Staff management (Owner only)
            Route::get('/tenants/{id}/staff', [TenantStaffController::class, 'index']);
            Route::post('/tenants/{id}/staff', [TenantStaffController::class, 'store'])->middleware('tenant.member:owner');
            Route::delete('/tenants/{id}/staff/{user_id}', [TenantStaffController::class, 'destroy'])->middleware('tenant.member:owner');

            // Courts listing and creation
            Route::get('/tenants/{id}/courts', [CourtController::class, 'index']);
            Route::post('/tenants/{id}/courts', [CourtController::class, 'store'])->middleware('tenant.member:owner');

            // Individual court operations (tenant resolved by court ID)
            Route::get('/courts/{id}', [CourtController::class, 'show']);
            Route::put('/courts/{id}', [CourtController::class, 'update'])->middleware('tenant.member:owner');
            Route::delete('/courts/{id}', [CourtController::class, 'destroy'])->middleware('tenant.member:owner');
            Route::put('/courts/{id}/operating-hours', [CourtController::class, 'setOperatingHours'])->middleware('tenant.member:owner');

            // Blackout date routes scoped by court ID
            Route::get('/courts/{court_id}/blackout-dates', [BlackoutDateController::class, 'index']);
            Route::post('/courts/{court_id}/blackout-dates', [BlackoutDateController::class, 'store'])->middleware('tenant.member:owner');

            // Individual blackout date operations (tenant resolved by blackout date ID)
            Route::delete('/blackout-dates/{id}', [BlackoutDateController::class, 'destroy'])->middleware('tenant.member:owner');

            // Bookings operations scoped to tenant
            Route::get('/tenants/{id}/bookings', [BookingController::class, 'index']);
            Route::post('/tenants/{id}/bookings/walk-in', [BookingController::class, 'storeWalkIn']);
            Route::get('/tenants/{id}/stats', [TenantController::class, 'stats']);
            Route::patch('/bookings/{id}/status', [BookingController::class, 'updateStatus']);
        });

        // Platform Admin (Super Admin only) routes
        Route::middleware('super_admin')->group(function () {
            Route::get('/admin/tenants', [PlatformAdminController::class, 'listTenants']);
            Route::post('/admin/tenants/{id}/suspend', [PlatformAdminController::class, 'toggleSuspend']);
            Route::get('/admin/stats', [PlatformAdminController::class, 'globalStats']);
        });
    });
});
