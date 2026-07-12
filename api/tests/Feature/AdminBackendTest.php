<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantMemberRole;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminBackendTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private User $superAdmin;

    private User $player;

    private Tenant $tenant;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->staff = User::factory()->create(['role' => UserRole::STAFF]);
        $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $this->player = User::factory()->create(['role' => UserRole::PLAYER]);

        $this->tenant = Tenant::factory()->create([
            'owner_id' => $this->owner->id,
            'slug' => 'senayan-futsal',
            'timezone' => 'Asia/Jakarta',
            'hold_minutes' => 15,
            'cancellation_window_hours' => 2,
        ]);

        TenantMember::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        TenantMember::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->staff->id,
            'role' => TenantMemberRole::STAFF,
        ]);

        $this->court = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'price_per_hour' => 100000,
            'slot_duration_minutes' => 60,
        ]);
    }

    /**
     * Test booking listing, pagination, and tenant isolation.
     */
    public function test_tenant_members_can_list_and_filter_bookings(): void
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant',
        ]);
        $otherCourt = Court::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        // Booking on target tenant
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-T1',
            'customer_name' => 'Alice',
            'customer_phone' => '08123',
            'customer_email' => 'alice@example.com',
            'start_time' => Carbon::now()->addHour()->utc(),
            'end_time' => Carbon::now()->addHours(2)->utc(),
            'price' => 100000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        // Booking on other tenant
        Booking::create([
            'tenant_id' => $otherTenant->id,
            'court_id' => $otherCourt->id,
            'booking_code' => 'LA-T2',
            'customer_name' => 'Bob',
            'customer_phone' => '08124',
            'customer_email' => 'bob@example.com',
            'start_time' => Carbon::now()->addHour()->utc(),
            'end_time' => Carbon::now()->addHours(2)->utc(),
            'price' => 100000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        // Owner lists bookings
        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/bookings?status=confirmed");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.booking_code', 'LA-T1')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        // Staff lists bookings
        $responseStaff = $this->actingAs($this->staff)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/bookings");
        $responseStaff->assertStatus(200);

        // Player cannot list bookings
        $responsePlayer = $this->actingAs($this->player)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/bookings");
        $responsePlayer->assertStatus(403);
    }

    /**
     * Test walk-in booking creation and validation.
     */
    public function test_tenant_members_can_create_walk_in_bookings(): void
    {
        $startTime = Carbon::now()->addDays(2)->setTime(10, 0, 0);

        $payload = [
            'court_id' => $this->court->id,
            'customer_name' => 'Tamu Walk-In',
            'customer_phone' => '08111222333',
            'customer_email' => 'walkin@example.com',
            'start_time' => $startTime->format('Y-m-d H:i:s'),
            'notes' => 'Pembayaran tunai',
        ];

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/bookings/walk-in", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('booking.status', BookingStatus::CONFIRMED->value)
            ->assertJsonPath('booking.payment_status', PaymentStatus::PAID->value);

        $this->assertDatabaseHas('bookings', [
            'customer_name' => 'Tamu Walk-In',
            'source' => 'walkin',
        ]);

        // Try double booking on the same slot
        $overlapResponse = $this->actingAs($this->staff)
            ->postJson("/api/v1/tenants/{$this->tenant->id}/bookings/walk-in", $payload);
        $overlapResponse->assertStatus(409);
    }

    /**
     * Test booking status update.
     */
    public function test_tenant_members_can_update_booking_status(): void
    {
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-UPD',
            'customer_name' => 'John',
            'customer_phone' => '08123',
            'customer_email' => 'john@example.com',
            'start_time' => Carbon::now()->addHour()->utc(),
            'end_time' => Carbon::now()->addHours(2)->utc(),
            'price' => 100000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        $response = $this->actingAs($this->owner)
            ->patchJson("/api/v1/bookings/{$booking->id}/status", [
                'status' => BookingStatus::COMPLETED->value,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::COMPLETED->value,
        ]);
    }

    /**
     * Test tenant occupancy & revenue stats calculation.
     */
    public function test_tenant_members_can_view_venue_stats(): void
    {
        $today = Carbon::now();

        // 1. Paid & Confirmed Booking
        Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-ST1',
            'customer_name' => 'Customer A',
            'customer_phone' => '081',
            'customer_email' => 'a@example.com',
            'start_time' => $today->copy()->setTime(10, 0)->utc(),
            'end_time' => $today->copy()->setTime(11, 0)->utc(), // 1 hour
            'price' => 100000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        // 2. Unpaid/Cancelled Booking (must be excluded from stats)
        Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-ST2',
            'customer_name' => 'Customer B',
            'customer_phone' => '082',
            'customer_email' => 'b@example.com',
            'start_time' => $today->copy()->setTime(14, 0)->utc(),
            'end_time' => $today->copy()->setTime(15, 0)->utc(),
            'price' => 100000,
            'status' => BookingStatus::CANCELLED,
            'payment_status' => PaymentStatus::FAILED,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/stats?start_date={$today->format('Y-m-d')}&end_date={$today->format('Y-m-d')}");

        $response->assertStatus(200)
            ->assertJsonPath('revenue', 100000)
            ->assertJsonPath('booked_hours', 1)
            ->assertJsonStructure(['occupancy_rate']);
    }

    /**
     * Test platform super admin listing, suspension, and global stats.
     */
    public function test_super_admin_tenants_management_and_stats(): void
    {
        // Global list tenants
        $responseList = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/tenants');
        $responseList->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Suspend tenant
        $responseSuspend = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/suspend", [
                'status' => 'suspended',
            ]);
        $responseSuspend->assertStatus(200);

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
            'status' => TenantStatus::SUSPENDED->value,
        ]);

        // Attempting login of tenant member now fails
        $responseLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $this->owner->email,
            'password' => 'password',
        ]);
        $responseLogin->assertStatus(403)
            ->assertJsonFragment(['message' => 'Akun Anda ditangguhkan (suspended). Silakan hubungi admin.']);

        // Platform global stats
        $globalBooking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-GLOBALB',
            'customer_name' => 'Global Customer',
            'customer_phone' => '081',
            'customer_email' => 'global@example.com',
            'start_time' => Carbon::now()->addHour()->utc(),
            'end_time' => Carbon::now()->addHours(2)->utc(),
            'price' => 150000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        Payment::create([
            'booking_id' => $globalBooking->id,
            'order_id' => 'LA-GLOBAL-1',
            'gross_amount' => 150000,
            'snap_token' => 'snap-global',
            'transaction_status' => 'settlement',
        ]);

        $responseStats = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/stats');

        $responseStats->assertStatus(200)
            ->assertJsonPath('suspended_tenants', 1)
            ->assertJsonPath('total_revenue', 150000);

        // Player is forbidden from accessing admin routes
        $this->actingAs($this->player)
            ->getJson('/api/v1/admin/tenants')
            ->assertStatus(403);
    }
}
