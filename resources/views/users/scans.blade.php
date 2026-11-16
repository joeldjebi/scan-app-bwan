@extends('layouts.app', ['title' => 'Historique · '.$user->name])

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
            @if (auth()->user()->canManage($user))
                <a href="{{ route('users.edit', $user) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Modifier le compte</a>
            @endif
        </div>
    </div>

    @include('scans._history', ['resetUrl' => route('users.scans', $user)])
@endsection
