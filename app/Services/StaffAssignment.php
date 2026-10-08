<?php

namespace App\Services;

use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Affectation des agents et chefs aux événements (équipe d'un événement, fiche d'un compte).
 * Un seul chef par événement : nommer un chef fait redevenir agent le chef précédent.
 */
class StaffAssignment
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Ajoute le membre à l'équipe ou change son rôle. Sans effet si le rôle est déjà le bon.
     */
    public function assign(Event $event, User $user, StaffRole $role): void
    {
        $current = $this->roleOf($event, $user);

        if ($current === $role) {
            return;
        }

        DB::transaction(function () use ($event, $user, $role, $current) {
            if ($role === StaffRole::Chief) {
                $this->demoteCurrentChief($event, $user);
            }

            $current === null
                ? $event->staff()->attach($user->id, ['role' => $role->value])
                : $event->staff()->updateExistingPivot($user->id, ['role' => $role->value]);
        });

        $current === null
            ? $this->audit->record('staff.added', "{$user->name} ajouté à l'équipe comme {$role->label()}", $event, ['membre' => $user->name, 'role' => $role->value])
            : $this->audit->record('staff.role_changed', "{$user->name} devient {$role->label()}", $event, ['membre' => $user->name, 'role' => $role->value]);
    }

    public function remove(Event $event, User $user): void
    {
        if ($this->roleOf($event, $user) === null) {
            return;
        }

        $event->staff()->detach($user->id);
        $this->audit->record('staff.removed', "{$user->name} retiré de l'équipe", $event, ['membre' => $user->name]);
    }

    /**
     * Applique les affectations d'un compte : [event_id => 'agent' | 'chief' | ''] ('' = retirer).
     * Les événements absents de la liste ne sont pas modifiés.
     *
     * @param  array<int|string, string|null>  $assignments
     */
    public function sync(User $user, array $assignments): void
    {
        $events = Event::whereIn('id', array_keys($assignments))->get()->keyBy('id');

        foreach ($assignments as $eventId => $role) {
            $event = $events->get((int) $eventId);

            if (! $event) {
                continue;
            }

            $role ? $this->assign($event, $user, StaffRole::from($role)) : $this->remove($event, $user);
        }
    }

    private function roleOf(Event $event, User $user): ?StaffRole
    {
        $role = $event->staff()->whereKey($user->id)->first()?->pivot->role;

        return $role instanceof StaffRole ? $role : ($role ? StaffRole::from($role) : null);
    }

    private function demoteCurrentChief(Event $event, User $newChief): void
    {
        $previous = $event->staff()->wherePivot('role', StaffRole::Chief->value)->whereKeyNot($newChief->id)->first();

        if (! $previous) {
            return;
        }

        $event->staff()->updateExistingPivot($previous->id, ['role' => StaffRole::Agent->value]);
        $this->audit->record('staff.role_changed', "{$previous->name} redevient Agent parking (nouveau chef : {$newChief->name})", $event, ['membre' => $previous->name, 'role' => StaffRole::Agent->value]);
    }
}
