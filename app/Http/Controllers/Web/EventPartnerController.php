<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Partner;
use App\Services\PartnerAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Accès des partenaires à un événement, géré par l'admin ou le chef de l'événement.
 */
class EventPartnerController extends Controller
{
    public function __construct(private PartnerAccess $access) {}

    public function store(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'partner_id' => ['required', 'integer', Rule::exists('partners', 'id')->where('is_active', true)],
        ]);

        $partner = Partner::findOrFail($data['partner_id']);
        $this->access->grant($event, $partner, $request->user());

        return back()->with('success', "{$partner->name} peut désormais scanner les pass de cet événement.");
    }

    public function destroy(Event $event, Partner $partner): RedirectResponse
    {
        $this->access->revoke($event, $partner);

        return back()->with('success', "{$partner->name} ne peut plus scanner les pass de cet événement.");
    }
}
