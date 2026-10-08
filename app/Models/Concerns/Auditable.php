<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use BackedEnum;
use Illuminate\Support\Str;

/**
 * Journalise automatiquement la création, la modification (avant/après) et la suppression d'un modèle.
 *
 * Le modèle définit `auditLabel()` et peut lister dans `$auditExclude` les champs à ignorer :
 * une modification qui ne touche que ces champs n'est pas journalisée.
 */
trait Auditable
{
    private const AUDIT_ALWAYS_EXCLUDED = ['created_at', 'updated_at', 'remember_token', 'email_verified_at'];

    private const AUDIT_SECRET = ['password'];

    public static function bootAuditable(): void
    {
        static::created(fn (self $model) => $model->recordAudit('created'));
        static::updated(fn (self $model) => $model->recordAudit('updated'));
        static::deleted(fn (self $model) => $model->recordAudit('deleted'));
    }

    abstract public function auditLabel(): string;

    /**
     * @return array{0: string, 1: bool} nom affiché du modèle, féminin ?
     */
    abstract protected function auditNoun(): array;

    private function recordAudit(string $event): void
    {
        $excluded = [...self::AUDIT_ALWAYS_EXCLUDED, ...($this->auditExclude ?? [])];

        $keys = match ($event) {
            'created' => array_keys($this->getAttributes()),
            'updated' => array_keys($this->getChanges()),
            'deleted' => array_keys($this->getRawOriginal()),
        };

        $changes = [];
        foreach (array_diff($keys, $excluded, [$this->getKeyName()]) as $key) {
            $before = $event === 'created' ? null : $this->getRawOriginal($key);
            $after = $event === 'deleted' ? null : $this->getAttributes()[$key] ?? null;

            if (in_array($key, self::AUDIT_SECRET, true)) {
                [$before, $after] = [$before === null ? null : '••••••••', $after === null ? null : '•••••••• (nouveau)'];
            }

            $changes[$key] = [self::auditValue($before), self::auditValue($after)];
        }

        if ($event === 'updated' && $changes === []) {
            return;
        }

        [$noun, $feminine] = $this->auditNoun();
        $verb = match ($event) {
            'created' => 'créé',
            'updated' => 'modifié',
            'deleted' => 'supprimé',
        }.($feminine ? 'e' : '');

        app(AuditLogger::class)->record(
            Str::snake(class_basename($this)).'.'.$event,
            sprintf('%s « %s » %s', $noun, $this->auditLabel(), $verb),
            $this,
            changes: $changes,
        );
    }

    private static function auditValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
