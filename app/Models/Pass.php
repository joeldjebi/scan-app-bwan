<?php

namespace App\Models;

use App\Enums\Direction;
use App\Enums\PassStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['status', 'presence', 'registered_at', 'last_scanned_at'])]
class Pass extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PassStatus::class,
            'presence' => Direction::class,
            'registered_at' => 'datetime',
            'last_scanned_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PassType::class, 'pass_type_id');
    }

    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    /**
     * Lien encodé dans le QR code (enregistrement usager + scan agent).
     */
    public function url(): string
    {
        return route('public.pass', $this->token);
    }

    public function isRegistered(): bool
    {
        return $this->status === PassStatus::Registered;
    }

    /**
     * Sens du prochain passage : un véhicule dehors entre, un véhicule dedans sort.
     */
    public function nextDirection(): Direction
    {
        return $this->presence->opposite();
    }
}
