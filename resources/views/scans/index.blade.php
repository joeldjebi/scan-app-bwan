@extends('layouts.app', ['title' => 'Passages · '.$event->name])

@php($input = 'rounded-lg border border-slate-300 px-3 py-2 text-sm')

@section('content')
    @include('events._tabs')

    <form class="mb-4 flex flex-wrap gap-2">
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
        <select name="agent" class="{{ $input }}">
            <option value="">Tous les agents</option>
            @foreach ($agents as $agent)
                <option value="{{ $agent->id }}" @selected((string) request('agent') === (string) $agent->id)>{{ $agent->name }}</option>
            @endforeach
        </select>
        <select name="method" class="{{ $input }}">
            <option value="">QR code et saisie</option>
            @foreach (\App\Enums\ScanMethod::cases() as $method)
                <option value="{{ $method->value }}" @selected(request('method') === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filtrer</button>
    </form>

    @can('admin')
        @if ($scans->total())
            <form id="bulk-delete-scans" method="POST" action="{{ route('events.scans.bulk-destroy', $event) }}" class="mb-3"
                  x-data="{ count: 0 }" @scan-selection.window="count = $event.detail"
                  onsubmit="return confirm('Supprimer les passages sélectionnés ? La position des véhicules sera recalculée.')">
                @csrf @method('DELETE')
                <button :disabled="count === 0" class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-sm text-red-700 hover:bg-red-50 disabled:opacity-40">
                    Supprimer la sélection (<span x-text="count"></span>)
                </button>
            </form>
        @endif
    @endcan

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        @include('scans._table', ['selectable' => true])
    </div>
    <div class="mt-4">{{ $scans->links() }}</div>
@endsection
