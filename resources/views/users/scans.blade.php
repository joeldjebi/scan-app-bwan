@extends('layouts.app', ['title' => 'Historique · '.$user->name])

@php($input = 'rounded-lg border border-slate-300 px-3 py-2 text-sm')

@section('content')
    <div class="mb-6">
        <a href="{{ route('users.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Comptes</a>
        <div class="mt-1 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Historique de {{ $user->name }}</h1>
                <p class="text-sm text-slate-500">
                    <span class="font-mono">{{ $user->phone ?? $user->email }}</span> · {{ $user->role->label() }}
                    @unless ($user->is_active) · <span class="text-red-600">compte désactivé</span> @endunless
                </p>
            </div>
            <a href="{{ route('users.edit', $user) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Modifier le compte</a>
        </div>
    </div>

    <form class="mb-4 flex flex-wrap items-end gap-2">
        <select name="event" class="{{ $input }}">
            <option value="">Tous les événements</option>
            @foreach ($events as $event)
                <option value="{{ $event->id }}" @selected((string) request('event') === (string) $event->id)>{{ $event->name }}</option>
            @endforeach
        </select>
        <select name="direction" class="{{ $input }}">
            <option value="">Tous les sens</option>
            @foreach (\App\Enums\Direction::cases() as $direction)
                <option value="{{ $direction->value }}" @selected(request('direction') === $direction->value)>{{ $direction->label() }}</option>
            @endforeach
        </select>
        <select name="result" class="{{ $input }}">
            <option value="">Tous les résultats</option>
            @foreach (\App\Enums\ScanResult::cases() as $result)
                <option value="{{ $result->value }}" @selected(request('result') === $result->value)>{{ $result->label() }}</option>
            @endforeach
        </select>
        <label class="text-xs text-slate-500">Du <input type="date" name="from" value="{{ request('from') }}" class="{{ $input }} ml-1"></label>
        <label class="text-xs text-slate-500">au <input type="date" name="to" value="{{ request('to') }}" class="{{ $input }} ml-1"></label>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filtrer</button>
        @if (array_filter(request()->only('event', 'direction', 'result', 'from', 'to')))
            <a href="{{ route('users.scans', $user) }}" class="px-2 py-2 text-sm text-slate-500 hover:underline">Réinitialiser</a>
        @endif
    </form>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            ['Scans', $summary['total']],
            ['Entrées validées', $summary['entries']],
            ['Sorties validées', $summary['exits']],
            ['Refus', $summary['denied']],
            ['Passages forcés', $summary['forced']],
            ['Géolocalisés', $summary['located']],
        ] as [$label, $value])
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-2xl font-bold">{{ number_format($value, 0, ',', ' ') }}</p>
                <p class="text-xs text-slate-500">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="font-semibold">Positions des scans</h2>
            <p class="flex items-center gap-3 text-xs text-slate-500">
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-emerald-600"></span>Validé</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-red-600"></span>Refusé</span>
            </p>
        </div>
        @if ($points->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-slate-500">Aucun scan géolocalisé pour ces filtres.</p>
        @else
            <div id="scan-map" class="h-80 w-full" role="img" aria-label="Carte des positions de scan"></div>
        @endif
    </section>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold">Scans</h2></div>
        @include('scans._table', ['showEvent' => true, 'showAgent' => false])
    </section>
    <div class="mt-4">{{ $scans->links() }}</div>

    @if ($points->isNotEmpty())
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (() => {
                const points = @js($points);
                const map = L.map('scan-map', { scrollWheelZoom: false });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(map);

                const bounds = [];
                points.forEach((point) => {
                    const color = point.granted ? '#059669' : '#dc2626';
                    L.circleMarker([point.lat, point.lng], { radius: 7, color: '#fff', weight: 2, fillColor: color, fillOpacity: 0.9 })
                        .bindTooltip(point.label + (point.accuracy ? ` · ±${point.accuracy} m` : ''))
                        .addTo(map);
                    bounds.push([point.lat, point.lng]);
                });
                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 18 });
            })();
        </script>
    @endif
@endsection
