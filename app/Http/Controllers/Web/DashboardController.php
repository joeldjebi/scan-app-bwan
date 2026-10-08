<?php

namespace App\Http\Controllers\Web;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Liste des événements : tous pour l'admin, ceux qu'il supervise pour un chef agent.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $events = ($user->isAdmin() ? Event::query() : $user->events()->wherePivot('role', StaffRole::Chief->value))
            ->withCount([
                'passes',
                'passes as registered_count' => fn ($query) => $query->where('status', 'registered'),
                'passes as inside_count' => fn ($query) => $query->where('presence', 'in'),
            ])
            ->orderByDesc('starts_at')
            ->paginate(20);

        return view('dashboard', compact('events'));
    }
}
