<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <nav class="border-b border-slate-200 bg-white" x-data="{ open: false }">
        <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold text-slate-900">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">P</span>
                    {{ config('app.name') }}
                </a>
                <div class="hidden items-center gap-1 sm:flex">
                    <a href="{{ route('dashboard') }}" class="rounded-md px-3 py-1.5 text-sm {{ request()->routeIs('dashboard', 'events.*') ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:text-slate-900' }}">Événements</a>
                    @can('admin')
                        <a href="{{ route('users.index') }}" class="rounded-md px-3 py-1.5 text-sm {{ request()->routeIs('users.*') ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:text-slate-900' }}">Comptes</a>
                        <a href="{{ route('brands.index') }}" class="rounded-md px-3 py-1.5 text-sm {{ request()->routeIs('brands.*') ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:text-slate-900' }}">Marques</a>
                    @endcan
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden text-sm text-slate-600 sm:inline">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Déconnexion</button>
                </form>
                <button @click="open = !open" class="rounded-md p-1.5 text-slate-600 sm:hidden" aria-label="Menu">☰</button>
            </div>
        </div>
        <div x-show="open" x-cloak class="border-t border-slate-200 px-4 py-2 sm:hidden">
            <a href="{{ route('dashboard') }}" class="block py-2 text-sm">Événements</a>
            @can('admin')
                <a href="{{ route('users.index') }}" class="block py-2 text-sm">Comptes</a>
                <a href="{{ route('brands.index') }}" class="block py-2 text-sm">Marques</a>
            @endcan
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @include('partials.flash')
        @yield('content')
    </main>
</body>
</html>
