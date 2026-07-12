<?php

namespace App\Http\Middleware;

use App\Enums\TenantMemberRole;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsTenantMember
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthorized.');
        }

        // Super Admin has access to everything
        if ($user->role === UserRole::SUPER_ADMIN) {
            return $next($request);
        }

        // Retrieve the resolved tenant from container
        if (! app()->bound(Tenant::class)) {
            abort(404, 'Context tenant tidak ditemukan.');
        }

        $tenant = app(Tenant::class);

        /** @var TenantMember|null $member */
        $member = $tenant->members()->where('user_id', $user->id)->first();

        if (! $member) {
            abort(403, 'Anda tidak memiliki akses ke tenant ini.');
        }

        // If a specific role is required (e.g. 'owner')
        $memberRoleValue = $member->role instanceof TenantMemberRole ? $member->role->value : $member->role;
        if ($role && $memberRoleValue !== $role) {
            abort(403, 'Aksi ini memerlukan role '.$role.'.');
        }

        return $next($request);
    }
}
