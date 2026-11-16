<?php

namespace App\Http\Controllers\Web;

use App\Enums\EventStatus;
use App\Enums\PartnerAbility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Partner;
use App\Models\PartnerApiKey;
use App\Services\AuditLogger;
use App\Services\PartnerAccess;
use App\Services\PartnerWebhooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Partenaires extérieurs de l'API : fiche, accès aux événements, clés API et webhooks (admin).
 */
class PartnerController extends Controller
{
    public function __construct(private PartnerAccess $access) {}

    public function index(Request $request): View
    {
        $partners = Partner::query()
            ->when($request->q, fn ($query, $q) => $query->where('name', 'like', "%{$q}%"))
            ->withCount(['events', 'scans', 'apiKeys as active_keys_count' => fn ($query) => $query->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('partners.index', compact('partners'));
    }

    public function create(): View
    {
        return $this->form(new Partner(['is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $partner = Partner::create($data);
        $this->access->sync($partner, $data['event_ids'] ?? [], $request->user());

        return redirect()->route('partners.show', $partner)->with('success', 'Partenaire créé. Générez maintenant sa clé API.');
    }

    public function show(Partner $partner): View
    {
        $partner->load(['events' => fn ($query) => $query->orderByDesc('starts_at')]);

        return view('partners.show', [
            'partner' => $partner,
            'keys' => $partner->apiKeys()->latest()->get(),
            'deliveries' => $partner->webhookDeliveries()->latest()->limit(15)->get(),
            'scansCount' => $partner->scans()->count(),
            'recentScans' => $partner->scans()->with(['event', 'partner', 'pass.type', 'pass.vehicle'])->latest('scanned_at')->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(Partner $partner): View
    {
        return $this->form($partner);
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        $data = $this->validated($request);
        $partner->update($data);
        $this->access->sync($partner, $data['event_ids'] ?? [], $request->user());

        return redirect()->route('partners.show', $partner)->with('success', 'Partenaire mis à jour.');
    }

    /**
     * Génère une clé API : elle n'est affichée qu'une seule fois.
     */
    public function storeKey(Request $request, Partner $partner): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::enum(PartnerAbility::class)],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'allowed_ips' => ['nullable', 'string', 'max:1000'],
        ], [
            'abilities.required' => 'Choisissez au moins un droit pour la clé.',
        ]);

        $ips = collect(preg_split('/[\s,;]+/', $data['allowed_ips'] ?? '', -1, PREG_SPLIT_NO_EMPTY))->values();
        $invalid = $ips->reject(fn (string $ip) => filter_var(explode('/', $ip)[0], FILTER_VALIDATE_IP));

        if ($invalid->isNotEmpty()) {
            return back()->withInput()->withErrors(['allowed_ips' => 'Adresse IP invalide : '.$invalid->implode(', ')]);
        }

        [, $plain] = $partner->issueApiKey(
            $data['name'],
            array_map(fn (string $ability) => PartnerAbility::from($ability), $data['abilities']),
            isset($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null,
            $ips->all(),
        );

        return back()->with('plain_key', $plain);
    }

    public function revokeKey(Partner $partner, PartnerApiKey $apiKey): RedirectResponse
    {
        $apiKey->revoke();

        return back()->with('success', "Clé « {$apiKey->name} » révoquée : elle est refusée immédiatement.");
    }

    /**
     * Génère un nouveau secret de signature : l'ancien cesse aussitôt d'être utilisé.
     */
    public function rotateWebhookSecret(Partner $partner): RedirectResponse
    {
        $secret = $partner->rotateWebhookSecret();
        app(AuditLogger::class)->record('partner.webhook_secret_rotated', "Nouveau secret de webhook pour « {$partner->name} »", $partner);

        return back()->with('webhook_secret', $secret);
    }

    public function testWebhook(Partner $partner, PartnerWebhooks $webhooks): RedirectResponse
    {
        if (! $partner->hasWebhook()) {
            return back()->with('error', 'Renseignez l\'URL et générez le secret du webhook avant de le tester.');
        }

        $webhooks->send($partner, PartnerWebhooks::PING, ['message' => 'Test de réception du webhook Pass Parking.']);

        return back()->with('success', 'Webhook de test envoyé : son résultat apparaît dans la liste des envois.');
    }

    /**
     * Événements proposés : en cours ou à venir, plus ceux (même clôturés) déjà ouverts au partenaire.
     */
    private function form(Partner $partner): View
    {
        $granted = $partner->exists ? $partner->events()->pluck('events.id') : collect();

        $events = Event::query()
            ->where(fn ($query) => $query->where('status', '!=', EventStatus::Closed)->orWhereIn('id', $granted))
            ->orderByDesc('starts_at')
            ->get();

        return view('partners.form', compact('partner', 'events', 'granted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'webhook_url' => ['nullable', 'url:https,http', 'max:500'],
            'is_active' => ['boolean'],
            'event_ids' => ['nullable', 'array'],
            'event_ids.*' => ['integer', Rule::exists('events', 'id')],
        ]) + ['is_active' => false];
    }
}
