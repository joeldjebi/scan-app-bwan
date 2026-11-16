<?php

namespace App\Models;

use App\Enums\PartnerAbility;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpFoundation\IpUtils;

#[Fillable(['name', 'prefix', 'key_hash', 'abilities', 'allowed_ips', 'expires_at', 'revoked_at', 'last_used_at', 'last_used_ip'])]
#[Hidden(['key_hash'])]
class PartnerApiKey extends Model
{
    use Auditable;

    public const PREFIX = 'ppk_';

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['key_hash', 'last_used_at', 'last_used_ip'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'allowed_ips' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function findByPlainKey(string $plain): ?self
    {
        if (! str_starts_with($plain, self::PREFIX)) {
            return null;
        }

        return self::with('partner')->where('key_hash', self::hash($plain))->first();
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function allows(PartnerAbility $ability): bool
    {
        return in_array($ability->value, $this->abilities ?? [], true);
    }

    /**
     * Aucune restriction si la liste est vide ; sinon IP exactes ou plages CIDR.
     */
    public function acceptsIp(?string $ip): bool
    {
        return empty($this->allowed_ips) || ($ip !== null && IpUtils::checkIp($ip, $this->allowed_ips));
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    public function auditLabel(): string
    {
        return "{$this->partner?->name} · {$this->name} ({$this->prefix}…)";
    }

    protected function auditNoun(): array
    {
        return ['Clé API', true];
    }
}
