<?php

namespace App\Http\Resources;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'hold_minutes' => $this->hold_minutes,
            'cancellation_window_hours' => $this->cancellation_window_hours,
            'max_advance_days' => $this->max_advance_days,
            'status' => $this->status instanceof TenantStatus ? $this->status->value : $this->status,
            'logo_url' => $this->logo_url,
            'image_url' => $this->image_url,
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
