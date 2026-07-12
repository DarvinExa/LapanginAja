<?php

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = $this->tenant->timezone ?? 'Asia/Makassar';

        $startTime = $this->start_time;
        $endTime = $this->end_time;
        $expiresAt = $this->expires_at;

        $startFormatted = $startTime instanceof \DateTimeInterface ? (new Carbon($startTime))->timezone($timezone)->format('Y-m-d H:i:s') : $startTime;
        $endFormatted = $endTime instanceof \DateTimeInterface ? (new Carbon($endTime))->timezone($timezone)->format('Y-m-d H:i:s') : $endTime;
        $expiresFormatted = $expiresAt instanceof \DateTimeInterface ? (new Carbon($expiresAt))->toIso8601String() : $expiresAt;

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'court_id' => $this->court_id,
            'booking_code' => $this->booking_code,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_email' => $this->customer_email,
            'start_time' => $startFormatted,
            'end_time' => $endFormatted,
            'price' => $this->price,
            'status' => $this->status instanceof BookingStatus ? $this->status->value : $this->status,
            'payment_status' => $this->payment_status instanceof PaymentStatus ? $this->payment_status->value : $this->payment_status,
            'expires_at' => $expiresFormatted,
            'source' => $this->source,
            'notes' => $this->notes,
            'court' => $this->court ? [
                'id' => $this->court->id,
                'name' => $this->court->name,
                'sport_type' => $this->court->sport_type,
            ] : null,
            'payment' => $this->payment ? [
                'snap_token' => $this->payment->snap_token,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
