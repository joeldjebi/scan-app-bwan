@extends('layouts.app', ['title' => 'Événements'])

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Événements</h1>
            <p class="text-sm text-slate-500">
                @can('admin') Tous les événements @else Les événements dont vous êtes chef agent parking @endcan
            </p>
        </div>
        @can('admin')
            <a href="{{ route('events.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nouvel événement</a>
        @endcan
    </div>

    @if ($events->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
            @can('admin')
                Aucun événement pour le moment.
            @else
                Vous n'êtes chef agent sur aucun événement. Les agents parking utilisent l'application mobile pour scanner.
            @endcan
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($events as $event)
                <a href="{{ route('events.show', $event) }}" class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="font-semibold">{{ $event->name }}</h2>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ match ($event->status) { \App\Enums\EventStatus::Active => 'bg-emerald-100 text-emerald-800', \App\Enums\EventStatus::Closed => 'bg-slate-200 text-slate-700', default => 'bg-amber-100 text-amber-800' } }}">{{ $event->status->label() }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ $event->starts_at->translatedFormat('d M Y, H\hi') }}@if ($event->location) · {{ $event->location }}@endif</p>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-slate-50 py-2"><p class="text-lg font-semibold">{{ $event->passes_count }}</p><p class="text-xs text-slate-500">Pass</p></div>
                        <div class="rounded-lg bg-slate-50 py-2"><p class="text-lg font-semibold">{{ $event->registered_count }}</p><p class="text-xs text-slate-500">Enregistrés</p></div>
                        <div class="rounded-lg bg-slate-50 py-2"><p class="text-lg font-semibold">{{ $event->inside_count }}</p><p class="text-xs text-slate-500">Présents</p></div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $events->links() }}</div>
    @endif
@endsection
