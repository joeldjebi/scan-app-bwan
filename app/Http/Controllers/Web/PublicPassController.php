<?php

namespace App\Http\Controllers\Web;

use App\Enums\PassStatus;
use App\Http\Controllers\Controller;
use App\Models\Pass;
use App\Services\VehicleRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page ouverte par l'usager en scannant son QR code.
 */
class PublicPassController extends Controller
{
    public function show(string $token): View
    {
        $pass = $this->find($token);

        return view('public.pass', [
            'pass' => $pass,
            'event' => $pass->event,
            'brands' => VehicleRegistration::brands(),
            'colors' => VehicleRegistration::colors(),
        ]);
    }

    public function register(Request $request, string $token, VehicleRegistration $registration): RedirectResponse
    {
        $pass = $this->find($token);

        // Une fois enregistré, seul l'admin ou le chef agent peut modifier le véhicule.
        abort_unless($pass->status === PassStatus::Pending && ! $pass->event->isClosed(), 403);

        $data = $request->validate(
            VehicleRegistration::rules(),
            VehicleRegistration::messages(),
            VehicleRegistration::attributes(),
        );

        $registration->save($pass, $data);

        return redirect()->route('public.pass', $pass->token)->with('registered', true);
    }

    private function find(string $token): Pass
    {
        return Pass::with(['event', 'type', 'vehicle'])->where('token', $token)->firstOrFail();
    }
}
