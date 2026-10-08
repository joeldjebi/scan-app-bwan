<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <main class="mx-auto max-w-md px-4 py-6">
        @yield('content')
    </main>
    <p class="pb-6 text-center text-xs text-slate-400">{{ config('app.name') }}</p>
</body>
</html>
