<?php

namespace App\Http\Controllers\Web;

use App\Enums\Direction;
use App\Enums\ScanMethod;
use App\Enums\ScanResult;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Historique des scans d'un agent ou d'un chef, tous événements confondus.
 */
class UserScanController extends Controller
{
    public function index(Request $request, User $user): View
    {
        $request->validate([
            'event' => ['nullable', 'integer'],
            'result' => ['nullable', 'in:granted,denied'],
            'direction' => ['nullable', 'in:in,out'],
            'method' => ['nullable', 'in:qr,plate'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $filtered = fn (): Builder => Scan::where('user_id', $user->id)
            ->when($request->event, fn ($query, $event) => $query->where('event_id', $event))
            ->when($request->result, fn ($query, $result) => $query->where('result', $result))
            ->when($request->direction, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($request->method, fn ($query, $method) => $query->where('method', $method))
            ->when($request->from, fn ($query, $from) => $query->where('scanned_at', '>=', $from))
            ->when($request->to, fn ($query, $to) => $query->where('scanned_at', '<=', $to.' 23:59:59'));

        $scans = $filtered()
            ->with(['event', 'pass.type', 'pass.vehicle'])
            ->latest('scanned_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'total' => $filtered()->count(),
            'entries' => $filtered()->where('result', ScanResult::Granted)->where('direction', Direction::In)->count(),
            'exits' => $filtered()->where('result', ScanResult::Granted)->where('direction', Direction::Out)->count(),
            'denied' => $filtered()->where('result', ScanResult::Denied)->count(),
            'forced' => $filtered()->where('forced', true)->count(),
            'manual' => $filtered()->where('method', ScanMethod::Plate)->count(),
            'located' => $filtered()->whereNotNull('latitude')->count(),
        ];

        // Points de la carte : les 500 derniers scans géolocalisés du filtre.
        $points = $filtered()
            ->whereNotNull('latitude')
            ->with('pass')
            ->latest('scanned_at')
            ->limit(500)
            ->get()
            ->map(fn (Scan $scan) => [
                'lat' => $scan->latitude,
                'lng' => $scan->longitude,
                'accuracy' => $scan->location_accuracy,
                'granted' => $scan->result === ScanResult::Granted,
                'label' => sprintf('%s · %s · %s', $scan->scanned_at->format('d/m H:i'), $scan->direction->label(), $scan->pass?->number ?? 'QR inconnu'),
            ]);

        return view('users.scans', [
            'user' => $user,
            'scans' => $scans,
            'summary' => $summary,
            'points' => $points,
            'events' => Event::whereIn('id', Scan::where('user_id', $user->id)->select('event_id'))->orderByDesc('starts_at')->get(['id', 'name']),
        ]);
    }
}
