<?php

namespace App\Http\Resources;

use App\Models\Court;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Court
 */
class CourtResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'sport_type' => $this->sport_type,
            'price_per_hour' => $this->price_per_hour,
            'slot_duration_minutes' => $this->slot_duration_minutes,
            'is_active' => $this->is_active,
            'operating_hours' => $this->operatingHours ? $this->operatingHours->map(function ($oh) {
                return [
                    'day_of_week' => $oh->day_of_week,
                    'open_time' => $oh->open_time ? substr($oh->open_time, 0, 5) : null,
                    'close_time' => $oh->close_time ? substr($oh->close_time, 0, 5) : null,
                    'is_closed' => (bool) $oh->is_closed,
                ];
            }) : [],
            'created_at' => $this->created_at,
        ];
    }
}
