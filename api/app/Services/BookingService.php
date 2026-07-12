<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    /**
     * Create a new booking.
     */
    public function createBooking(array $data): Booking
    {
        $court = Court::findOrFail($data['court_id']);
        $tenant = $court->tenant;
        if (! $tenant instanceof Tenant) {
            abort(500, 'Venue tidak memiliki konfigurasi tenant.');
        }
        $timezone = $tenant->timezone ?? 'Asia/Makassar';

        // Parse local start time and calculate end time
        $localStart = Carbon::parse($data['start_time'], $timezone);
        $localEnd = (clone $localStart)->addMinutes($court->slot_duration_minutes);

        // Convert to UTC for database storage/comparison
        $startUtc = (clone $localStart)->utc();
        $endUtc = (clone $localEnd)->utc();

        return DB::transaction(function () use ($court, $tenant, $data, $startUtc, $endUtc) {
            // Pessimistic lock on the court to serialize slot selection
            Court::where('id', $court->id)->lockForUpdate()->first();

            // 1. Check if the date is a blackout date
            $isBlackout = $court->blackoutDates()
                ->whereDate('date', $startUtc->copy()->timezone($tenant->timezone)->format('Y-m-d'))
                ->exists();

            if ($isBlackout) {
                abort(409, 'Lapangan ditutup karena libur/maintenance pada tanggal ini.');
            }

            // 2. Check overlap with active bookings
            $overlap = Booking::where('court_id', $court->id)
                ->where('status', '!=', BookingStatus::CANCELLED)
                ->where(function ($query) {
                    $query->where('status', '!=', BookingStatus::PENDING)
                        ->orWhere('expires_at', '>', now());
                })
                ->where('start_time', '<', $endUtc)
                ->where('end_time', '>', $startUtc)
                ->exists();

            if ($overlap) {
                abort(409, 'Slot waktu ini sudah dipesan atau sedang di-hold.');
            }

            // 3. Snapshot price
            $price = (float) $court->price_per_hour * ($court->slot_duration_minutes / 60.0);

            // 4. Generate unique booking code
            $bookingCode = $this->generateUniqueBookingCode();

            // 5. Expiration hold time
            $expiresAt = now()->addMinutes($tenant->hold_minutes ?? 15);

            try {
                return Booking::create([
                    'tenant_id' => $tenant->id,
                    'court_id' => $court->id,
                    'user_id' => auth()->id(), // null if guest
                    'booking_code' => $bookingCode,
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $data['customer_phone'],
                    'customer_email' => $data['customer_email'],
                    'start_time' => $startUtc,
                    'end_time' => $endUtc,
                    'price' => $price,
                    'status' => BookingStatus::PENDING,
                    'payment_status' => PaymentStatus::UNPAID,
                    'expires_at' => $expiresAt,
                    'source' => $data['source'] ?? 'online',
                    'notes' => $data['notes'] ?? null,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                abort(409, 'Slot waktu ini baru saja dipesan oleh pengguna lain.');
            }
        });
    }

    /**
     * Create a new walk-in booking.
     */
    public function createWalkInBooking(array $data): Booking
    {
        $court = Court::findOrFail($data['court_id']);
        $tenant = $court->tenant;
        if (! $tenant instanceof Tenant) {
            abort(500, 'Venue tidak memiliki konfigurasi tenant.');
        }
        $timezone = $tenant->timezone ?? 'Asia/Jakarta';

        $localStart = Carbon::parse($data['start_time'], $timezone);
        if (isset($data['end_time'])) {
            $localEnd = Carbon::parse($data['end_time'], $timezone);
        } else {
            $localEnd = (clone $localStart)->addMinutes($court->slot_duration_minutes);
        }

        $startUtc = (clone $localStart)->utc();
        $endUtc = (clone $localEnd)->utc();

        return DB::transaction(function () use ($court, $tenant, $data, $startUtc, $endUtc) {
            Court::where('id', $court->id)->lockForUpdate()->first();

            // Check overlap with active bookings
            $overlap = Booking::where('court_id', $court->id)
                ->where('status', '!=', BookingStatus::CANCELLED)
                ->where('start_time', '<', $endUtc)
                ->where('end_time', '>', $startUtc)
                ->exists();

            if ($overlap) {
                abort(409, 'Slot waktu ini sudah dipesan.');
            }

            $durationInHours = $startUtc->diffInMinutes($endUtc) / 60.0;
            $price = (float) $court->price_per_hour * $durationInHours;

            $bookingCode = $this->generateUniqueBookingCode();

            try {
                return Booking::create([
                    'tenant_id' => $tenant->id,
                    'court_id' => $court->id,
                    'user_id' => auth()->id(),
                    'booking_code' => $bookingCode,
                    'customer_name' => $data['customer_name'],
                    'customer_phone' => $data['customer_phone'],
                    'customer_email' => $data['customer_email'],
                    'start_time' => $startUtc,
                    'end_time' => $endUtc,
                    'price' => $price,
                    'status' => BookingStatus::CONFIRMED,
                    'payment_status' => PaymentStatus::PAID,
                    'expires_at' => null,
                    'source' => 'walkin',
                    'notes' => $data['notes'] ?? null,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                abort(409, 'Slot waktu ini baru saja dipesan oleh pengguna lain.');
            }
        });
    }

    /**
     * Generate a unique booking code.
     */
    protected function generateUniqueBookingCode(): string
    {
        do {
            $code = 'LA-'.strtoupper(Str::random(8));
        } while (Booking::where('booking_code', $code)->exists());

        return $code;
    }
}
