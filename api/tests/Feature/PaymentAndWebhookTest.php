<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantMemberRole;
use App\Enums\UserRole;
use App\Jobs\SendBookingNotifications;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentAndWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Court $court;

    private $midtransMock;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
            'slug' => 'senayan-futsal',
            'timezone' => 'Asia/Jakarta',
            'hold_minutes' => 15,
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
            'price_per_hour' => 100000,
            'slot_duration_minutes' => 60,
        ]);

        // Mock MidtransService by default in tests
        $this->midtransMock = \Mockery::mock(MidtransService::class);
        $this->app->instance(MidtransService::class, $this->midtransMock);
    }

    /**
     * Test booking creation calls Midtrans and saves payment.
     */
    public function test_booking_creation_creates_midtrans_transaction(): void
    {
        $timezone = $this->tenant->timezone;
        $startTimeLocal = Carbon::now($timezone)->addDay()->startOfDay()->addHours(8)->format('Y-m-d H:i:s');

        $this->midtransMock->shouldReceive('createSnapTransaction')
            ->once()
            ->andReturn('snap-token-xyz123');

        $response = $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings", [
            'court_id' => $this->court->id,
            'start_time' => $startTimeLocal,
            'customer_name' => 'John Doe',
            'customer_phone' => '081234567890',
            'customer_email' => 'john@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('payment.snap_token', 'snap-token-xyz123');
    }

    /**
     * Test handling Midtrans creation failure (releases slot).
     */
    public function test_booking_cancels_if_midtrans_fails(): void
    {
        $timezone = $this->tenant->timezone;
        $startTimeLocal = Carbon::now($timezone)->addDay()->startOfDay()->addHours(9)->format('Y-m-d H:i:s');

        $this->midtransMock->shouldReceive('createSnapTransaction')
            ->once()
            ->andReturn(null);

        $response = $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings", [
            'court_id' => $this->court->id,
            'start_time' => $startTimeLocal,
            'customer_name' => 'John Doe',
            'customer_phone' => '081234567890',
            'customer_email' => 'john@example.com',
        ]);

        $response->assertStatus(502);

        // Assert booking is marked cancelled
        $this->assertDatabaseHas('bookings', [
            'court_id' => $this->court->id,
            'status' => BookingStatus::CANCELLED->value,
        ]);
    }

    /**
     * Test pay retry endpoint limits.
     */
    public function test_pay_retry_endpoint_boundaries(): void
    {
        $timezone = $this->tenant->timezone;
        $startTime = Carbon::now($timezone)->addDay();

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-RET1',
            'customer_name' => 'John',
            'customer_phone' => '081234567890',
            'customer_email' => 'john@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 100000,
            'status' => BookingStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Scenario 1: Re-use existing token
        Payment::create([
            'booking_id' => $booking->id,
            'order_id' => 'LA-RET1-1234',
            'gross_amount' => 100000,
            'snap_token' => 'snap-existing',
            'transaction_status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings/LA-RET1/pay");
        $response->assertStatus(200)
            ->assertJsonPath('snap_token', 'snap-existing');

        // Scenario 2: Paid booking returns 409
        $booking->update([
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings/LA-RET1/pay")
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Booking sudah dibayar.']);

        // Scenario 3: Expired booking returns 409
        $booking->update([
            'status' => BookingStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings/LA-RET1/pay")
            ->assertStatus(409)
            ->assertJsonFragment(['message' => 'Waktu pembayaran booking ini sudah kedaluwarsa.']);
    }

    /**
     * Test webhook successful processing.
     */
    public function test_midtrans_webhook_successful_payment(): void
    {
        Queue::fake();

        $timezone = $this->tenant->timezone;
        $startTime = Carbon::now($timezone)->addDay();

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-WEB1',
            'customer_name' => 'Alice',
            'customer_phone' => '081234567890',
            'customer_email' => 'alice@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 100000,
            'status' => BookingStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
            'expires_at' => now()->addMinutes(15),
        ]);

        $orderId = 'LA-WEB1-17772';
        $statusCode = '200';
        $grossAmount = '100000.00';
        $serverKey = config('midtrans.server_key');
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_id' => 'midtrans-tx-12345',
        ];

        $response = $this->postJson('/api/v1/webhooks/midtrans', $payload);
        $response->assertStatus(200);

        // Verify status updates
        $this->assertDatabaseHas('bookings', [
            'booking_code' => 'LA-WEB1',
            'status' => BookingStatus::CONFIRMED->value,
            'payment_status' => PaymentStatus::PAID->value,
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $orderId,
            'transaction_status' => 'settlement',
        ]);

        Queue::assertPushed(SendBookingNotifications::class);
    }

    /**
     * Test webhook signature validation and nominal protection.
     */
    public function test_midtrans_webhook_signature_and_price_validation(): void
    {
        $timezone = $this->tenant->timezone;
        $startTime = Carbon::now($timezone)->addDay();

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-WEB2',
            'customer_name' => 'Alice',
            'customer_phone' => '081234567890',
            'customer_email' => 'alice@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 100000,
            'status' => BookingStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
            'expires_at' => now()->addMinutes(15),
        ]);

        $orderId = 'LA-WEB2-17772';
        $statusCode = '200';
        $grossAmount = '100000.00';

        // 1. Test invalid signature
        $payloadInvalidSignature = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => 'fake-signature-key',
            'transaction_status' => 'settlement',
        ];

        $this->postJson('/api/v1/webhooks/midtrans', $payloadInvalidSignature)
            ->assertStatus(403);

        // 2. Test gross amount mismatch
        $serverKey = config('midtrans.server_key');
        $mismatchGrossAmount = '90000.00';
        $correctSignatureForMismatch = hash('sha512', $orderId.$statusCode.$mismatchGrossAmount.$serverKey);

        $payloadMismatchAmount = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $mismatchGrossAmount,
            'signature_key' => $correctSignatureForMismatch,
            'transaction_status' => 'settlement',
        ];

        $this->postJson('/api/v1/webhooks/midtrans', $payloadMismatchAmount)
            ->assertStatus(400);
    }

    /**
     * Test auto-release expired bookings scheduler command.
     */
    public function test_scheduler_auto_releases_expired_bookings(): void
    {
        $timezone = $this->tenant->timezone;
        $startTime = Carbon::now($timezone)->addDay();

        // 1. Expired booking
        $expiredBooking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-EXP1',
            'customer_name' => 'Expired User',
            'customer_phone' => '081234567890',
            'customer_email' => 'exp@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 100000,
            'status' => BookingStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
            'expires_at' => now()->subMinutes(5),
        ]);

        Payment::create([
            'booking_id' => $expiredBooking->id,
            'order_id' => 'LA-EXP1-999',
            'gross_amount' => 100000,
            'snap_token' => 'snap-exp',
            'transaction_status' => 'pending',
        ]);

        // 2. Non-expired booking
        $activeBooking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-ACT1',
            'customer_name' => 'Active User',
            'customer_phone' => '081234567890',
            'customer_email' => 'act@example.com',
            'start_time' => $startTime->copy()->addHours(2)->utc(),
            'end_time' => $startTime->copy()->addHours(3)->utc(),
            'price' => 100000,
            'status' => BookingStatus::PENDING,
            'payment_status' => PaymentStatus::UNPAID,
            'expires_at' => now()->addMinutes(10),
        ]);

        Artisan::call('bookings:release-expired');

        // Expired booking should be cancelled
        $this->assertDatabaseHas('bookings', [
            'booking_code' => 'LA-EXP1',
            'status' => BookingStatus::CANCELLED->value,
            'payment_status' => PaymentStatus::FAILED->value,
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => 'LA-EXP1-999',
            'transaction_status' => 'expire',
        ]);

        // Non-expired booking should remain pending
        $this->assertDatabaseHas('bookings', [
            'booking_code' => 'LA-ACT1',
            'status' => BookingStatus::PENDING->value,
        ]);
    }

    /**
     * Test race condition handling: Webhook wins and confirms booking after scheduler cancels it.
     */
    public function test_webhook_wins_race_condition(): void
    {
        $timezone = $this->tenant->timezone;
        $startTime = Carbon::now($timezone)->addDay();

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-RACE',
            'customer_name' => 'Racer',
            'customer_phone' => '081234567890',
            'customer_email' => 'racer@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 100000,
            'status' => BookingStatus::CANCELLED, // Already expired/cancelled by scheduler
            'payment_status' => PaymentStatus::FAILED,
            'expires_at' => now()->subMinutes(10),
        ]);

        $orderId = 'LA-RACE-12345';
        $statusCode = '200';
        $grossAmount = '100000.00';
        $serverKey = config('midtrans.server_key');
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
        ];

        // Webhook processes settlement payment
        $response = $this->postJson('/api/v1/webhooks/midtrans', $payload);
        $response->assertStatus(200);

        // Booking must be confirmed and payment marked paid!
        $this->assertDatabaseHas('bookings', [
            'booking_code' => 'LA-RACE',
            'status' => BookingStatus::CONFIRMED->value,
            'payment_status' => PaymentStatus::PAID->value,
        ]);
    }
}
