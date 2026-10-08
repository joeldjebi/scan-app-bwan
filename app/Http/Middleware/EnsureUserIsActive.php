<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $token = $user->currentAccessToken();

                if ($token instanceof PersonalAccessToken) {
                    $token->delete();
                }

                abort(403, 'Compte désactivé.');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['login' => 'Votre compte est désactivé.']);
        }

        return $next($request);
    }
}
