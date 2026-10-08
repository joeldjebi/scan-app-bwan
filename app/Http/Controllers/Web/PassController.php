<?php

namespace App\Http\Controllers\Web;

use App\Enums\Direction;
use App\Enums\PassStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Pass;
use App\Models\Vehicle;
use App\Services\DeletionService;
use App\Services\QrCode;
use App\Services\VehicleRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PassController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        $passes = $this->filtered($request, $event)
            ->with(['type', 'vehicle'])
            ->orderBy('pass_type_id')
            ->orderBy('sequence')
            ->paginate(50)
            ->withQueryString();

        return view('passes.index', [
            'event' => $event,
            'passes' => $passes,
            'types' => $event->passTypes,
        ]);
    }

    public function show(Event $event, Pass $pass): View
    {
        $pass->load(['type', 'vehicle', 'scans' => fn ($query) => $query->with('agent')->latest('scanned_at')]);

        return view('passes.show', [
            'event' => $event,
            'pass' => $pass,
            'brands' => VehicleRegistration::brands(),
            'colors' => VehicleRegistration::colors(),
        ]);
    }

    public function qr(Event $event, Pass $pass): Response
    {
        return response(QrCode::svg($pass->url(), 300), 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function updateVehicle(Request $request, Event $event, Pass $pass, VehicleRegistration $registration): RedirectResponse
    {
        $data = $request->validate(
            VehicleRegistration::rules(),
            VehicleRegistration::messages(),
            VehicleRegistration::attributes(),
        );

        $registration->save($pass, $data);

        return back()->with('success', 'Véhicule enregistré.');
    }

    public function revoke(Event $event, Pass $pass): RedirectResponse
    {
        $pass->update(['status' => PassStatus::Revoked]);

        return back()->with('success', "Pass {$pass->number} révoqué : il sera refusé au scan.");
    }

    public function restore(Event $event, Pass $pass): RedirectResponse
    {
        $pass->update(['status' => $pass->vehicle ? PassStatus::Registered : PassStatus::Pending]);

        return back()->with('success', "Pass {$pass->number} réactivé.");
    }

    /**
     * Supprime le véhicule : le pass redevient vierge et peut être réenregistré par l'usager.
     */
    public function reset(Event $event, Pass $pass): RedirectResponse
    {
        $pass->vehicle?->delete();
        $pass->update([
            'status' => $pass->status === PassStatus::Revoked ? PassStatus::Revoked : PassStatus::Pending,
            'registered_at' => null,
            'presence' => Direction::Out,
        ]);

        return back()->with('success', "Pass {$pass->number} réinitialisé.");
    }

    public function destroy(Event $event, Pass $pass, DeletionService $deletion): RedirectResponse
    {
        $deletion->deletePasses(collect([$pass]));

        return redirect()->route('events.passes.index', $event)->with('success', "Pass {$pass->number} supprimé.");
    }

    /**
     * Suppression en masse : les pass cochés, ou tous ceux correspondant aux filtres de la liste.
     */
    public function bulkDestroy(Request $request, Event $event, DeletionService $deletion): RedirectResponse
    {
        $request->validate([
            'ids' => ['required_without:all_matching', 'array'],
            'ids.*' => ['integer'],
            'all_matching' => ['boolean'],
        ], ['ids.required_without' => 'Sélectionnez au moins un pass.']);

        $passes = $request->boolean('all_matching')
            ? self::filtered($request, $event)->get(['id'])
            : $event->passes()->whereIn('id', $request->ids)->get(['id']);

        $count = $deletion->deletePasses($passes);

        return redirect()
            ->route('events.passes.index', [$event, ...$request->only('q', 'type', 'status', 'presence')])
            ->with('success', "{$count} pass supprimé(s).");
    }

    /**
     * Filtres partagés entre la liste et les exports.
     */
    public static function filtered(Request $request, Event $event)
    {
        return $event->passes()
            ->when($request->type, fn ($query, $type) => $query->where('pass_type_id', $type))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->presence, fn ($query, $presence) => $query->where('presence', $presence))
            ->when($request->q, fn ($query, $q) => $query->where(fn ($sub) => $sub
                ->where('number', 'like', '%'.$q.'%')
                ->orWhereHas('vehicle', fn ($v) => $v
                    ->where('plate_key', 'like', '%'.Vehicle::plateKey($q).'%')
                    ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $q).'%'))));
    }
}
