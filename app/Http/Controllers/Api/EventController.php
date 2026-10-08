<?php

namespace App\Http\Controllers\Api;

use App\Enums\Direction;
use App\Enums\EventStatus;
use App\Enums\PassStatus;
use App\Enums\ScanResult;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\ScanResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    /**
     * Événements (non clôturés) auxquels l'utilisateur est affecté ; tous pour un admin.
     * Sert au chef pour choisir l'événement à superviser.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $events = ($user->isAdmin() ? Event::query() : $user->events())
            ->where('status', '!=', EventStatus::Closed)
            ->with('passTypes')
            ->orderBy('starts_at')
            ->get();

        return EventResource::collection($events);
    }

    /**
     * Tableau de bord du chef agent.
     */
    public function stats(Event $event): JsonResponse
    {
        $passes = $event->passes()
            ->selectRaw('pass_type_id, status, presence, count(*) as total')
            ->groupBy('pass_type_id', 'status', 'presence')
            ->get();

        $scans = $event->scans()
            ->selectRaw('direction, result, count(*) as total')
            ->groupBy('direction', 'result')
            ->get();

        $count = fn ($collection, array $filters) => (int) $collection
            ->filter(fn ($row) => collect($filters)->every(fn ($value, $key) => $row->{$key} == $value))
            ->sum('total');

        return response()->json([
            'passes' => [
                'total' => $count($passes, []),
                'pending' => $count($passes, ['status' => PassStatus::Pending]),
                'registered' => $count($passes, ['status' => PassStatus::Registered]),
                'revoked' => $count($passes, ['status' => PassStatus::Revoked]),
                'inside' => $count($passes, ['presence' => Direction::In]),
            ],
            'scans' => [
                'entries' => $count($scans, ['direction' => Direction::In, 'result' => ScanResult::Granted]),
                'exits' => $count($scans, ['direction' => Direction::Out, 'result' => ScanResult::Granted]),
                'denied' => $count($scans, ['result' => ScanResult::Denied]),
            ],
            'by_type' => $event->passTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'color' => $type->color,
                'total' => $count($passes, ['pass_type_id' => $type->id]),
                'registered' => $count($passes, ['pass_type_id' => $type->id, 'status' => PassStatus::Registered]),
                'inside' => $count($passes, ['pass_type_id' => $type->id, 'presence' => Direction::In]),
            ]),
        ]);
    }

    /**
     * Derniers passages de l'événement (chef agent).
     */
    public function scans(Event $event): AnonymousResourceCollection
    {
        $scans = $event->scans()
            ->with(['agent', 'event', 'pass.type', 'pass.vehicle'])
            ->latest('scanned_at')
            ->paginate(50);

        return ScanResource::collection($scans);
    }
}
