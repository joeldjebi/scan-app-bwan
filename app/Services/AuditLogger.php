<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Enregistre une entrée du journal d'audit avec tout son contexte (auteur, canal, IP,
 * navigateur, URL, appareil). Ne fait jamais échouer l'action journalisée.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties  informations complémentaires
     * @param  array<string, array{0: mixed, 1: mixed}>|null  $changes  champ => [avant, après]
     */
    public function record(string $action, string $description, ?Model $subject = null, array $properties = [], ?array $changes = null, ?User $actor = null): ?AuditLog
    {
        try {
            $request = app()->bound('request') ? request() : null;
            $actor ??= $this->currentUser();

            return AuditLog::create([
                'user_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'actor_role' => $actor?->role?->value,
                'action' => $action,
                'description' => Str::limit($description, 497),
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject && method_exists($subject, 'auditLabel') ? Str::limit($subject->auditLabel(), 250) : null,
                'event_id' => $this->eventIdOf($subject),
                'changes' => $changes ?: null,
                'properties' => $properties ?: null,
                'channel' => $this->channel(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'method' => $request?->route() ? $request->method() : null,
                'url' => $request?->route() ? $request->fullUrl() : null,
                'device' => $this->device($actor),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function channel(): string
    {
        $request = request();

        return match (true) {
            ! $request->route() => 'console',
            $request->is('api/*') => 'api',
            $request->routeIs('public.*') => 'public',
            default => 'web',
        };
    }

    /**
     * Nom de l'appareil (token de l'application mobile).
     */
    private function device(?User $actor): ?string
    {
        $token = $actor?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token->name : null;
    }

    /**
     * Événement de rattachement. Un événement qui vient d'être supprimé n'est pas rattaché
     * (la clé étrangère le refuserait) : son nom reste dans la description et l'objet.
     */
    private function eventIdOf(?Model $subject): ?int
    {
        $eventId = match (true) {
            $subject instanceof Event => $subject->exists ? $subject->id : null,
            $subject !== null && isset($subject->event_id) => $subject->event_id,
            default => null,
        };

        return $eventId && Event::whereKey($eventId)->exists() ? $eventId : null;
    }
}
