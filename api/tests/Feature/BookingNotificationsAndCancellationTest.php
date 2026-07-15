<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\TenantMemberRole;
use App\Enums\UserRole;
use App\Jobs\SendBookingNotifications;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingNotificationsAndCancellationTest extends TestCase
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
            'cancellation_window_hours' => 24,
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
    }

    /**
     * Test QR & PDF generation, email and WhatsApp notifications dispatch in Job.
     */
    public function test_send_booking_notifications_job_execution(): void
    {
        Mail::fake();

        $startTime = Carbon::now()->addDays(2);
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-NOTIFJOB',
            'customer_name' => 'Alice Doe',
            'customer_phone' => '081234567890',
            'customer_email' => 'alice@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 100000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        // Execute the job
        $job = new SendBookingNotifications($booking);
        $job->handle(app(DocumentService::class));

        // Check Mail was sent
        Mail::assertSent(BookingConfirmedMail::class, function ($mail) use ($booking) {
            return $mail->hasTo($booking->customer_email);
        });

        // Verify notification logs saved in DB
        $this->assertDatabaseHas('notifications', [
            'booking_id' => $booking->id,
            'channel' => NotificationChannel::EMAIL->value,
            'type' => NotificationType::ETICKET->value,
            'recipient' => 'alice@example.com',
            'status' => NotificationStatus::SENT->value,
        ]);
    }

    /**
     * Test command for sending H-1 reminders.
     */
    public function test_scheduler_sends_tomorrow_reminders(): void
    {
        $tomorrow = Carbon::now()->addDay();

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-REM tomorrow',
            'customer_name' => 'Bob Smith',
            'customer_phone' => '081298765432',
            'customer_email' => 'bob@example.com',
            'start_time' => $tomorrow->copy()->startOfDay()->addHours(10)->utc(), // tomorrow 10:00 UTC
            'end_time' => $tomorrow->copy()->startOfDay()->addHours(11)->utc(),
            'price' => 100000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        Artisan::call('bookings:send-reminders');

        $this->assertDatabaseHas('notifications', [
            'booking_id' => $booking->id,
            'channel' => NotificationChannel::EMAIL->value,
            'type' => NotificationType::REMINDER->value,
            'status' => NotificationStatus::SENT->value,
        ]);
    }

    /**
     * Test notification retry command.
     */
    public function test_scheduler_retries_failed_notifications(): void
    {
        Mail::fake();

        $startTime = Carbon::now()->addDays(2);
        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-RETRY',
            'customer_name' => 'John',
            'customer_phone' => '081234567890',
            'customer_email' => 'john@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 100000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        // Seed a failed notification log
        $notification = Notification::create([
            'booking_id' => $booking->id,
            'channel' => NotificationChannel::EMAIL,
            'type' => NotificationType::INVOICE,
            'recipient' => $booking->customer_email,
            'content' => 'Invoice Mail',
            'status' => NotificationStatus::FAILED,
            'retry_count' => 0,
        ]);

        Artisan::call('notifications:retry');

        // Verify retry count incremented and status changed to sent
        $notification->refresh();
        $this->assertEquals(1, $notification->retry_count);
        $this->assertEquals(NotificationStatus::SENT, $notification->status);

        Mail::assertSent(BookingConfirmedMail::class);
    }

    /**
     * Test cancellation within window successfully triggers refund.
     */
    public function test_can_cancel_booking_within_window_with_refund(): void
    {
        $startTime = Carbon::now()->addHours(48); // 48 hours in future (well above 24 window)

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-CANCELOK',
            'customer_name' => 'Charlie',
            'customer_phone' => '081234567890',
            'customer_email' => 'charlie@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 100000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'order_id' => 'LA-CANCELOK-1',
            'gross_amount' => 100000,
            'snap_token' => 'snap-cancel',
            'transaction_status' => 'settlement',
        ]);

        // Mock Midtrans refund
        $midtransMock = \Mockery::mock(MidtransService::class);
        $midtransMock->shouldReceive('refundTransaction')
            ->once()
            ->andReturn(true);
        $this->app->instance(MidtransService::class, $midtransMock);

        $response = $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings/LA-CANCELOK/cancel");
        $response->assertStatus(200);

        $this->assertDatabaseHas('bookings', [
            'booking_code' => 'LA-CANCELOK',
            'status' => BookingStatus::CANCELLED->value,
            'payment_status' => PaymentStatus::REFUNDED->value,
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => 'LA-CANCELOK-1',
            'transaction_status' => 'refund',
        ]);
    }

    /**
     * Test cancellation outside window returns 422.
     */
    public function test_cannot_cancel_booking_outside_window(): void
    {
        $startTime = Carbon::now()->addHours(12); // 12 hours in future (below 24 window)

        $booking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'booking_code' => 'LA-CANCELEDFAIL',
            'customer_name' => 'Dave',
            'customer_phone' => '081234567890',
            'customer_email' => 'dave@example.com',
            'start_time' => $startTime->copy()->utc(),
            'end_time' => $startTime->copy()->addHour()->utc(),
            'price' => 100000,
            'status' => BookingStatus::CONFIRMED,
            'payment_status' => PaymentStatus::PAID,
        ]);

        $response = $this->postJson("/api/v1/public/{$this->tenant->slug}/bookings/LA-CANCELEDFAIL/cancel");
        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Pembatalan hanya diperbolehkan maksimal 24 jam sebelum jadwal dimulai.']);

        // Assert booking is still confirmed
        $this->assertDatabaseHas('bookings', [
            'booking_code' => 'LA-CANCELEDFAIL',
            'status' => BookingStatus::CONFIRMED->value,
        ]);
    }
}
