<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    protected $model = Court::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Court '.fake()->randomElement(['A', 'B', 'C', '1', '2', '3']),
            'sport_type' => fake()->randomElement(['padel', 'futsal', 'badminton']),
            'price_per_hour' => fake()->randomElement([50000.00, 75000.00, 100000.00, 150000.00]),
            'slot_duration_minutes' => fake()->randomElement([60, 90]),
            'is_active' => true,
        ];
    }
}
