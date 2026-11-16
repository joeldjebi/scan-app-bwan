<?php

namespace App\Http\Middleware;

use App\Enums\PartnerAbility;
use App\Models\Partner;
use App\Models\PartnerApiKey;
use Closure;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifie un partenaire par sa clé API (`Authorization: Bearer ppk_…` ou `X-Api-Key`) et
 * vérifie le droit demandé par la route (ex. `partner:scans:write`).
 *
 * Implémente AuthenticatesRequests pour s'exécuter avant la limite de débit, calculée par clé.
 */
class AuthenticatePartner implements AuthenticatesRequests
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $plain = $request->bearerToken() ?? $request->header('X-Api-Key');
        $key = $plain ? PartnerApiKey::findByPlainKey($plain) : null;

        if (! $key || ! $key->isUsable() || ! $key->partner->is_active) {
            return self::error(401, 'invalid_api_key', 'Clé API absente, invalide, expirée ou révoquée.');
        }

        if (! $key->acceptsIp($request->ip())) {
            return self::error(403, 'ip_not_allowed', 'Adresse IP non autorisée pour cette clé API.');
        }

        if ($ability !== null && ! $key->allows(PartnerAbility::from($ability))) {
            return self::error(403, 'insufficient_scope', "Cette clé API n'a pas le droit « {$ability} ».");
        }

        // Une écriture par minute au plus : suivi de l'utilisation sans charger la base.
        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }

        $request->attributes->set('partner', $key->partner);
        $request->attributes->set('partner_api_key', $key);

        return $next($request);
    }

    public static function partner(Request $request): Partner
    {
        return $request->attributes->get('partner');
    }

    public static function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code], $status);
    }
}
