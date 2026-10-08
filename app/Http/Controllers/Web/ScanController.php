<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Scan;
use App\Services\DeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        $scans = $event->scans()
            ->with(['agent', 'pass.vehicle', 'pass.type'])
            ->when($request->result, fn ($query, $result) => $query->where('result', $result))
            ->when($request->direction, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($request->agent, fn ($query, $agent) => $query->where('user_id', $agent))
            ->latest('scanned_at')
            ->paginate(50)
            ->withQueryString();

        return view('scans.index', [
            'event' => $event,
            'scans' => $scans,
            'agents' => $event->staff()->orderBy('name')->get(),
        ]);
    }

    public function destroy(Event $event, Scan $scan, DeletionService $deletion): RedirectResponse
    {
        $deletion->deleteScans(collect([$scan]));

        return back()->with('success', 'Passage supprimé.');
    }

    public function bulkDestroy(Request $request, Event $event, DeletionService $deletion): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Sélectionnez au moins un passage.']);

        $count = $deletion->deleteScans($event->scans()->whereIn('id', $request->ids)->get(['id', 'pass_id']));

        return back()->with('success', "{$count} passage(s) supprimé(s).");
    }
}
