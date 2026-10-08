@extends('layouts.app', ['title' => 'Marques'])

@php($input = 'rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Marques de véhicules</h1>
            <p class="text-sm text-slate-500">{{ $activeCount }} marques proposées aux usagers dans le formulaire d'enregistrement, plus « Autre » (saisie libre).</p>
        </div>
        <form method="POST" action="{{ route('brands.store') }}" class="flex gap-2">
            @csrf
            <input name="name" placeholder="Nouvelle marque" required maxlength="50" class="{{ $input }} w-56">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Ajouter</button>
        </form>
    </div>

    <form class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Rechercher une marque…" class="{{ $input }} w-64">
        <select name="status" class="{{ $input }}">
            <option value="">Toutes</option>
            <option value="active" @selected(request('status') === 'active')>Proposées</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Masquées</option>
        </select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filtrer</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Marque</th>
                    <th class="px-4 py-2 font-medium">Véhicules enregistrés</th>
                    <th class="px-4 py-2 font-medium">Statut</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($brands as $brand)
                    <tr x-data="{ edit: false }">
                        <td class="px-4 py-2">
                            <span x-show="!edit" class="font-medium">{{ $brand->name }}</span>
                            <form x-show="edit" x-cloak method="POST" action="{{ route('brands.update', $brand) }}" class="flex gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $brand->name }}" required maxlength="50" class="{{ $input }} py-1">
                                <button class="rounded-lg bg-slate-900 px-3 py-1 text-xs font-semibold text-white">OK</button>
                                <button type="button" @click="edit = false" class="px-1 text-xs text-slate-500">Annuler</button>
                            </form>
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $usage[$brand->name] ?? 0 }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $brand->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">{{ $brand->is_active ? 'Proposée' : 'Masquée' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2 text-right text-xs">
                            <button type="button" @click="edit = true" x-show="!edit" class="text-indigo-600 hover:underline">Renommer</button>
                            <span class="text-slate-300">·</span>
                            <form method="POST" action="{{ route('brands.toggle', $brand) }}" class="inline">
                                @csrf @method('PATCH')
                                <button class="text-indigo-600 hover:underline">{{ $brand->is_active ? 'Masquer' : 'Proposer' }}</button>
                            </form>
                            <span class="text-slate-300">·</span>
                            <form method="POST" action="{{ route('brands.destroy', $brand) }}" class="inline" onsubmit="return confirm('Supprimer la marque {{ $brand->name }} ? Les véhicules déjà enregistrés la conservent.')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Aucune marque.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $brands->links() }}</div>
@endsection
