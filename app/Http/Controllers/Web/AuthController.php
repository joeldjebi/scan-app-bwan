<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Email (administrateurs) ou numéro de téléphone (chefs et agents).
        $identifier = str_contains($data['login'], '@')
            ? ['email' => trim($data['login'])]
            : ['phone' => User::normalizePhone($data['login'])];

        $credentials = [...$identifier, 'password' => $data['password'], 'is_active' => true];

        if (in_array(null, $identifier, true) || ! Auth::attempt($credentials, $request->boolean('remember'))) {
            app(AuditLogger::class)->record('auth.login_failed', "Échec de connexion au back-office avec « {$data['login']} »", properties: ['identifiant' => $data['login']]);

            throw ValidationException::withMessages(['login' => 'Identifiants incorrects ou compte désactivé.']);
        }

        $request->session()->regenerate();
        app(AuditLogger::class)->record('auth.login', 'Connexion au back-office', $request->user());

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        app(AuditLogger::class)->record('auth.logout', 'Déconnexion du back-office', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
