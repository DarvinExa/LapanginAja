<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantMemberRole;
use App\Enums\UserRole;
use App\Models\BlackoutDate;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Court $activeCourt;

    private Court $inactiveCourt;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create a tenant with Asia/Jakarta timezone and 7 days max advance
        $owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
            'slug' => 'senayan-futsal',
            'timezone' => 'Asia/Jakarta',
            'max_advance_days' => 7,
        ]);

        TenantMember::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $owner->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        // Bind the tenant globally for creation logic
        app()->instance(Tenant::class, $this->tenant);

        // 2. Create courts
        $this->activeCourt = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Court',
            'is_active' => true,
            'slot_duration_minutes' => 60,
            'price_per_hour' => 100000,
        ]);

        $this->inactiveCourt = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive Court',
            'is_active' => false,
        ]);

        // 3. Set operating hours for active court: open 08:00 to 12:00 everyday
        for ($i = 0; $i < 7; $i++) {
            $this->activeCourt->operatingHours()->create([
                'day_of_week' => $i,
                'open_time' => '08:00',
                'close_time' => '12:00',
                'is_closed' => false,
            ]);
        }
    }

    /**
     * Test public profile shows only active courts.
     */
    public function test_public_profile_excludes_inactive_courts(): void
    {
        $response = $this->getJson("/api/v1/public/{$this->tenant->slug}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $this->activeCourt->name])
            ->assertJsonMissing(['name' => $this->inactiveCourt->name]);
    }

    /**
     * Test availability date boundary checks.
     */
    public function test_availability_date_boundaries(): void
    {
        $timezone = $this->tenant->timezone;

        // 1. Date before today (in tenant's timezone)
        $pastDate = Carbon::now($timezone)->subDay()->format('Y-m-d');
        $response = $this->getJson("/api/v1/public/{$this->tenant->slug}/availability?court_id={$this->activeCourt->id}&date={$pastDate}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);

        // 2. Date exceeds max_advance_days (H+7)
        $futureDate = Carbon::now($timezone)->addDays(9)->format('Y-m-d');
        $response = $this->getJson("/api/v1/public/{$this->tenant->slug}/availability?court_id={$this->activeCourt->id}&date={$futureDate}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    /**
     * Test availability slots generation and booking statuses.
     */
    public function test_slots_availability_with_various_bookings(): void
    {
        $timezone = $this->tenant->timezone;
        // We test for tomorrow
        $targetDateStr = Carbon::now($timezone)->addDay()->format('Y-m-d');
        $targetDate = Carbon::parse($targetDateStr, $timezone);

        // Define slot local start/end times
        // Slot 1: 08:00 - 09:00
        // Slot 2: 09:00 - 10:00
        // Slot 3: 10:00 - 11:00
        // Slot 4: 11:00 - 12:00

        // Create confirmed booking for Slot 1: 08:00 - 09:00 local (01:00 - 02:00 UTC)
        Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->activeCourt->id,
            'booking_code' => 'CONF123',
            'customer_name' => 'John',
            'customer_email' => 'john@example.com',
            'customer_phone' => '0812345678',
            'start_time' => Carbon::parse($targetDateStr.' 08:00:00', $timezone)->utc(),
            'end_time' => Carbon::parse($targetDateStr.' 09:00:00', $timezone)->utc(),
            'price' => 100000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        // Create pending active booking for Slot 2: 09:00 - 10:00 local (expires in 15 mins)
        Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->activeCourt->id,
            'booking_code' => 'PEND123',
            'customer_name' => 'Alice',
            'customer_email' => 'alice@example.com',
            'customer_phone' => '0812345678',
            'start_time' => Carbon::parse($targetDateStr.' 09:00:00', $timezone)->utc(),
            'end_time' => Carbon::parse($targetDateStr.' 10:00:00', $timezone)->utc(),
            'price' => 100000,
            'status' => BookingStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        // Create pending expired booking for Slot 3: 10:00 - 11:00 local (expired 10 mins ago)
        Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->activeCourt->id,
            'booking_code' => 'EXP123',
            'customer_name' => 'Bob',
            'customer_email' => 'bob@example.com',
            'customer_phone' => '0812345678',
            'start_time' => Carbon::parse($targetDateStr.' 10:00:00', $timezone)->utc(),
            'end_time' => Carbon::parse($targetDateStr.' 11:00:00', $timezone)->utc(),
            'price' => 100000,
            'status' => BookingStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
            'expires_at' => Carbon::now()->subMinutes(10),
        ]);

        // Call availability API
        $response = $this->getJson("/api/v1/public/{$this->tenant->slug}/availability?court_id={$this->activeCourt->id}&date={$targetDateStr}");

        $response->assertStatus(200)
            ->assertJson([
                'date' => $targetDateStr,
                'court_id' => $this->activeCourt->id,
                'slots' => [
                    // Confirmed booking -> booked
                    ['start_time' => '08:00', 'end_time' => '09:00', 'status' => 'booked'],
                    // Pending non-expired booking -> booked (on hold)
                    ['start_time' => '09:00', 'end_time' => '10:00', 'status' => 'booked'],
                    // Pending expired booking -> available
                    ['start_time' => '10:00', 'end_time' => '11:00', 'status' => 'available'],
                    // No booking -> available
                    ['start_time' => '11:00', 'end_time' => '12:00', 'status' => 'available'],
                ],
            ]);
    }

    /**
     * Test blackout dates blocks all slots for that day.
     */
    public function test_blackout_dates_blocks_entire_day(): void
    {
        $timezone = $this->tenant->timezone;
        $targetDateStr = Carbon::now($timezone)->addDay()->format('Y-m-d');

        // Set blackout date
        BlackoutDate::create([
            'court_id' => $this->activeCourt->id,
            'date' => $targetDateStr,
            'reason' => 'Renovasi',
        ]);

        $response = $this->getJson("/api/v1/public/{$this->tenant->slug}/availability?court_id={$this->activeCourt->id}&date={$targetDateStr}");

        $response->assertStatus(200)
            ->assertJson([
                'date' => $targetDateStr,
                'court_id' => $this->activeCourt->id,
                'slots' => [],
            ]);
    }
}
