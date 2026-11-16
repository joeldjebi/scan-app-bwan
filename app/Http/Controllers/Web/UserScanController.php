<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Scan;
use App\Models\User;
use App\Services\ScanHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Historique des scans d'un agent ou d'un chef, tous événements confondus.
 */
class UserScanController extends Controller
{
    public function index(Request $request, User $user, ScanHistory $history): View
    {
        $request->validate(ScanHistory::rules());

        return view('users.scans', [
            'user' => $user,
            ...$history->build($request, Scan::where('user_id', $user->id)),
        ]);
    }
}
