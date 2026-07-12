<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Court;
use App\Models\OperatingHour;
use Illuminate\Support\Carbon;

class AvailabilityService
{
    /**
     * Get availability slots for a court on a specific date.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailability(Court $court, Carbon $date): array
    {
        $timezone = $court->tenant->timezone ?? 'Asia/Makassar';

        // 1. Check if the date is a blackout date
        $isBlackout = $court->blackoutDates()
            ->whereDate('date', $date->format('Y-m-d'))
            ->exists();

        if ($isBlackout) {
            return [];
        }

        // 2. Get operating hours for this weekday
        $dayOfWeek = $date->dayOfWeek; // 0 (Sunday) to 6 (Saturday)
        /** @var OperatingHour|null $hours */
        $hours = $court->operatingHours()
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $hours || $hours->is_closed) {
            return [];
        }

        // Parse open and close times
        $openTimeStr = $hours->open_time;
        $closeTimeStr = $hours->close_time;

        // Create local Carbon objects in tenant's timezone
        $localStart = Carbon::parse($date->format('Y-m-d').' '.$openTimeStr, $timezone);
        $localEnd = Carbon::parse($date->format('Y-m-d').' '.$closeTimeStr, $timezone);

        $duration = $court->slot_duration_minutes;
        $slots = [];

        // Fetch all active bookings for this court on this date to reduce query load inside loop
        // We fetch bookings that overlap with the day's operating hours interval in UTC
        $utcDayStart = (clone $localStart)->utc();
        $utcDayEnd = (clone $localEnd)->utc();

        $activeBookings = Booking::where('court_id', $court->id)
            ->where('status', '!=', BookingStatus::CANCELLED)
            ->where(function ($query) {
                // If pending, it must not be expired
                $query->where('status', '!=', BookingStatus::PENDING)
                    ->orWhere('expires_at', '>', now());
            })
            ->where('start_time', '<', $utcDayEnd)
            ->where('end_time', '>', $utcDayStart)
            ->get();

        $currentSlotStart = clone $localStart;

        while ($currentSlotStart->copy()->addMinutes($duration)->lte($localEnd)) {
            $currentSlotEnd = $currentSlotStart->copy()->addMinutes($duration);

            // Convert current slot to UTC for matching
            $slotStartUtc = $currentSlotStart->copy()->utc();
            $slotEndUtc = $currentSlotEnd->copy()->utc();

            // Check if this slot overlaps with any active booking
            $isBooked = $activeBookings->contains(function ($booking) use ($slotStartUtc, $slotEndUtc) {
                return $booking->start_time < $slotEndUtc && $booking->end_time > $slotStartUtc;
            });

            $slots[] = [
                'start_time' => $currentSlotStart->format('H:i'),
                'end_time' => $currentSlotEnd->format('H:i'),
                'status' => $isBooked ? 'booked' : 'available',
                'price' => (float) $court->price_per_hour * ($duration / 60.0),
            ];

            $currentSlotStart->addMinutes($duration);
        }

        return $slots;
    }
}
