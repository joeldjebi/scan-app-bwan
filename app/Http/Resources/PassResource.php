<?php

namespace App\Http\Resources;

use App\Models\Pass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pass */
class PassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'number' => $this->number,
            'token' => $this->token,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'presence' => $this->presence,
            'next_direction' => $this->nextDirection(),
            'type' => [
                'id' => $this->type->id,
                'name' => $this->type->name,
                'code' => $this->type->code,
                'color' => $this->type->color,
            ],
            'vehicle' => $this->vehicle ? [
                'plate' => $this->vehicle->plate,
                'plate_key' => $this->vehicle->plate_key,
                'brand' => $this->vehicle->brand,
                'color' => $this->vehicle->color,
                'phone' => $this->vehicle->phone,
            ] : null,
            'registered_at' => $this->registered_at,
            'last_scanned_at' => $this->last_scanned_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
