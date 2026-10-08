<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        // Rôle déjà chargé via la relation user->events (pivot) : évite une requête par événement.
        $role = $user->isAdmin()
            ? 'admin'
            : ($this->pivot?->role ?? $user->staffRoleFor($this->resource))?->value;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'location' => $this->location,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status,
            // Rôle de l'utilisateur connecté : chief, agent, ou admin.
            'my_role' => $role,
            'can_supervise' => in_array($role, ['admin', 'chief'], true),
            'pass_types' => $this->passTypes->map->only(['id', 'name', 'code', 'color']),
        ];
    }
}
