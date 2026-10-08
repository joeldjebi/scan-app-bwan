<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Entrée du journal d'audit. Immuable : elle ne peut être ni modifiée ni supprimée.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** Catégories d'actions (préfixe de `action`) proposées dans les filtres. */
    public const CATEGORIES = [
        'auth' => 'Connexions',
        'access' => 'Accès refusés',
        'event' => 'Événements',
        'pass_type' => 'Types de pass',
        'pass' => 'Pass',
        'vehicle' => 'Véhicules',
        'staff' => 'Équipes',
        'scan' => 'Passages',
        'user' => 'Comptes',
        'brand' => 'Marques',
        'export' => 'Exports',
    ];

    public const CHANNELS = [
        'web' => 'Back-office',
        'api' => 'Application mobile',
        'public' => 'Page usager',
        'console' => 'Serveur (console)',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'audit ne peut pas être modifié.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'audit ne peut pas être supprimé.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): string
    {
        return strtok($this->action, '.');
    }

    public function isSecurityRelated(): bool
    {
        return in_array($this->action, ['auth.login_failed', 'auth.blocked', 'access.denied', 'access.throttled'], true);
    }
}
