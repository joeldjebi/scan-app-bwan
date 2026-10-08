<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\StaffRole;
use App\Services\PosterPalette;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'code', 'location', 'starts_at', 'ends_at', 'status', 'description', 'logo_path', 'poster_path', 'theme_from_poster', 'primary_color', 'secondary_color'])]
class Event extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => EventStatus::class,
            'theme_from_poster' => 'boolean',
        ];
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_staff')
            ->using(EventStaff::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function chief(): ?User
    {
        return $this->staff->first(fn (User $user) => $user->pivot->role === StaffRole::Chief);
    }

    public function passTypes(): HasMany
    {
        return $this->hasMany(PassType::class);
    }

    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function isClosed(): bool
    {
        return $this->status === EventStatus::Closed;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function posterUrl(): ?string
    {
        return $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null;
    }

    /**
     * Couleurs du formulaire d'enregistrement (issues de l'affiche ou choisies par l'admin).
     *
     * @return array{primary: string, secondary: string, on_primary: string, primary_soft: string, primary_dark: string}
     */
    public function theme(): array
    {
        $primary = $this->primary_color ?? '#4f46e5';
        $secondary = $this->secondary_color ?? PosterPalette::shade($primary, -0.4);

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'on_primary' => PosterPalette::readableTextOn($primary),
            'primary_soft' => PosterPalette::shade($primary, 0.88),
            'primary_dark' => PosterPalette::shade($primary, -0.45),
        ];
    }
}
