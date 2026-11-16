<?php

namespace App\Http\Resources;

use App\Models\Scan;
use App\Services\ScanOutcome;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Passage enregistré par un partenaire.
 *
 * @mixin Scan
 */
class PartnerScanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'result' => $this->result,
            'method' => $this->method,
            'reason' => $this->reason,
            'reason_label' => ScanOutcome::label($this->reason),
            'operator_ref' => $this->operator_ref,
            'device_id' => $this->device_id,
            'idempotency_key' => $this->idempotency_key,
            'scanned_at' => $this->scanned_at,
            'location' => $this->hasLocation() ? [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'accuracy' => $this->location_accuracy,
            ] : null,
            'event' => $this->event ? ['id' => $this->event->id, 'name' => $this->event->name] : null,
            'pass' => $this->pass ? [
                'number' => $this->pass->number,
                'plate' => $this->pass->vehicle?->plate,
                'type' => ['name' => $this->pass->type->name, 'color' => $this->pass->type->color],
            ] : null,
        ];
    }
}
