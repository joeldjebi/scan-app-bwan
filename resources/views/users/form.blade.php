@extends('layouts.app', ['title' => $user->exists ? 'Modifier le compte' : 'Nouveau compte'])

@php($input = 'mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none')

@section('content')
    <div class="mx-auto max-w-xl">
        <h1 class="mb-6 text-2xl font-bold">{{ $user->exists ? 'Modifier le compte' : 'Nouveau compte' }}</h1>

        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" x-data="{ role: @js(old('role', $user->role?->value)) }">
            @csrf
            @if ($user->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-slate-700">Nom complet</label>
                <input name="name" value="{{ old('name', $user->name) }}" required class="{{ $input }}">
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Téléphone <span class="font-normal text-slate-500">(identifiant agent / chef)</span></label>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+225 07 00 00 00 00" class="{{ $input }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Email <span class="font-normal text-slate-500">(identifiant admin)</span></label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" class="{{ $input }}">
                </div>
            </div>
            <p class="-mt-3 text-xs text-slate-500">Les agents et chefs se connectent à l'application avec leur numéro de téléphone et leur mot de passe.</p>
            <div>
                <label class="block text-sm font-medium text-slate-700">Rôle</label>
                <select name="role" x-model="role" class="{{ $input }}">
                    @foreach (\App\Enums\UserRole::cases() as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Un agent devient chef agent parking lorsqu'il est nommé chef sur un événement.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Mot de passe @if ($user->exists)<span class="font-normal text-slate-500">(laisser vide pour conserver)</span>@endif</label>
                    <input type="password" name="password" @required(! $user->exists) autocomplete="new-password" class="{{ $input }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Confirmation</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password" class="{{ $input }}">
                </div>
            </div>
            <fieldset x-show="role === 'agent'" x-cloak class="rounded-xl border border-slate-200 p-4">
                <legend class="px-1 text-sm font-semibold text-slate-800">Affectations aux événements</legend>
                @if ($events->isEmpty())
                    <p class="text-sm text-slate-500">Aucun événement en cours ou à venir.</p>
                @else
                    <p class="mb-3 text-xs text-slate-500">Le compte peut scanner les pass des événements auxquels il est affecté. Un seul chef par événement : nommer un chef fait redevenir agent le chef actuel.</p>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($events as $event)
                            @php($current = old("assignments.{$event->id}", $assigned[$event->id] ?? ''))
                            @php($otherChief = $event->staff->first(fn ($chief) => ! $chief->is($user)))
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2.5" x-data="{ value: @js($current) }">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $event->name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $event->starts_at->translatedFormat('d M Y') }} · {{ $event->status->label() }}
                                        @if ($otherChief) · Chef : {{ $otherChief->name }} @endif
                                    </p>
                                    @if ($otherChief)
                                        <p x-show="value === 'chief'" x-cloak class="text-xs font-medium text-amber-700">{{ $otherChief->name }} redeviendra agent parking.</p>
                                    @endif
                                </div>
                                <select name="assignments[{{ $event->id }}]" x-model="value" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                        :class="value && 'border-indigo-400 bg-indigo-50 text-indigo-800'">
                                    <option value="">Non affecté</option>
                                    @foreach (\App\Enums\StaffRole::cases() as $staffRole)
                                        <option value="{{ $staffRole->value }}" @selected($current === $staffRole->value)>{{ $staffRole->label() }}</option>
                                    @endforeach
                                </select>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </fieldset>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-slate-300">
                Compte actif
            </label>

            <div class="flex justify-end gap-3">
                <a href="{{ route('users.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Annuler</a>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Enregistrer</button>
            </div>
        </form>
    </div>
@endsection
