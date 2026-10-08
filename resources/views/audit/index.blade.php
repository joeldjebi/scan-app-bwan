@extends('layouts.app', ['title' => 'Journal'])

@php
    use App\Models\AuditLog;

    $input = 'rounded-lg border border-slate-300 px-3 py-2 text-sm';
    $fieldLabels = [
        'name' => 'Nom', 'code' => 'Code', 'location' => 'Lieu', 'starts_at' => 'Début', 'ends_at' => 'Fin',
        'status' => 'Statut', 'description' => 'Description', 'logo_path' => 'Logo', 'poster_path' => 'Affiche',
        'theme_from_poster' => 'Couleurs auto.', 'primary_color' => 'Couleur principale', 'secondary_color' => 'Couleur d\'accent',
        'color' => 'Couleur', 'plate' => 'Immatriculation', 'brand' => 'Marque', 'phone' => 'Téléphone', 'email' => 'Email',
        'role' => 'Rôle', 'is_active' => 'Actif', 'is_owner' => 'Propriétaire', 'password' => 'Mot de passe',
        'number' => 'Numéro', 'token' => 'Jeton QR', 'sequence' => 'Séquence', 'registered_at' => 'Enregistré le',
        'pass_type_id' => 'Type (id)', 'event_id' => 'Événement (id)', 'pass_id' => 'Pass (id)', 'deleted_at' => 'Supprimé le',
    ];
    $display = fn ($value) => match (true) {
        $value === null || $value === '' => '—',
        is_bool($value) => $value ? 'Oui' : 'Non',
        is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
        default => (string) $value,
    };
    $categoryStyles = [
        'auth' => 'bg-sky-100 text-sky-800', 'access' => 'bg-red-100 text-red-800', 'event' => 'bg-indigo-100 text-indigo-800',
        'pass_type' => 'bg-violet-100 text-violet-800', 'pass' => 'bg-amber-100 text-amber-800', 'vehicle' => 'bg-teal-100 text-teal-800',
        'staff' => 'bg-fuchsia-100 text-fuchsia-800', 'scan' => 'bg-emerald-100 text-emerald-800', 'user' => 'bg-slate-200 text-slate-800',
        'brand' => 'bg-lime-100 text-lime-800', 'export' => 'bg-orange-100 text-orange-800',
    ];
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Journal</h1>
            <p class="text-sm text-slate-500">Toutes les actions effectuées sur la plateforme. Visible uniquement par vous, le propriétaire. Les entrées ne peuvent être ni modifiées ni supprimées.</p>
        </div>
        <a href="{{ route('audit.export', request()->query()) }}" data-download="Export du journal" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Exporter (CSV)</a>
    </div>

    @if ($securityAlerts)
        <a href="{{ route('audit.index', ['security' => 1, 'from' => now()->subDay()->toDateString()]) }}"
           class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 hover:bg-red-100">
            <svg class="size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.49 2.87a1.75 1.75 0 0 1 3.02 0l6.28 10.88A1.75 1.75 0 0 1 16.28 16.4H3.72a1.75 1.75 0 0 1-1.51-2.63L8.49 2.87ZM10 6.5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6.5Zm0 7.25a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
            <span><strong>{{ $securityAlerts }}</strong> événement(s) de sécurité ces dernières 24 h (échecs de connexion, accès refusés…). Cliquez pour les voir.</span>
        </a>
    @endif

    <form class="mb-4 flex flex-wrap items-end gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Rechercher (texte, objet, auteur, IP)" class="{{ $input }} w-64">
        <select name="category" class="{{ $input }}">
            <option value="">Toutes les actions</option>
            @foreach (AuditLog::CATEGORIES as $key => $label)
                <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="channel" class="{{ $input }}">
            <option value="">Tous les canaux</option>
            @foreach (AuditLog::CHANNELS as $key => $label)
                <option value="{{ $key }}" @selected(request('channel') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="user" class="{{ $input }}">
            <option value="">Tous les auteurs</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected((string) request('user') === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <select name="event" class="{{ $input }}">
            <option value="">Tous les événements</option>
            @foreach ($events as $event)
                <option value="{{ $event->id }}" @selected((string) request('event') === (string) $event->id)>{{ $event->name }}</option>
            @endforeach
        </select>
        <label class="text-xs text-slate-500">Du <input type="date" name="from" value="{{ request('from') }}" class="{{ $input }} ml-1"></label>
        <label class="text-xs text-slate-500">au <input type="date" name="to" value="{{ request('to') }}" class="{{ $input }} ml-1"></label>
        <label class="flex items-center gap-1.5 text-sm text-slate-700">
            <input type="checkbox" name="security" value="1" @checked(request()->boolean('security')) class="rounded border-slate-300"> Sécurité seulement
        </label>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filtrer</button>
        @if (array_filter(request()->only('q', 'category', 'channel', 'user', 'event', 'from', 'to', 'security')))
            <a href="{{ route('audit.index') }}" class="px-2 py-2 text-sm text-slate-500 hover:underline">Réinitialiser</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Date</th>
                    <th class="px-4 py-2 font-medium">Auteur</th>
                    <th class="px-4 py-2 font-medium">Action</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Canal</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">IP</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            @forelse ($logs as $log)
                <tbody x-data="{ open: false }" class="border-t border-slate-100 {{ $log->isSecurityRelated() ? 'bg-red-50/40' : '' }}">
                    <tr class="cursor-pointer align-top hover:bg-slate-50" @click="open = !open">
                        <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">
                            {{ $log->created_at->format('d/m/Y') }}<br><span class="font-mono text-xs">{{ $log->created_at->format('H:i:s') }}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <p class="font-medium">{{ $log->actor_name ?? 'Anonyme' }}</p>
                            <p class="text-xs text-slate-500">{{ $log->actor_role ? (\App\Enums\UserRole::tryFrom($log->actor_role)?->label() ?? $log->actor_role) : ($log->channel === 'public' ? 'Usager' : 'Non connecté') }}</p>
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $categoryStyles[$log->category()] ?? 'bg-slate-100 text-slate-700' }}">{{ AuditLog::CATEGORIES[$log->category()] ?? $log->category() }}</span>
                            <p class="mt-1 text-slate-800">{{ $log->description }}</p>
                            @if ($log->event)
                                <p class="text-xs text-slate-500">{{ $log->event->name }}</p>
                            @endif
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-2.5 text-slate-600 lg:table-cell">
                            {{ AuditLog::CHANNELS[$log->channel] ?? $log->channel }}
                            @if ($log->device) <p class="text-xs text-slate-500">{{ $log->device }}</p> @endif
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-2.5 font-mono text-xs text-slate-600 md:table-cell">{{ $log->ip_address ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <svg class="inline size-4 text-slate-400 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.2 7.2a.75.75 0 0 1 1.06 0L10 10.94l3.74-3.74a.75.75 0 1 1 1.06 1.06l-4.27 4.27a.75.75 0 0 1-1.06 0L5.2 8.26a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </td>
                    </tr>
                    <tr x-show="open" x-cloak>
                        <td colspan="6" class="bg-slate-50 px-4 py-4">
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="space-y-3">
                                    @if ($log->changes)
                                        <div>
                                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Modifications</p>
                                            <table class="w-full overflow-hidden rounded-lg border border-slate-200 bg-white text-xs">
                                                <thead class="bg-slate-100 text-left text-slate-500">
                                                    <tr><th class="px-3 py-1.5">Champ</th><th class="px-3 py-1.5">Avant</th><th class="px-3 py-1.5">Après</th></tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach ($log->changes as $field => [$before, $after])
                                                        <tr>
                                                            <td class="px-3 py-1.5 font-medium">{{ $fieldLabels[$field] ?? $field }}</td>
                                                            <td class="break-all px-3 py-1.5 text-red-700">{{ $display($before) }}</td>
                                                            <td class="break-all px-3 py-1.5 text-emerald-700">{{ $display($after) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                    @if ($log->properties)
                                        <div>
                                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Détails</p>
                                            <dl class="space-y-1 rounded-lg border border-slate-200 bg-white p-3 text-xs">
                                                @foreach ($log->properties as $key => $value)
                                                    <div class="flex gap-2">
                                                        <dt class="shrink-0 font-medium text-slate-600">{{ str_replace('_', ' ', ucfirst($key)) }} :</dt>
                                                        <dd class="break-all text-slate-800">
                                                            @if (is_array($value) && array_is_list($value))
                                                                <ul class="list-disc pl-4">@foreach ($value as $item)<li>{{ $display($item) }}</li>@endforeach</ul>
                                                            @else
                                                                {{ $display($value) }}
                                                            @endif
                                                        </dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @endif
                                    @unless ($log->changes || $log->properties)
                                        <p class="text-xs text-slate-500">Aucun détail supplémentaire.</p>
                                    @endunless
                                </div>
                                <dl class="space-y-1.5 rounded-lg border border-slate-200 bg-white p-3 text-xs">
                                    <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Date exacte</dt><dd class="font-mono">{{ $log->created_at->format('d/m/Y H:i:s') }} ({{ config('app.timezone') }})</dd></div>
                                    <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Action</dt><dd class="font-mono">{{ $log->action }}</dd></div>
                                    <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Objet</dt><dd>{{ $log->subject_type ? $log->subject_type.' #'.$log->subject_id : '—' }} {{ $log->subject_label ? '« '.$log->subject_label.' »' : '' }}</dd></div>
                                    <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Auteur</dt><dd>{{ $log->actor_name ?? '—' }} {{ $log->user_id ? '(compte #'.$log->user_id.')' : '' }}</dd></div>
                                    <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Canal</dt><dd>{{ AuditLog::CHANNELS[$log->channel] ?? $log->channel }}</dd></div>
                                    <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Appareil</dt><dd>{{ $log->device ?? '—' }}</dd></div>
                                    <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Adresse IP</dt><dd class="font-mono">{{ $log->ip_address ?? '—' }}</dd></div>
                                    <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Requête</dt><dd class="break-all font-mono">{{ $log->method ? $log->method.' '.$log->url : '—' }}</dd></div>
                                    <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">Navigateur</dt><dd class="break-all">{{ $log->user_agent ?? '—' }}</dd></div>
                                </dl>
                            </div>
                        </td>
                    </tr>
                </tbody>
            @empty
                <tbody><tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Aucune entrée pour ces filtres.</td></tr></tbody>
            @endforelse
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
