<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head')
    <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    <style>
        #app-loader { position: fixed; inset: 0 auto auto 0; z-index: 60; height: 3px; width: 0; opacity: 0; background: linear-gradient(90deg, #6366f1, #a855f7); box-shadow: 0 0 8px rgba(99, 102, 241, .6); }
        .app-spinner { display: inline-block; width: 1em; height: 1em; margin-right: .4em; vertical-align: -.15em; border: 2px solid currentColor; border-right-color: transparent; border-radius: 9999px; animation: app-spin .7s linear infinite; }
        [data-loading] { cursor: wait; opacity: .75; }
        .app-invalid { border-color: #f87171 !important; box-shadow: 0 0 0 3px rgba(248, 113, 113, .2) !important; }
        .app-toast { opacity: 0; transform: translateY(8px); transition: opacity .25s, transform .25s; }
        .app-toast.is-visible { opacity: 1; transform: none; }
        #app-progress { opacity: 0; transition: opacity .2s; }
        #app-progress.is-open { opacity: 1; }
        #app-progress .is-indeterminate { animation: app-indeterminate 1.2s ease-in-out infinite; }
        @keyframes app-spin { to { transform: rotate(360deg); } }
        @keyframes app-indeterminate { 0% { margin-left: -35%; } 100% { margin-left: 100%; } }
        @media (prefers-reduced-motion: reduce) { .app-spinner, #app-progress .is-indeterminate { animation-duration: 2s; } }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    {{-- Éléments persistants (hors de #app-page, qui est remplacé à chaque navigation AJAX) --}}
    <div id="app-loader" role="progressbar" aria-label="Chargement"></div>
    <div id="app-toasts" class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2" aria-live="polite"></div>
    <div id="app-progress" hidden class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-live="polite">
            <div class="flex items-center justify-between gap-3">
                <p data-title class="font-semibold">Chargement…</p>
                <p data-percent class="font-mono text-sm text-slate-500"></p>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                <div data-bar class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-[width] duration-300"></div>
            </div>
            <p data-detail class="mt-3 text-sm text-slate-500"></p>
            <ul data-files class="mt-4 max-h-64 space-y-2 overflow-y-auto empty:hidden"></ul>
            <div data-actions hidden class="mt-4 flex justify-end gap-2">
                <button type="button" data-download-all hidden class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Tout télécharger</button>
                <button type="button" data-close class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Fermer</button>
            </div>
        </div>
    </div>

    <div id="app-page">
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
                    @can('owner')
                        <a href="{{ route('audit.index') }}" class="rounded-md px-3 py-1.5 text-sm {{ request()->routeIs('audit.*') ? 'bg-slate-100 font-medium text-slate-900' : 'text-slate-600 hover:text-slate-900' }}">Journal</a>
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
            @can('owner')
                <a href="{{ route('audit.index') }}" class="block py-2 text-sm">Journal</a>
            @endcan
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @include('partials.flash')
        @yield('content')
    </main>
    </div>
</body>
</html>
