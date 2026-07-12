<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TenantMemberRole;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class TenantStaffController extends Controller
{
    /**
     * List all staff members in the active tenant.
     */
    public function index(): JsonResponse
    {
        $tenant = app(Tenant::class);

        $staffMembers = $tenant->members()
            ->where('role', TenantMemberRole::STAFF)
            ->with('user')
            ->get()
            ->map(fn($member) => $member->user)
            ->filter();

        return response()->json([
            'staff' => UserResource::collection($staffMembers),
        ]);
    }

    /**
     * Create a new staff member and link to tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = app(Tenant::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^(\+62|0)8[0-9]{7,13}$/'],
            'password' => ['required', Password::min(8)],
        ], [
            'phone.regex' => 'Format nomor HP tidak valid. Gunakan format Indonesia (08xxxx atau +628xxxx).',
            'email.unique' => 'Alamat email ini sudah terdaftar di sistem.',
        ]);

        // 1. Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => UserRole::STAFF,
            'is_verified' => true, // Staff created by Owner is auto-verified
            'password' => Hash::make($validated['password']),
        ]);

        // 2. Link to tenant member
        TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => TenantMemberRole::STAFF,
        ]);

        return response()->json([
            'message' => 'Akun Staff berhasil dibuat.',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Remove a staff member from the tenant.
     */
    public function destroy(int $userId): JsonResponse
    {
        $tenant = app(Tenant::class);

        // Find the member record
        $member = $tenant->members()
            ->where('user_id', $userId)
            ->where('role', TenantMemberRole::STAFF)
            ->first();

        if (!$member) {
            abort(404, 'Staff tidak ditemukan pada venue ini.');
        }

        $user = $member->user;

        // Delete membership
        $member->delete();

        // Delete user if they don't have other tenant memberships
        if ($user && $user->tenantMembers()->count() === 0) {
            $user->delete();
        }

        return response()->json([
            'message' => 'Staff berhasil dinonaktifkan.',
        ]);
    }
}
