<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\PassResource;
use App\Models\Event;
use App\Models\Pass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SyncController extends Controller
{
    /**
     * Pass de tous les événements (non clôturés) de l'agent, pour le mode hors ligne.
     * `since` ne renvoie que les pass modifiés depuis, plus l'intégralité des pass
     * des événements auxquels l'agent a été affecté entre-temps, et les identifiants
     * des pass supprimés depuis (à retirer du cache local).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['since' => ['nullable', 'date']]);

        $user = $request->user();
        $serverTime = now();
        $since = $request->since ? Carbon::parse($request->since)->timezone(config('app.timezone')) : null;

        $events = ($user->isAdmin() ? Event::query() : $user->events())
            ->where('status', '!=', EventStatus::Closed)
            ->with('passTypes')
            ->orderBy('starts_at')
            ->get();

        $newlyAssigned = $since && ! $user->isAdmin()
            ? $events->filter(fn (Event $event) => Carbon::parse($event->pivot->created_at)->gte($since))->pluck('id')
            : collect();

        $passes = Pass::whereIn('event_id', $events->pluck('id'))
            ->with(['type', 'vehicle'])
            ->when($since, fn ($query) => $query->where(fn ($q) => $q
                ->where('updated_at', '>=', $since)
                ->orWhereIn('event_id', $newlyAssigned)))
            ->orderBy('id')
            ->get();

        $deletedPassIds = $since
            ? Pass::onlyTrashed()
                ->whereIn('event_id', $events->pluck('id'))
                ->where('deleted_at', '>=', $since)
                ->pluck('id')
            : collect();

        return response()->json([
            'server_time' => $serverTime->toIso8601String(),
            'full' => $since === null,
            'events' => EventResource::collection($events),
            'passes' => PassResource::collection($passes),
            'deleted_pass_ids' => $deletedPassIds,
        ]);
    }
}
