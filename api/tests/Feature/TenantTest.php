<?php

namespace Tests\Feature;

use App\Enums\TenantMemberRole;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test owner can onboard a tenant successfully.
     */
    public function test_owner_can_onboard_tenant_successfully(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OWNER]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/tenants', [
                'name' => 'Senayan Sport Arena',
                'slug' => 'senayan-sport-arena',
                'address' => 'Jl. Senayan No. 10',
                'phone' => '081234567890',
                'timezone' => 'Asia/Makassar',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'tenant' => ['id', 'owner_id', 'name', 'slug', 'address', 'phone', 'timezone', 'status'],
            ]);

        $tenantId = $response->json('tenant.id');

        // Assert tenant_members record was created automatically
        $this->assertDatabaseHas('tenant_members', [
            'tenant_id' => $tenantId,
            'user_id' => $owner->id,
            'role' => TenantMemberRole::OWNER->value,
        ]);
    }

    /**
     * Test player cannot onboard a tenant.
     */
    public function test_player_cannot_onboard_tenant(): void
    {
        $player = User::factory()->create(['role' => UserRole::PLAYER]);

        $response = $this->actingAs($player, 'sanctum')
            ->postJson('/api/v1/tenants', [
                'name' => 'Senayan Sport Arena',
                'slug' => 'senayan-sport-arena',
                'address' => 'Jl. Senayan No. 10',
                'phone' => '081234567890',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test slug format and reserved words validation.
     */
    public function test_slug_validation_rules(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OWNER]);

        // 1. Invalid regex (uppercase / underscores)
        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/tenants', [
                'name' => 'Senayan Sport Arena',
                'slug' => 'Senayan_Sport',
                'address' => 'Jl. Senayan No. 10',
                'phone' => '081234567890',
            ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);

        // 2. Reserved word
        $response = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/tenants', [
                'name' => 'Senayan Sport Arena',
                'slug' => 'admin',
                'address' => 'Jl. Senayan No. 10',
                'phone' => '081234567890',
            ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    /**
     * Test viewing tenant details by ID.
     */
    public function test_tenant_member_can_view_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OWNER]);
        $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);
        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/tenants/{$tenant->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['slug' => $tenant->slug]);
    }

    /**
     * Test cross-tenant access is blocked.
     */
    public function test_non_member_cannot_view_tenant(): void
    {
        $owner1 = User::factory()->create(['role' => UserRole::OWNER]);
        $tenant = Tenant::factory()->create(['owner_id' => $owner1->id]);
        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner1->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        $owner2 = User::factory()->create(['role' => UserRole::OWNER]);

        $response = $this->actingAs($owner2, 'sanctum')
            ->getJson("/api/v1/tenants/{$tenant->id}");

        $response->assertStatus(403);
    }

    /**
     * Test updating tenant configurations by Owner.
     */
    public function test_owner_can_update_tenant_settings(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OWNER]);
        $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);
        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenant->id}", [
                'name' => 'Updated Arena Name',
                'address' => 'Updated Address',
                'phone' => '081223344556',
                'timezone' => 'Asia/Jakarta',
                'hold_minutes' => 30,
                'cancellation_window_hours' => 4,
                'max_advance_days' => 60,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Arena Name',
                'hold_minutes' => 30,
                'cancellation_window_hours' => 4,
                'max_advance_days' => 60,
            ]);
    }

    /**
     * Test Staff member cannot update tenant settings (Owner only).
     */
    public function test_staff_cannot_update_tenant_settings(): void
    {
        $owner = User::factory()->create(['role' => UserRole::OWNER]);
        $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);
        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => TenantMemberRole::OWNER,
        ]);

        $staff = User::factory()->create(['role' => UserRole::STAFF]);
        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'role' => TenantMemberRole::STAFF,
        ]);

        $response = $this->actingAs($staff, 'sanctum')
            ->putJson("/api/v1/tenants/{$tenant->id}", [
                'name' => 'Updated Arena Name',
                'address' => 'Updated Address',
                'phone' => '081223344556',
                'timezone' => 'Asia/Jakarta',
                'hold_minutes' => 30,
                'cancellation_window_hours' => 4,
                'max_advance_days' => 60,
            ]);

        $response->assertStatus(403);
    }
}
