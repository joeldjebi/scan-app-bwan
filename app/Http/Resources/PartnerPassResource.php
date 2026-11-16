<?php

namespace App\Http\Resources;

use App\Models\Pass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pass vu par un partenaire : uniquement ce qu'il faut pour contrôler le véhicule
 * (jamais le téléphone de l'usager ni le jeton du QR code).
 *
 * @mixin Pass
 */
class PartnerPassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'presence' => $this->presence,
            'next_direction' => $this->nextDirection(),
            'type' => [
                'name' => $this->type->name,
                'code' => $this->type->code,
                'color' => $this->type->color,
            ],
            'vehicle' => $this->vehicle ? [
                'plate' => $this->vehicle->plate,
                'brand' => $this->vehicle->brand,
                'color' => $this->vehicle->color,
            ] : null,
            'event' => ['id' => $this->event->id, 'name' => $this->event->name],
            'last_scanned_at' => $this->last_scanned_at,
        ];
    }
}
