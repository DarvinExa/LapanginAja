<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantMemberRole;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
            'slug' => 'senayan-futsal',
            'timezone' => 'Asia/Jakarta',
            'hold_minutes' => 15,
            'max_advance_days' => 30,
        ]);

        TenantMember::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $owner->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        app()->instance(Tenant::class, $this->tenant);

        $this->court = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'slot_duration_minutes' => 60,
            'price_per_hour' => 150000,
        ]);

        // Mock MidtransService so it doesn't make real API calls
        $midtransMock = \Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('createSnapTransaction')
            ->andReturn('mocked-token-123');
        $this->app->instance(MidtransService::class, $midtransMock);
    }

    /**
     * Test booking creation successfully.
     */
    public function test_can_create_booking_successfully(): void
    {
        $timezone = $this->tenant->timezone;
        $startTimeLocal = Carbon::now($timezone)->addDay()->startOfDay()->addHours(8)->format('Y-m-d H:i:s'); // tomorrow 08:00:00

        $response = $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings", [
            'court_id' => $this->court->id,
            'start_time' => $startTimeLocal,
            'customer_name' => 'Alice Doe',
            'customer_phone' => '081234567890',
            'customer_email' => 'alice@example.com',
            'notes' => 'Tolong siapkan rompi.',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'booking' => [
                    'id', 'booking_code', 'start_time', 'end_time', 'price', 'status', 'payment_status', 'expires_at',
                ],
            ]);

        $bookingCode = $response->json('booking.booking_code');

        $this->assertDatabaseHas('bookings', [
            'booking_code' => $bookingCode,
            'customer_name' => 'Alice Doe',
            'status' => BookingStatus::PENDING->value,
            'payment_status' => PaymentStatus::UNPAID->value,
        ]);
    }

    /**
     * Test price snapshotting.
     */
    public function test_booking_price_snapshotting(): void
    {
        $timezone = $this->tenant->timezone;
        $startTimeLocal = Carbon::now($timezone)->addDay()->startOfDay()->addHours(9)->format('Y-m-d H:i:s');

        // Test 60 mins slot (150,000 * 60/60 = 150,000)
        $response1 = $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings", [
            'court_id' => $this->court->id,
            'start_time' => $startTimeLocal,
            'customer_name' => 'Alice',
            'customer_phone' => '081234567890',
            'customer_email' => 'alice@example.com',
        ]);
        $response1->assertStatus(201);
        $this->assertEquals(150000.00, (float) $response1->json('booking.price'));

        // Test 90 mins slot (150,000 * 90/60 = 225,000)
        $court90 = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'slot_duration_minutes' => 90,
            'price_per_hour' => 150000,
        ]);
        $startTimeLocal90 = Carbon::now($timezone)->addDay()->startOfDay()->addHours(11)->format('Y-m-d H:i:s');
        $response2 = $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings", [
            'court_id' => $court90->id,
            'start_time' => $startTimeLocal90,
            'customer_name' => 'Bob',
            'customer_phone' => '081234567890',
            'customer_email' => 'bob@example.com',
        ]);
        $response2->assertStatus(201);
        $this->assertEquals(225000.00, (float) $response2->json('booking.price'));
    }

    /**
     * Test double booking prevention concurrently.
     */
    public function test_concurrent_double_booking_prevention(): void
    {
        $timezone = $this->tenant->timezone;
        $startTimeLocal = Carbon::now($timezone)->addDay()->startOfDay()->addHours(14)->format('Y-m-d H:i:s');

        // First booking attempt succeeds
        $response1 = $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings", [
            'court_id' => $this->court->id,
            'start_time' => $startTimeLocal,
            'customer_name' => 'User One',
            'customer_phone' => '081234567890',
            'customer_email' => 'one@example.com',
        ]);
        $response1->assertStatus(201);

        // Second booking attempt for the exact same slot fails with 409 Conflict
        $response2 = $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings", [
            'court_id' => $this->court->id,
            'start_time' => $startTimeLocal,
            'customer_name' => 'User Two',
            'customer_phone' => '081234567890',
            'customer_email' => 'two@example.com',
        ]);
        $response2->assertStatus(409);
    }

    /**
     * Test getting booking by code.
     */
    public function test_can_get_booking_by_code(): void
    {
        $timezone = $this->tenant->timezone;
        $startTime = Carbon::now($timezone)->addDay();

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-SUCCESS',
            'customer_name' => 'Alice',
            'customer_phone' => '081234567890',
            'customer_email' => 'alice@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 150000,
            'status' => BookingStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Retrieve successful
        $response = $this->getJson("/api/v1/public/{$this->tenant->slug}/bookings/{$booking->booking_code}");
        $response->assertStatus(200)
            ->assertJsonFragment(['booking_code' => 'LA-SUCCESS']);

        // Retrieve non-existent code returns 404
        $this->getJson("/api/v1/public/{$this->tenant->slug}/bookings/LA-NOTEXIST")
            ->assertStatus(404);
    }

    /**
     * Test booking detail is protected across tenants.
     */
    public function test_booking_detail_cannot_be_leaked_across_tenants(): void
    {
        $timezone = $this->tenant->timezone;
        $startTime = Carbon::now($timezone)->addDay();

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-SECRET',
            'customer_name' => 'Alice',
            'customer_phone' => '081234567890',
            'customer_email' => 'alice@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 150000,
            'status' => BookingStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Create another tenant
        $otherOwner = User::factory()->create(['role' => UserRole::OWNER]);
        $otherTenant = Tenant::factory()->create([
            'owner_id' => $otherOwner->id,
            'slug' => 'hacker-futsal',
        ]);

        // Querying the booking code using other tenant's slug returns 404
        $this->getJson("/api/v1/public/{$otherTenant->slug}/bookings/{$booking->booking_code}")
            ->assertStatus(404);
    }
}
