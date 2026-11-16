<?php

namespace App\Models;

use App\Enums\Direction;
use App\Enums\ScanMethod;
use App\Enums\ScanResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'pass_id', 'user_id', 'partner_id', 'operator_ref', 'direction', 'result', 'method', 'reason', 'forced', 'offline', 'device_id', 'latitude', 'longitude', 'location_accuracy', 'client_uuid', 'idempotency_key', 'scanned_at'])]
class Scan extends Model
{
    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'result' => ScanResult::class,
            'method' => ScanMethod::class,
            'forced' => 'boolean',
            'offline' => 'boolean',
            'scanned_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_accuracy' => 'integer',
        ];
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function mapUrl(): ?string
    {
        return $this->hasLocation() ? "https://www.google.com/maps?q={$this->latitude},{$this->longitude}" : null;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Partenaire extérieur auteur du passage (API partenaire), à la place d'un agent.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Nom affiché de l'auteur : agent, ou partenaire suivi de son opérateur.
     */
    public function operatorName(): ?string
    {
        if ($this->partner_id) {
            return trim(($this->partner?->name ?? 'Partenaire').($this->operator_ref ? " · {$this->operator_ref}" : ''));
        }

        return $this->agent?->name;
    }
}
