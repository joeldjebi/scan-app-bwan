@extends('layouts.app', ['title' => 'Partenaires'])

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Partenaires</h1>
            <p class="text-sm text-slate-500">Entités extérieures autorisées à scanner les pass via l'API partenaire, sur les événements qui leur sont ouverts.</p>
        </div>
        <div class="flex gap-2">
            <form><input name="q" value="{{ request('q') }}" placeholder="Rechercher…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></form>
            <a href="{{ route('partners.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nouveau partenaire</a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Partenaire</th>
                    <th class="px-4 py-2 font-medium">Contact</th>
                    <th class="px-4 py-2 font-medium">Événements</th>
                    <th class="px-4 py-2 font-medium">Clés actives</th>
                    <th class="px-4 py-2 font-medium">Passages</th>
                    <th class="px-4 py-2 font-medium">Statut</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($partners as $partner)
                    <tr>
                        <td class="px-4 py-2 font-medium"><a href="{{ route('partners.show', $partner) }}" class="hover:text-indigo-600 hover:underline">{{ $partner->name }}</a></td>
                        <td class="px-4 py-2 text-slate-600">{{ $partner->contact_name ?? '—' }}@if ($partner->contact_email)<span class="block text-xs text-slate-500">{{ $partner->contact_email }}</span>@endif</td>
                        <td class="px-4 py-2">{{ $partner->events_count }}</td>
                        <td class="px-4 py-2">{{ $partner->active_keys_count }}</td>
                        <td class="px-4 py-2"><a href="{{ route('partners.scans', $partner) }}" class="text-indigo-600 hover:underline">{{ number_format($partner->scans_count, 0, ',', ' ') }}</a></td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $partner->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">{{ $partner->is_active ? 'Actif' : 'Suspendu' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2 text-right">
                            <a href="{{ route('partners.show', $partner) }}" class="text-indigo-600 hover:underline">Ouvrir</a>
                            <span class="text-slate-300">·</span>
                            <a href="{{ route('partners.edit', $partner) }}" class="text-indigo-600 hover:underline">Modifier</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">Aucun partenaire pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $partners->links() }}</div>
@endsection
