@extends('layouts.app', ['title' => $partner->name])

@php($input = 'rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('partners.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Partenaires</a>
            <h1 class="mt-1 flex items-center gap-3 text-2xl font-bold">
                {{ $partner->name }}
                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $partner->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">{{ $partner->is_active ? 'Actif' : 'Suspendu' }}</span>
            </h1>
            <p class="text-sm text-slate-500">
                {{ $partner->contact_name ?? 'Aucun contact' }}@if ($partner->contact_email) · {{ $partner->contact_email }}@endif @if ($partner->contact_phone) · {{ $partner->contact_phone }}@endif
                · {{ number_format($scansCount, 0, ',', ' ') }} passage(s)@if ($recentScans->isNotEmpty()), dernier le {{ $recentScans->first()->scanned_at->translatedFormat('d M Y à H\hi') }}@endif
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('partners.scans', $partner) }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Historique des passages</a>
            @if (config('parking.api_docs'))
                <a href="{{ route('api-docs.partner') }}" target="_blank" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Documentation API</a>
            @endif
            <a href="{{ route('partners.edit', $partner) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Modifier</a>
        </div>
    </div>

    @if (session('plain_key'))
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4" x-data="{ copied: false }">
            <p class="text-sm font-semibold text-amber-900">Nouvelle clé API : copiez-la maintenant, elle ne sera plus jamais affichée.</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <code class="break-all rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-amber-200">{{ session('plain_key') }}</code>
                <button type="button" @click="navigator.clipboard.writeText(@js(session('plain_key'))); copied = true" class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-700" x-text="copied ? 'Copiée' : 'Copier'">Copier</button>
            </div>
            <p class="mt-2 text-xs text-amber-800">Transmettez-la au partenaire par un canal sûr. À utiliser dans l'en-tête <code>Authorization: Bearer &lt;clé&gt;</code>.</p>
        </div>
    @endif
    @if (session('webhook_secret'))
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-900">Nouveau secret de webhook : copiez-le maintenant, il ne sera plus affiché.</p>
            <code class="mt-2 block break-all rounded-lg bg-white px-3 py-2 font-mono text-sm text-slate-900 ring-1 ring-amber-200">{{ session('webhook_secret') }}</code>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Clés API --}}
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-slate-200 px-5 py-3">
                <h2 class="font-semibold">Clés API</h2>
                <p class="text-xs text-slate-500">Une clé par application ou environnement du partenaire. Pour changer de clé sans coupure : créez la nouvelle, puis révoquez l'ancienne.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-2 font-medium">Nom</th>
                            <th class="px-5 py-2 font-medium">Droits</th>
                            <th class="px-5 py-2 font-medium">Dernière utilisation</th>
                            <th class="px-5 py-2 font-medium">État</th>
                            <th class="px-5 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($keys as $key)
                            <tr>
                                <td class="px-5 py-2">
                                    <p class="font-medium">{{ $key->name }}</p>
                                    <p class="font-mono text-xs text-slate-500">{{ $key->prefix }}…</p>
                                    @if ($key->allowed_ips)<p class="text-xs text-slate-500">IP : {{ implode(', ', $key->allowed_ips) }}</p>@endif
                                </td>
                                <td class="px-5 py-2 text-xs text-slate-600">
                                    @foreach ($key->abilities as $ability)
                                        <span class="block">{{ \App\Enums\PartnerAbility::tryFrom($ability)?->label() ?? $ability }}</span>
                                    @endforeach
                                </td>
                                <td class="whitespace-nowrap px-5 py-2 text-xs text-slate-600">
                                    {{ $key->last_used_at?->translatedFormat('d M Y H\hi') ?? 'Jamais' }}
                                    @if ($key->last_used_ip)<span class="block font-mono text-slate-400">{{ $key->last_used_ip }}</span>@endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-2">
                                    @if ($key->isRevoked())
                                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Révoquée</span>
                                    @elseif ($key->isExpired())
                                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600">Expirée</span>
                                    @else
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Active</span>
                                        @if ($key->expires_at)<span class="block text-xs text-slate-500">jusqu'au {{ $key->expires_at->translatedFormat('d M Y') }}</span>@endif
                                    @endif
                                </td>
                                <td class="px-5 py-2 text-right">
                                    @if ($key->isUsable())
                                        <form method="POST" action="{{ route('partners.keys.revoke', [$partner, $key]) }}" onsubmit="return confirm('Révoquer la clé « {{ $key->name }} » ? Les appels qui l\'utilisent seront refusés immédiatement.')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-red-600 hover:underline">Révoquer</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-6 text-center text-slate-500">Aucune clé : le partenaire ne peut pas encore appeler l'API.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route('partners.keys.store', $partner) }}" class="space-y-3 border-t border-slate-200 bg-slate-50 px-5 py-4">
                @csrf
                <p class="text-sm font-medium">Générer une clé</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Nom</label>
                        <input name="name" value="{{ old('name') }}" placeholder="Production, Tablette entrée nord…" required maxlength="100" class="{{ $input }} mt-1 w-full">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Expire le <span class="font-normal">(facultatif)</span></label>
                        <input type="date" name="expires_at" value="{{ old('expires_at') }}" min="{{ now()->addDay()->toDateString() }}" class="{{ $input }} mt-1 w-full">
                    </div>
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-1">
                    @foreach (\App\Enums\PartnerAbility::cases() as $ability)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="abilities[]" value="{{ $ability->value }}" @checked(in_array($ability->value, old('abilities', \App\Enums\PartnerAbility::values()), true)) class="rounded border-slate-300">
                            {{ $ability->label() }}
                        </label>
                    @endforeach
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">IP autorisées <span class="font-normal">(facultatif : adresses ou plages CIDR, séparées par des virgules)</span></label>
                    <input name="allowed_ips" value="{{ old('allowed_ips') }}" placeholder="41.202.10.5, 10.0.0.0/8" class="{{ $input }} mt-1 w-full font-mono">
                </div>
                <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Générer la clé</button>
            </form>
        </section>

        {{-- Événements --}}
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                <h2 class="font-semibold">Événements ouverts</h2>
                <a href="{{ route('partners.edit', $partner) }}" class="text-sm text-indigo-600 hover:underline">Gérer</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($partner->events as $event)
                    <li class="px-5 py-3">
                        <a href="{{ route('events.show', $event) }}" class="text-sm font-medium hover:text-indigo-600 hover:underline">{{ $event->name }}</a>
                        <p class="text-xs text-slate-500">{{ $event->starts_at->translatedFormat('d M Y') }} · {{ $event->status->label() }}</p>
                    </li>
                @empty
                    <li class="px-5 py-6 text-sm text-slate-500">Aucun événement : le partenaire ne peut scanner aucun pass.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- Derniers passages --}}
    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="font-semibold">Derniers passages</h2>
            <a href="{{ route('partners.scans', $partner) }}" class="text-sm text-indigo-600 hover:underline">Tout voir</a>
        </div>
        @include('scans._table', ['scans' => $recentScans, 'showEvent' => true])
    </section>

    {{-- Webhooks --}}
    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
            <div>
                <h2 class="font-semibold">Webhooks</h2>
                <p class="text-xs text-slate-500">
                    @if ($partner->webhook_url)
                        <span class="font-mono">{{ $partner->webhook_url }}</span> · {{ $partner->webhook_secret ? 'secret configuré' : 'secret à générer' }}
                    @else
                        Aucune URL : <a href="{{ route('partners.edit', $partner) }}" class="text-indigo-600 hover:underline">la renseigner</a> pour notifier le partenaire des révocations de pass.
                    @endif
                </p>
            </div>
            <div class="flex gap-2 text-sm">
                <form method="POST" action="{{ route('partners.webhook.secret', $partner) }}" @if ($partner->webhook_secret) onsubmit="return confirm('Générer un nouveau secret ? Le partenaire devra le mettre à jour pour vérifier les signatures.')" @endif>
                    @csrf
                    <button class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50">{{ $partner->webhook_secret ? 'Nouveau secret' : 'Générer le secret' }}</button>
                </form>
                @if ($partner->hasWebhook())
                    <form method="POST" action="{{ route('partners.webhook.test', $partner) }}">
                        @csrf
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Envoyer un test</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-2 font-medium">Date</th>
                        <th class="px-5 py-2 font-medium">Type</th>
                        <th class="px-5 py-2 font-medium">Statut</th>
                        <th class="px-5 py-2 font-medium">Tentatives</th>
                        <th class="px-5 py-2 font-medium">Dernière réponse</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($deliveries as $delivery)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-2">{{ $delivery->created_at->translatedFormat('d M Y H\hi') }}</td>
                            <td class="px-5 py-2 font-mono text-xs">{{ $delivery->type }}</td>
                            <td class="px-5 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ match ($delivery->status) { 'delivered' => 'bg-emerald-100 text-emerald-800', 'failed' => 'bg-red-100 text-red-800', default => 'bg-slate-100 text-slate-600' } }}">{{ match ($delivery->status) { 'delivered' => 'Livré', 'failed' => 'Échec', default => 'En cours' } }}</span>
                            </td>
                            <td class="px-5 py-2">{{ $delivery->attempts }}</td>
                            <td class="px-5 py-2 text-xs text-slate-600">{{ $delivery->response_status ? 'HTTP '.$delivery->response_status : '' }} {{ $delivery->last_error }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-6 text-center text-slate-500">Aucun envoi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
