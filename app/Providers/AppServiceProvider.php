<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        // Les QR codes imprimés encodent des URL absolues : elles doivent toujours
        // utiliser le domaine de production (APP_URL), quel que soit l'hôte de la requête.
        URL::forceRootUrl(config('app.url'));
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Admin : gestion des événements, des pass, des QR codes et des comptes.
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Propriétaire (premier administrateur) : journal d'audit.
        Gate::define('owner', fn (User $user) => $user->isOwner());

        // Admin ou membre de l'équipe de l'événement (chef ou agent) : scanner.
        Gate::define('operate', fn (User $user, Event $event) => $user->canOperate($event));

        // Admin ou chef agent de l'événement : supervision, révocation, passage forcé.
        Gate::define('supervise', fn (User $user, Event $event) => $user->canSupervise($event));

        // API partenaire : quota par clé API (l'authentification s'exécute avant).
        RateLimiter::for('partner-api', fn (Request $request) => Limit::perMinute(config('parking.partner_rate_limit'))
            ->by('partner-key:'.($request->attributes->get('partner_api_key')?->id ?? $request->ip()))
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => 'Trop de requêtes : réessayez plus tard.',
                'code' => 'rate_limited',
            ], 429, $headers)));
    }
}
