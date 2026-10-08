<?php

namespace App\Models;

use App\Enums\Direction;
use App\Enums\ScanResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'pass_id', 'user_id', 'direction', 'result', 'reason', 'forced', 'offline', 'device_id', 'latitude', 'longitude', 'location_accuracy', 'client_uuid', 'scanned_at'])]
class Scan extends Model
{
    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'result' => ScanResult::class,
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
}
