<?php

namespace App\Http\Resources;

use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Scan */
class ScanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'direction' => $this->direction,
            'result' => $this->result,
            'method' => $this->method,
            'reason' => $this->reason,
            'forced' => $this->forced,
            'offline' => $this->offline,
            'scanned_at' => $this->scanned_at,
            'location' => $this->hasLocation() ? [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'accuracy' => $this->location_accuracy,
            ] : null,
            'event' => $this->whenLoaded('event', fn () => $this->event ? ['id' => $this->event->id, 'name' => $this->event->name] : null),
            'agent' => $this->whenLoaded('agent', fn () => $this->agent ? ['id' => $this->agent->id, 'name' => $this->agent->name] : null),
            'pass' => $this->whenLoaded('pass', fn () => $this->pass ? [
                'id' => $this->pass->id,
                'number' => $this->pass->number,
                'plate' => $this->pass->vehicle?->plate,
                'type' => $this->pass->relationLoaded('type') ? [
                    'name' => $this->pass->type->name,
                    'color' => $this->pass->type->color,
                ] : null,
            ] : null),
        ];
    }
}
