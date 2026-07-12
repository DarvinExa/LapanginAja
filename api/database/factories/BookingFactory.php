<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+30 days');
        // Round to nearest hour
        $start->setTime((int) $start->format('H'), 0, 0);
        $end = clone $start;
        $end->modify('+1 hour');

        return [
            'tenant_id' => Tenant::factory(),
            'court_id' => Court::factory(),
            'user_id' => User::factory(),
            'booking_code' => 'LA-'.Str::upper(Str::random(8)),
            'customer_name' => fake()->name(),
            'customer_phone' => '08'.fake()->numerify('##########'),
            'customer_email' => fake()->safeEmail(),
            'start_time' => $start,
            'end_time' => $end,
            'price' => 100000.00,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'expires_at' => now()->addMinutes(15),
            'source' => 'online',
            'notes' => fake()->sentence(),
        ];
    }
}
