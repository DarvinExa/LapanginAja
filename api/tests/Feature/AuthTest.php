<?php

namespace Tests\Feature;

use App\Enums\TenantMemberRole;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
        RateLimiter::clear('register');
    }

    /**
     * Test user registration successfully.
     */
    public function test_user_can_register_successfully(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '081234567890',
            'role' => 'player',
            'password' => 'SecurePass123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone', 'role', 'created_at'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'phone' => '081234567890',
            'role' => 'player',
        ]);
    }

    /**
     * Test registration validation errors.
     */
    public function test_registration_validation_fails_for_invalid_data(): void
    {
        // 1. Weak password (no numbers)
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '081234567890',
            'role' => 'player',
            'password' => 'weakpassword',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 2. Invalid phone format
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '12345',
            'role' => 'player',
            'password' => 'SecurePass123',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /**
     * Test user login successfully.
     */
    public function test_user_can_login_successfully(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('SecurePass123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'phone', 'role', 'created_at'],
                'token',
            ]);
    }

    /**
     * Test login returns 401 for incorrect credentials.
     */
    public function test_login_fails_with_incorrect_password(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('SecurePass123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment([
                'message' => 'Email atau password salah.',
            ]);
    }

    /**
     * Test profile (me) endpoint.
     */
    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'email' => $user->email,
            ]);
    }

    /**
     * Test logout revokes token.
     */
    public function test_user_can_logout_successfully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Berhasil logout.',
            ]);
    }

    /**
     * Test rate limit on login endpoint.
     */
    public function test_login_rate_limiting(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => bcrypt('SecurePass123'),
        ]);

        // Attempt login 5 times
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'john@example.com',
                'password' => 'WrongPassword',
            ])->assertStatus(401);
        }

        // 6th attempt should trigger 429 Too Many Requests
        $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword',
        ])->assertStatus(429);
    }

    /**
     * Test login block for users associated with a suspended tenant.
     */
    public function test_user_associated_with_suspended_tenant_is_blocked_login(): void
    {
        $owner = User::factory()->create([
            'email' => 'suspended-owner@example.com',
            'role' => UserRole::OWNER,
            'password' => bcrypt('SecurePass123'),
        ]);

        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
            'status' => TenantStatus::SUSPENDED,
        ]);

        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended-owner@example.com',
            'password' => 'SecurePass123',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Akun Anda ditangguhkan (suspended). Silakan hubungi admin.',
            ]);
    }
}
