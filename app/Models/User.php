<?php

namespace App\Models;

use App\Enums\StaffRole;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Le téléphone sert d'identifiant de connexion : il est stocké sans espaces ni séparateurs.
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => self::normalizePhone($value));
    }

    public static function normalizePhone(?string $value): ?string
    {
        $phone = preg_replace('/(?!^\+)[^\d]/', '', trim((string) $value));

        return $phone !== '' ? $phone : null;
    }

    public static function findByPhone(?string $phone): ?self
    {
        $phone = self::normalizePhone($phone);

        return $phone ? self::where('phone', $phone)->first() : null;
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_staff')
            ->using(EventStaff::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Rôle de l'utilisateur sur un événement (null s'il n'y est pas affecté).
     */
    public function staffRoleFor(Event $event): ?StaffRole
    {
        return $this->events()->whereKey($event->id)->first()?->pivot->role;
    }

    public function isChiefOf(Event $event): bool
    {
        return $this->staffRoleFor($event) === StaffRole::Chief;
    }

    /**
     * Peut scanner / consulter un événement : admin ou membre de l'équipe.
     */
    public function canOperate(Event $event): bool
    {
        return $this->isAdmin() || $this->staffRoleFor($event) !== null;
    }

    /**
     * Peut superviser un événement (stats, révocation, passage forcé).
     */
    public function canSupervise(Event $event): bool
    {
        return $this->isAdmin() || $this->isChiefOf($event);
    }
}
