@extends('layouts.public', ['title' => 'Connexion'])

@section('content')
    <div class="mt-12 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <div class="mb-6 flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-xl bg-indigo-600 text-lg font-bold text-white">P</span>
            <div>
                <h1 class="text-lg font-semibold">{{ config('app.name') }}</h1>
                <p class="text-sm text-slate-500">Espace administration</p>
            </div>
        </div>

        @include('partials.flash')

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="login" class="block text-sm font-medium text-slate-700">Email ou numéro de téléphone</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
                <input id="password" name="password" type="password" required
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300"> Se souvenir de moi
            </label>
            <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 font-semibold text-white hover:bg-indigo-700">Se connecter</button>
        </form>
    </div>
@endsection
