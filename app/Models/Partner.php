<?php

namespace App\Models;

use App\Enums\PartnerAbility;
use App\Models\Concerns\Auditable;
use App\Services\ScanActor;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Entité extérieure qui scanne les pass via l'API partenaire, uniquement sur les événements
 * auxquels elle a été donnée accès. Elle ne peut jamais forcer un passage.
 */
#[Fillable(['name', 'contact_name', 'contact_email', 'contact_phone', 'is_active', 'webhook_url'])]
#[Hidden(['webhook_secret'])]
class Partner extends Model implements ScanActor
{
    /** @use HasFactory<PartnerFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['webhook_secret'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'webhook_secret' => 'encrypted',
        ];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_partner')
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(PartnerApiKey::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }

    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function canOperate(Event $event): bool
    {
        return $this->is_active && $this->events()->whereKey($event->id)->exists();
    }

    public function canSupervise(Event $event): bool
    {
        return false;
    }

    /**
     * Crée une clé API. La clé en clair n'est renvoyée qu'ici : seul son hachage est conservé.
     *
     * @param  list<PartnerAbility>  $abilities
     * @param  list<string>  $allowedIps
     * @return array{0: PartnerApiKey, 1: string}
     */
    public function issueApiKey(string $name, array $abilities, ?Carbon $expiresAt = null, array $allowedIps = []): array
    {
        $plain = PartnerApiKey::PREFIX.Str::random(40);

        $key = $this->apiKeys()->create([
            'name' => $name,
            'prefix' => substr($plain, 0, 12),
            'key_hash' => PartnerApiKey::hash($plain),
            'abilities' => array_map(fn (PartnerAbility $ability) => $ability->value, $abilities),
            'allowed_ips' => $allowedIps ?: null,
            'expires_at' => $expiresAt,
        ]);

        return [$key, $plain];
    }

    /**
     * Génère un nouveau secret de signature des webhooks et le renvoie.
     */
    public function rotateWebhookSecret(): string
    {
        $secret = 'whsec_'.Str::random(40);
        $this->forceFill(['webhook_secret' => $secret])->save();

        return $secret;
    }

    /**
     * Supprime le partenaire en conservant son historique : ses clés sont révoquées, ses accès aux
     * événements retirés et ses webhooks en attente abandonnés. Ses passages restent à son nom.
     */
    public function deleteKeepingHistory(): void
    {
        DB::transaction(function () {
            $this->apiKeys()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $this->events()->detach();
            $this->webhookDeliveries()->where('status', WebhookDelivery::PENDING)
                ->update(['status' => WebhookDelivery::FAILED, 'last_error' => 'Partenaire supprimé.']);
            $this->delete();
        });
    }

    public function hasWebhook(): bool
    {
        return $this->webhook_url !== null && $this->webhook_secret !== null;
    }

    public function auditLabel(): string
    {
        return (string) $this->name;
    }

    protected function auditNoun(): array
    {
        return ['Partenaire', false];
    }
}
