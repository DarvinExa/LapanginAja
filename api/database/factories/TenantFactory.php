<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company().' Arena',
            'slug' => fake()->unique()->slug(2),
            'address' => fake()->address(),
            'phone' => '08'.fake()->numerify('##########'),
            'timezone' => 'Asia/Makassar',
            'hold_minutes' => 15,
            'cancellation_window_hours' => 2,
            'max_advance_days' => 30,
            'status' => 'active',
        ];
    }
}
