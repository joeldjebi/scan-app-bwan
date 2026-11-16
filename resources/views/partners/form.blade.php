@extends('layouts.app', ['title' => $partner->exists ? 'Modifier le partenaire' : 'Nouveau partenaire'])

@php($input = 'mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none')
@php($checked = collect(old('event_ids', $granted->all()))->map(fn ($id) => (int) $id))

@section('content')
    <div class="mx-auto max-w-xl">
        <h1 class="mb-6 text-2xl font-bold">{{ $partner->exists ? 'Modifier le partenaire' : 'Nouveau partenaire' }}</h1>

        <form method="POST" action="{{ $partner->exists ? route('partners.update', $partner) : route('partners.store') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @if ($partner->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-slate-700">Nom de l'entité</label>
                <input name="name" value="{{ old('name', $partner->name) }}" required class="{{ $input }}">
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Contact</label>
                    <input name="contact_name" value="{{ old('contact_name', $partner->contact_name) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Téléphone</label>
                    <input type="tel" name="contact_phone" value="{{ old('contact_phone', $partner->contact_phone) }}" class="{{ $input }}">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Email technique</label>
                <input type="email" name="contact_email" value="{{ old('contact_email', $partner->contact_email) }}" class="{{ $input }}">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">URL de webhook <span class="font-normal text-slate-500">(facultatif)</span></label>
                <input type="url" name="webhook_url" value="{{ old('webhook_url', $partner->webhook_url) }}" placeholder="https://partenaire.example.com/webhooks/pass-parking" class="{{ $input }}">
                <p class="mt-1 text-xs text-slate-500">Reçoit les révocations et réactivations de pass de ses événements. Le secret de signature se génère sur la fiche du partenaire.</p>
            </div>

            <fieldset class="rounded-xl border border-slate-200 p-4">
                <legend class="px-1 text-sm font-semibold text-slate-800">Événements ouverts au partenaire</legend>
                @if ($events->isEmpty())
                    <p class="text-sm text-slate-500">Aucun événement en cours ou à venir.</p>
                @else
                    <p class="mb-3 text-xs text-slate-500">Le partenaire ne peut scanner que les pass de ces événements. Un pass d'un autre événement lui apparaît comme inconnu.</p>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($events as $event)
                            <li>
                                <label class="flex items-center gap-3 py-2.5">
                                    <input type="checkbox" name="event_ids[]" value="{{ $event->id }}" @checked($checked->contains($event->id)) class="rounded border-slate-300">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium">{{ $event->name }}</span>
                                        <span class="text-xs text-slate-500">{{ $event->starts_at->translatedFormat('d M Y') }} · {{ $event->status->label() }}</span>
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </fieldset>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $partner->is_active)) class="rounded border-slate-300">
                Partenaire actif <span class="text-slate-500">(décoché : toutes ses clés sont refusées)</span>
            </label>

            <div class="flex justify-end gap-3">
                <a href="{{ $partner->exists ? route('partners.show', $partner) : route('partners.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Annuler</a>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
