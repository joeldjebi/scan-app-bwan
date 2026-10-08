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

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'location' => $this->location,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status' => $this->status,
            // Rôle de l'utilisateur connecté : chief, agent, ou admin.
            'my_role' => $user->isAdmin() ? 'admin' : $user->staffRoleFor($this->resource)?->value,
            'can_supervise' => $user->canSupervise($this->resource),
            'pass_types' => $this->passTypes->map->only(['id', 'name', 'code', 'color']),
        ];
    }
}
