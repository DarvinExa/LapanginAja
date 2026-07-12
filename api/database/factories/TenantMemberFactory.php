<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantMember>
 */
class TenantMemberFactory extends Factory
{
    protected $model = TenantMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role' => 'staff',
            'created_at' => now(),
        ];
    }
}
