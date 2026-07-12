<?php

namespace Tests\Feature;

use App\Enums\TenantMemberRole;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationAndStaffTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test registration creates OTP and leaves user unverified.
     */
    public function test_registration_flow_with_verification(): void
    {
        // Change env to production simulation
        app()->detectEnvironment(fn() => 'production');

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '081234567891',
            'role' => 'player',
            'password' => 'SecurePass123',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_verified);
        $this->assertNotNull($user->otp_code);

        // Try logging in before verifying -> 403 needs_verification
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'SecurePass123',
        ]);

        $loginResponse->assertStatus(403)
            ->assertJsonFragment([
                'needs_verification' => true,
            ]);

        // Verify with correct OTP
        $verifyResponse = $this->postJson('/api/v1/auth/verify-otp', [
            'email' => 'jane@example.com',
            'code' => $user->otp_code,
        ]);

        $verifyResponse->assertStatus(200);
        $this->assertTrue($user->fresh()->is_verified);

        // Login now works
        $loginSuccessResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'SecurePass123',
        ]);

        $loginSuccessResponse->assertStatus(200);
    }

    /**
     * Test forgot and reset password flow.
     */
    public function test_forgot_and_reset_password(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => bcrypt('SecurePass123'),
            'is_verified' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'jane@example.com',
        ]);

        $response->assertStatus(200);
        $user = $user->fresh();
        $this->assertNotNull($user->reset_password_code);

        // Reset password
        $resetResponse = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'jane@example.com',
            'code' => $user->reset_password_code,
            'password' => 'NewSecurePass123',
            'password_confirmation' => 'NewSecurePass123',
        ]);

        $resetResponse->assertStatus(200);

        // Check if login works with new password
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'NewSecurePass123',
        ]);

        $loginResponse->assertStatus(200);
    }

    /**
     * Test staff creation and permission restrictions.
     */
    public function test_owner_can_manage_staff_and_staff_has_restricted_permissions(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::OWNER,
        ]);

        $tenant = Tenant::factory()->create([
            'owner_id' => $owner->id,
        ]);

        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        // 1. Owner creates staff account
        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/tenants/{$tenant->id}/staff", [
                'name' => 'Karyawan CS',
                'email' => 'staff_cs@example.com',
                'phone' => '089876543210',
                'password' => 'SecurePassCS123',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'staff_cs@example.com',
            'role' => UserRole::STAFF,
            'is_verified' => true,
        ]);

        $staff = User::where('email', 'staff_cs@example.com')->first();

        // 2. Staff tries to delete court -> blocked by owner middleware (403/404/Redirect based on policy)
        $response = $this->actingAs($staff, 'sanctum')
            ->postJson("/api/v1/tenants/{$tenant->id}/courts", [
                'name' => 'Blocked Court',
                'sport_type' => 'futsal',
                'price_per_hour' => 100000,
            ]);

        // Middleware is tenant.member:owner. It aborts with 403 if role is not owner.
        $response->assertStatus(403);
    }
}
