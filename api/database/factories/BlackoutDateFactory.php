<?php

namespace Database\Factories;

use App\Models\BlackoutDate;
use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlackoutDate>
 */
class BlackoutDateFactory extends Factory
{
    protected $model = BlackoutDate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'court_id' => Court::factory(),
            'date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'reason' => fake()->randomElement(['Maintenance', 'Tournament', 'National Holiday']),
            'created_at' => now(),
        ];
    }
}
