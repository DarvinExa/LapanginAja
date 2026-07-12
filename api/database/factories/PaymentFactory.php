<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'order_id' => 'ORDER-'.Str::upper(Str::random(10)),
            'gross_amount' => 100000.00,
            'payment_type' => 'qris',
            'transaction_id' => (string) fake()->uuid(),
            'transaction_status' => 'pending',
            'snap_token' => 'snap-token-'.Str::random(20),
            'paid_at' => null,
            'raw_payload' => [],
        ];
    }
}
