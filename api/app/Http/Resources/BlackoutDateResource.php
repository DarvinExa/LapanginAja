<?php

namespace App\Http\Resources;

use App\Models\BlackoutDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlackoutDate
 */
class BlackoutDateResource extends JsonResource
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
            'court_id' => $this->court_id,
            'date' => $this->date instanceof \DateTimeInterface ? $this->date->format('Y-m-d') : $this->date,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
