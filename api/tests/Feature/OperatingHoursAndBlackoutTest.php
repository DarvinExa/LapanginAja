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

class OperatingHoursAndBlackoutTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Court $court;

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

        app()->instance(Tenant::class, $this->tenant);
        $this->court = Court::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * Test setting valid operating hours.
     */
    public function test_can_set_operating_hours_successfully(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/v1/courts/{$this->court->id}/operating-hours", [
                'hours' => [
                    ['day_of_week' => 0, 'open_time' => '08:00', 'close_time' => '22:00', 'is_closed' => false],
                    ['day_of_week' => 1, 'open_time' => '08:00', 'close_time' => '17:00', 'is_closed' => false],
                    ['day_of_week' => 2, 'open_time' => null, 'close_time' => null, 'is_closed' => true],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Jadwal operasional lapangan berhasil diperbarui.']);

        $this->assertDatabaseHas('operating_hours', [
            'court_id' => $this->court->id,
            'day_of_week' => 0,
            'open_time' => '08:00',
            'close_time' => '22:00',
            'is_closed' => false,
        ]);

        $this->assertDatabaseHas('operating_hours', [
            'court_id' => $this->court->id,
            'day_of_week' => 2,
            'is_closed' => true,
        ]);
    }

    /**
     * Test operating hours validation fails if close_time <= open_time.
     */
    public function test_operating_hours_validation_fails_for_invalid_times(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/v1/courts/{$this->court->id}/operating-hours", [
                'hours' => [
                    ['day_of_week' => 0, 'open_time' => '18:00', 'close_time' => '08:00', 'is_closed' => false],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['hours.0.close_time']);
    }

    /**
     * Test CRUD operations on blackout dates.
     */
    public function test_blackout_dates_crud_operations(): void
    {
        $today = now()->format('Y-m-d');
        $tomorrow = now()->addDay()->format('Y-m-d');

        // 1. Create blackout date for tomorrow
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/v1/courts/{$this->court->id}/blackout-dates", [
                'date' => $tomorrow,
                'reason' => 'Renovasi Gedung',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['date' => $tomorrow, 'reason' => 'Renovasi Gedung']);

        $this->assertDatabaseHas('blackout_dates', [
            'court_id' => $this->court->id,
            'date' => $tomorrow,
            'reason' => 'Renovasi Gedung',
        ]);

        $blackoutId = $response->json('blackout_date.id');

        // 2. Prevent creating duplicate blackout date for same court + date
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/v1/courts/{$this->court->id}/blackout-dates", [
                'date' => $tomorrow,
                'reason' => 'Duplicate',
            ])
            ->assertStatus(422);

        // 3. Prevent creating blackout date in the past
        $yesterday = now()->subDay()->format('Y-m-d');
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/v1/courts/{$this->court->id}/blackout-dates", [
                'date' => $yesterday,
                'reason' => 'Past Date',
            ])
            ->assertStatus(422);

        // 4. List blackout dates
        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/courts/{$this->court->id}/blackout-dates")
            ->assertStatus(200)
            ->assertJsonCount(1, 'blackout_dates');

        // 5. Delete blackout date
        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/v1/blackout-dates/{$blackoutId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('blackout_dates', [
            'id' => $blackoutId,
        ]);
    }

    /**
     * Test non-member is blocked from managing operating hours and blackout dates.
     */
    public function test_non_member_blocked_from_operating_hours_and_blackout(): void
    {
        $otherOwner = User::factory()->create(['role' => UserRole::OWNER]);
        $tomorrow = now()->addDay()->format('Y-m-d');

        // Block operating hours PUT
        $this->actingAs($otherOwner, 'sanctum')
            ->putJson("/api/v1/courts/{$this->court->id}/operating-hours", [
                'hours' => [
                    ['day_of_week' => 0, 'open_time' => '08:00', 'close_time' => '22:00', 'is_closed' => false],
                ],
            ])
            ->assertStatus(403);

        // Block blackout dates POST
        $this->actingAs($otherOwner, 'sanctum')
            ->postJson("/api/v1/courts/{$this->court->id}/blackout-dates", [
                'date' => $tomorrow,
                'reason' => 'Luar Wewenang',
            ])
            ->assertStatus(403);
    }
}
