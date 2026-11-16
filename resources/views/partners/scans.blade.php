@extends('layouts.app', ['title' => 'Passages · '.$partner->name])

@section('content')
    <div class="mb-6">
        <a href="{{ route('partners.show', $partner) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $partner->name }}</a>
        <div class="mt-1 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Passages de {{ $partner->name }}</h1>
                <p class="text-sm text-slate-500">
                    Scans enregistrés via l'API partenaire, tous événements confondus. La colonne « Opérateur » reprend l'identifiant d'agent envoyé par le partenaire.
                    @unless ($partner->is_active) · <span class="text-red-600">partenaire suspendu</span> @endunless
                </p>
            </div>
            <a href="{{ route('partners.edit', $partner) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Modifier le partenaire</a>
        </div>
    </div>

    @include('scans._history', ['resetUrl' => route('partners.scans', $partner), 'showAgent' => true])
@endsection
