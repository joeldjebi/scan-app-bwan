@extends('layouts.app', ['title' => $event->name])

@php($input = 'rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none')

@section('content')
    @include('events._tabs')

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            ['Pass générés', $stats['total']],
            ['Véhicules enregistrés', $stats['registered']],
            ['Dans le parking', $stats['inside']],
            ['Entrées', $stats['entries']],
            ['Refus', $stats['denied']],
            ['Révoqués', $stats['revoked']],
        ] as [$label, $value])
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-bold">{{ number_format($value, 0, ',', ' ') }}</p>
                <p class="text-xs text-slate-500">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Types de pass --}}
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                <h2 class="font-semibold">Types de pass & QR codes</h2>
                <div class="flex flex-wrap gap-2 text-sm">
                    <a href="{{ route('events.export.passes', [$event, 'format' => 'xlsx']) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Export Excel</a>
                    <a href="{{ route('events.export.passes', [$event, 'format' => 'csv']) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Export CSV</a>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($event->passTypes as $type)
                    <div class="px-5 py-4" x-data="{ edit: false }">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="size-4 rounded" style="background: {{ $type->color }}"></span>
                                <div>
                                    <p class="font-medium">{{ $type->name }} <span class="font-mono text-xs text-slate-500">{{ $type->code }}</span></p>
                                    <p class="text-xs text-slate-500">{{ $type->passes_count }} pass · {{ $type->registered_count }} enregistrés · {{ $type->inside_count }} présents</p>
                                </div>
                            </div>
                            @can('admin')
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('events.types.generate', [$event, $type]) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="number" name="count" min="1" max="{{ config('parking.max_generation') }}" value="50" class="{{ $input }} w-24">
                                        <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Générer</button>
                                    </form>
                                    @if ($type->passes_count)
                                        <div class="relative" x-data="{ open: false }">
                                            <button @click="open = !open" class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">QR codes ▾</button>
                                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 z-10 mt-1 w-48 rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                                                <a href="{{ route('events.export.qrcodes', [$event, 'type' => $type->id, 'format' => 'svg']) }}" class="block px-3 py-2 hover:bg-slate-50">ZIP · SVG (vectoriel)</a>
                                                <a href="{{ route('events.export.qrcodes', [$event, 'type' => $type->id, 'format' => 'png']) }}" class="block px-3 py-2 hover:bg-slate-50">ZIP · PNG (1024 px)</a>
                                            </div>
                                        </div>
                                    @endif
                                    <button @click="edit = !edit" class="px-1 text-sm text-slate-500 hover:text-slate-800">Modifier</button>
                                </div>
                            @endcan
                        </div>
                        @can('admin')
                            <div x-show="edit" x-cloak class="mt-3 flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('events.types.update', [$event, $type]) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $type->name }}" required class="{{ $input }}">
                                    <input type="color" name="color" value="{{ $type->color }}" class="h-9 w-12 rounded border border-slate-300">
                                    <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Enregistrer</button>
                                </form>
                                @unless ($type->passes_count)
                                    <form method="POST" action="{{ route('events.types.destroy', [$event, $type]) }}" onsubmit="return confirm('Supprimer ce type ?')">
                                        @csrf @method('DELETE')
                                        <button class="text-sm text-red-600 hover:underline">Supprimer</button>
                                    </form>
                                @endunless
                            </div>
                        @endcan
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-slate-500">Aucun type de pass. Commencez par en créer un (ex. VIP, Staff, Presse).</p>
                @endforelse
            </div>

            @can('admin')
                <form method="POST" action="{{ route('events.types.store', $event) }}" class="flex flex-wrap items-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Nouveau type</label>
                        <input name="name" placeholder="Ex. VIP" required class="{{ $input }} mt-1">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Code</label>
                        <input name="code" placeholder="VIP" required maxlength="10" class="{{ $input }} mt-1 w-24 font-mono uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Couleur</label>
                        <input type="color" name="color" value="#2563eb" class="mt-1 h-9 w-12 rounded border border-slate-300">
                    </div>
                    <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ajouter</button>
                </form>
                @if ($stats['total'])
                    <div class="border-t border-slate-200 px-5 py-3 text-sm">
                        Tous les QR codes :
                        <a href="{{ route('events.export.qrcodes', [$event, 'format' => 'svg']) }}" class="text-indigo-600 hover:underline">ZIP SVG</a> ·
                        <a href="{{ route('events.export.qrcodes', [$event, 'format' => 'png']) }}" class="text-indigo-600 hover:underline">ZIP PNG</a>
                        <span class="text-slate-500">— inclut un fichier passes.csv pour la fusion dans le gabarit.</span>
                    </div>
                @endif
            @endcan
        </section>

        {{-- Équipe --}}
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3">
                <h2 class="font-semibold">Équipe parking</h2>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($event->staff->sortBy(fn ($u) => [$u->pivot->role->value !== 'chief', $u->name]) as $member)
                    <li class="flex items-center justify-between gap-2 px-5 py-3">
                        <div>
                            @can('admin')
                                <a href="{{ route('users.scans', [$member, 'event' => $event->id]) }}" class="text-sm font-medium hover:text-indigo-600 hover:underline">{{ $member->name }}</a>
                            @else
                                <p class="text-sm font-medium">{{ $member->name }}</p>
                            @endcan
                            <p class="text-xs {{ $member->pivot->role === \App\Enums\StaffRole::Chief ? 'font-semibold text-indigo-700' : 'text-slate-500' }}">{{ $member->pivot->role->label() }}</p>
                        </div>
                        @can('admin')
                            <div class="flex items-center gap-2 text-xs">
                                @if ($member->pivot->role !== \App\Enums\StaffRole::Chief)
                                    <form method="POST" action="{{ route('events.staff.update', [$event, $member]) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="role" value="chief">
                                        <button class="text-indigo-600 hover:underline">Nommer chef</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('events.staff.destroy', [$event, $member]) }}" onsubmit="return confirm('Retirer {{ $member->name }} de l\'équipe ?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">Retirer</button>
                                </form>
                            </div>
                        @endcan
                    </li>
                @empty
                    <li class="px-5 py-6 text-sm text-slate-500">Aucun membre affecté.</li>
                @endforelse
            </ul>
            @can('admin')
                @if ($availableAgents->isNotEmpty())
                    <form method="POST" action="{{ route('events.staff.store', $event) }}" class="space-y-2 border-t border-slate-200 bg-slate-50 px-5 py-4">
                        @csrf
                        <label class="block text-xs font-medium text-slate-600">Ajouter des membres (Ctrl/Cmd + clic pour plusieurs)</label>
                        <select name="user_ids[]" multiple size="5" class="{{ $input }} w-full">
                            @foreach ($availableAgents as $agent)
                                <option value="{{ $agent->id }}">{{ $agent->name }}{{ $agent->phone ? ' · '.$agent->phone : '' }}</option>
                            @endforeach
                        </select>
                        <div class="flex gap-2">
                            <select name="role" class="{{ $input }} flex-1">
                                <option value="agent">Agent parking</option>
                                <option value="chief">Chef agent parking</option>
                            </select>
                            <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ajouter</button>
                        </div>
                    </form>
                @else
                    <p class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
                        Tous les agents actifs sont affectés. <a href="{{ route('users.create') }}" class="text-indigo-600 hover:underline">Créer un compte agent</a>
                    </p>
                @endif
            @endcan
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="font-semibold">Derniers passages</h2>
            <a href="{{ route('events.scans.index', $event) }}" class="text-sm text-indigo-600 hover:underline">Tout voir</a>
        </div>
        @include('scans._table', ['scans' => $recentScans])
    </section>
@endsection
