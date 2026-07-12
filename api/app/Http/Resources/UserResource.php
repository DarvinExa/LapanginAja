<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenantsList = [];
        $roleValue = $this->role instanceof UserRole ? $this->role : UserRole::tryFrom($this->role);

        if ($roleValue === UserRole::OWNER) {
            $tenantsList = $this->tenants->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ];
            })->toArray();
        } elseif ($roleValue === UserRole::STAFF) {
            $tenantsList = $this->tenantMembers->map(function ($member) {
                if ($member->tenant) {
                    return [
                        'id' => $member->tenant->id,
                        'name' => $member->tenant->name,
                        'slug' => $member->tenant->slug,
                    ];
                }
                return null;
            })->filter()->values()->toArray();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $roleValue ? $roleValue->value : $this->role,
            'tenants' => $tenantsList,
            'created_at' => $this->created_at,
        ];
    }
}
