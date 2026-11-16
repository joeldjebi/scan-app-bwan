<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Scan;
use App\Services\ScanHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Historique des passages enregistrés par un partenaire via l'API, tous événements confondus.
 */
class PartnerScanController extends Controller
{
    public function index(Request $request, Partner $partner, ScanHistory $history): View
    {
        $request->validate(ScanHistory::rules());

        return view('partners.scans', [
            'partner' => $partner,
            'operators' => $partner->scans()->whereNotNull('operator_ref')->distinct()->orderBy('operator_ref')->pluck('operator_ref'),
            ...$history->build($request, Scan::where('partner_id', $partner->id)),
        ]);
    }
}
