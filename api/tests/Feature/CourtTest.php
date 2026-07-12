<?php

namespace Tests\Feature;

use App\Enums\TenantMemberRole;
use App\Enums\UserRole;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourtTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->tenant = Tenant::factory()->create(['owner_id' => $this->owner->id]);

        TenantMember::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'role' => TenantMemberRole::OWNER,
        ]);
    }

    /**
     * Test listing courts of tenant.
     */
    public function test_tenant_member_can_list_courts(): void
    {
        // Bind resolved tenant context for factory to succeed if scoped
        app()->instance(Tenant::class, $this->tenant);
        Court::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/courts");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test creating a court.
     */
    public function test_tenant_member_can_create_court(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/courts", [
                'name' => 'Lapangan Futsal A',
                'sport_type' => 'futsal',
                'price_per_hour' => 150000,
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Lapangan Futsal A',
                'sport_type' => 'futsal',
            ]);

        $this->assertDatabaseHas('courts', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Lapangan Futsal A',
        ]);
    }

    /**
     * Test cross-tenant access block on listing/creating.
     */
    public function test_non_member_cannot_access_tenant_courts(): void
    {
        $otherOwner = User::factory()->create(['role' => UserRole::OWNER]);

        // Trying to view Tenant A's courts
        $this->actingAs($otherOwner, 'sanctum')
            ->getJson("/api/v1/tenants/{$this->tenant->id}/courts")
            ->assertStatus(403);

        // Trying to create court in Tenant A
        $this->actingAs($otherOwner, 'sanctum')
            ->postJson("/api/v1/tenants/{$this->tenant->id}/courts", [
                'name' => 'Lapangan Futsal A',
                'sport_type' => 'futsal',
                'price_per_hour' => 150000,
                'slot_duration_minutes' => 60,
            ])
            ->assertStatus(403);
    }

    /**
     * Test viewing, updating, and deactivating individual court.
     */
    public function test_individual_court_crud_operations(): void
    {
        app()->instance(Tenant::class, $this->tenant);
        $court = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // 1. Show court
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/courts/{$court->id}");
        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $court->name]);

        // 2. Update court
        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/v1/courts/{$court->id}", [
                'name' => 'Updated Court Name',
                'sport_type' => 'badminton',
                'price_per_hour' => 80000,
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ]);
        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Court Name']);

        // 3. Deactivate court (destroy)
        $response = $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/v1/courts/{$court->id}");
        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Lapangan berhasil dinonaktifkan.']);

        $this->assertDatabaseHas('courts', [
            'id' => $court->id,
            'is_active' => false,
        ]);
    }

    /**
     * Test cross-tenant update/delete block.
     */
    public function test_non_member_cannot_modify_court(): void
    {
        app()->instance(Tenant::class, $this->tenant);
        $court = Court::factory()->create(['tenant_id' => $this->tenant->id]);

        $otherOwner = User::factory()->create(['role' => UserRole::OWNER]);

        // Try update
        $this->actingAs($otherOwner, 'sanctum')
            ->putJson("/api/v1/courts/{$court->id}", [
                'name' => 'Hacked Court Name',
                'sport_type' => 'badminton',
                'price_per_hour' => 80000,
                'slot_duration_minutes' => 60,
                'is_active' => true,
            ])
            ->assertStatus(403);

        // Try delete
        $this->actingAs($otherOwner, 'sanctum')
            ->deleteJson("/api/v1/courts/{$court->id}")
            ->assertStatus(403);
    }
}
