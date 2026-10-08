@extends('layouts.app', ['title' => 'Comptes'])

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Comptes</h1>
            <p class="text-sm text-slate-500">Administrateurs et agents parking. Le rôle de chef se définit par événement.</p>
        </div>
        <div class="flex gap-2">
            <form><input name="q" value="{{ request('q') }}" placeholder="Rechercher…" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"></form>
            <a href="{{ route('users.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">+ Nouveau compte</a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 font-medium">Nom</th>
                    <th class="px-4 py-2 font-medium">Téléphone</th>
                    <th class="px-4 py-2 font-medium">Email</th>
                    <th class="px-4 py-2 font-medium">Rôle</th>
                    <th class="px-4 py-2 font-medium">Événements</th>
                    <th class="px-4 py-2 font-medium">Statut</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-4 py-2 font-medium">
                            {{ $user->name }}
                            @if ($user->isOwner())
                                <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800" title="Compte protégé : seul son titulaire peut le modifier">Propriétaire</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 font-mono">{{ $user->phone ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $user->email ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $user->role->label() }}</td>
                        <td class="px-4 py-2">{{ $user->events_count }}</td>
                        <td class="px-4 py-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">{{ $user->is_active ? 'Actif' : 'Désactivé' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2 text-right">
                            <a href="{{ route('users.scans', $user) }}" class="text-indigo-600 hover:underline">Historique</a>
                            <span class="text-slate-300">·</span>
                            @if (auth()->user()->canManage($user))
                                <a href="{{ route('users.edit', $user) }}" class="text-indigo-600 hover:underline">Modifier</a>
                            @else
                                <span class="text-slate-400" title="Seul le propriétaire peut modifier ce compte">Protégé</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
@endsection
