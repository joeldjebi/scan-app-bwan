<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pour les formulaires envoyés en AJAX, transforme la redirection habituelle
 * (« back()->with('success', …) ») en JSON : message à afficher et page à recharger.
 * Sans JavaScript, le comportement classique est inchangé.
 */
class AjaxRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->ajax() || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $session = $request->session();
        $error = $session->pull('error');
        $success = $session->pull('success');

        return new JsonResponse([
            'ok' => $error === null,
            'message' => $error ?? $success,
            'redirect' => $response->getTargetUrl(),
        ], $error === null ? 200 : 422);
    }
}
