<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticatePartner;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Événements sur lesquels le partenaire peut scanner.
     */
    public function index(Request $request): JsonResponse
    {
        $events = AuthenticatePartner::partner($request)->events()->orderByDesc('starts_at')->get();

        return response()->json([
            'data' => $events->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'code' => $event->code,
                'location' => $event->location,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'status' => $event->status,
                'is_open' => ! $event->isClosed(),
            ])->values(),
        ]);
    }
}
