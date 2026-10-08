@extends('layouts.app', ['title' => 'Pass · '.$event->name])

@php($input = 'rounded-lg border border-slate-300 px-3 py-2 text-sm')

@section('content')
    @include('events._tabs')

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form class="flex flex-wrap gap-2">
            <input name="q" value="{{ request('q') }}" placeholder="N°, immatriculation, téléphone" class="{{ $input }} w-64">
            <select name="type" class="{{ $input }}">
                <option value="">Tous les types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected((string) request('type') === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
            <select name="status" class="{{ $input }}">
                <option value="">Tous les statuts</option>
                @foreach (\App\Enums\PassStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select name="presence" class="{{ $input }}">
                <option value="">Toutes positions</option>
                <option value="in" @selected(request('presence') === 'in')>Dans le parking</option>
                <option value="out" @selected(request('presence') === 'out')>Dehors</option>
            </select>
            <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filtrer</button>
        </form>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('events.export.passes', [$event, ...request()->only('q', 'type', 'status', 'presence'), 'format' => 'xlsx']) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 hover:bg-slate-50">Excel</a>
            <a href="{{ route('events.export.passes', [$event, ...request()->only('q', 'type', 'status', 'presence'), 'format' => 'csv']) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 hover:bg-slate-50">CSV</a>
        </div>
    </div>

    @can('admin')
        @if ($passes->total())
            <div class="mb-3 flex flex-wrap items-center gap-2 text-sm" x-data="{ count: 0 }" @pass-selection.window="count = $event.detail">
                <form id="bulk-delete-passes" method="POST" action="{{ route('events.passes.bulk-destroy', $event) }}"
                      onsubmit="return confirm('Supprimer définitivement les pass sélectionnés, leur véhicule et leurs passages ?')">
                    @csrf @method('DELETE')
                    <button :disabled="count === 0" class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-red-700 hover:bg-red-50 disabled:opacity-40">
                        Supprimer la sélection (<span x-text="count"></span>)
                    </button>
                </form>
                <form method="POST" action="{{ route('events.passes.bulk-destroy', $event) }}"
                      onsubmit="return confirm('Supprimer définitivement les {{ $passes->total() }} pass correspondant aux filtres, leur véhicule et leurs passages ?')">
                    @csrf @method('DELETE')
                    <input type="hidden" name="all_matching" value="1">
                    @foreach (request()->only('q', 'type', 'status', 'presence') as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button class="rounded-lg px-3 py-1.5 text-red-700 hover:underline">
                        Supprimer les {{ $passes->total() }} pass {{ array_filter(request()->only('q', 'type', 'status', 'presence')) ? 'filtrés' : 'de l\'événement' }}
                    </button>
                </form>
            </div>
        @endif
    @endcan

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm"
         x-data="{ all: false, sync() { $dispatch('pass-selection', $root.querySelectorAll('input[name=\'ids[]\']:checked').length) } }">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    @can('admin')
                        <th class="w-8 px-4 py-2">
                            <input type="checkbox" x-model="all" aria-label="Tout sélectionner"
                                   @change="$root.querySelectorAll('input[name=\'ids[]\']').forEach(box => box.checked = all); sync()">
                        </th>
                    @endcan
                    <th class="px-4 py-2 font-medium">N°</th>
                    <th class="px-4 py-2 font-medium">Type</th>
                    <th class="px-4 py-2 font-medium">Statut</th>
                    <th class="px-4 py-2 font-medium">Immatriculation</th>
                    <th class="px-4 py-2 font-medium">Véhicule</th>
                    <th class="px-4 py-2 font-medium">Téléphone</th>
                    <th class="px-4 py-2 font-medium">Position</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($passes as $pass)
                    <tr class="hover:bg-slate-50">
                        @can('admin')
                            <td class="px-4 py-2"><input type="checkbox" name="ids[]" value="{{ $pass->id }}" form="bulk-delete-passes" @change="sync()" aria-label="Sélectionner {{ $pass->number }}"></td>
                        @endcan
                        <td class="whitespace-nowrap px-4 py-2"><a href="{{ route('events.passes.show', [$event, $pass]) }}" class="font-mono text-indigo-600 hover:underline">{{ $pass->number }}</a></td>
                        <td class="px-4 py-2">@include('partials.type-badge', ['type' => $pass->type])</td>
                        <td class="px-4 py-2">@include('partials.status-badge', ['status' => $pass->status])</td>
                        <td class="whitespace-nowrap px-4 py-2 font-mono">{{ $pass->vehicle?->plate ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $pass->vehicle ? $pass->vehicle->brand.' · '.$pass->vehicle->color : '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-2">{{ $pass->vehicle?->phone ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($pass->presence === \App\Enums\Direction::In)
                                <span class="text-emerald-700">● Dans le parking</span>
                            @else
                                <span class="text-slate-400">Dehors</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">Aucun pass.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $passes->links() }}</div>
@endsection
