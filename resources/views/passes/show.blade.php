@extends('layouts.app', ['title' => 'Pass '.$pass->number])

@section('content')
    @include('events._tabs')

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="font-mono text-lg font-semibold">{{ $pass->number }}</p>
                    <div class="mt-1 flex flex-wrap gap-2">
                        @include('partials.type-badge', ['type' => $pass->type])
                        @include('partials.status-badge', ['status' => $pass->status])
                    </div>
                </div>
            </div>
            <img src="{{ route('events.passes.qr', [$event, $pass]) }}" alt="QR code" class="mx-auto mt-4 size-48">
            <p class="mt-2 break-all text-center text-xs text-slate-500"><a href="{{ $pass->url() }}" target="_blank" class="hover:underline">{{ $pass->url() }}</a></p>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Position</dt><dd>{{ $pass->presence === \App\Enums\Direction::In ? 'Dans le parking' : 'Dehors' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Enregistré le</dt><dd>{{ $pass->registered_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Dernier passage</dt><dd>{{ $pass->last_scanned_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            </dl>

            <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                @if ($pass->status === \App\Enums\PassStatus::Revoked)
                    <form method="POST" action="{{ route('events.passes.restore', [$event, $pass]) }}">
                        @csrf
                        <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">Réactiver</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('events.passes.revoke', [$event, $pass]) }}" onsubmit="return confirm('Révoquer ce pass ? Il sera refusé à chaque scan.')">
                        @csrf
                        <button class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700">Révoquer</button>
                    </form>
                @endif
                @can('admin')
                    <form method="POST" action="{{ route('events.passes.destroy', [$event, $pass]) }}" onsubmit="return confirm('Supprimer définitivement ce pass, son véhicule et ses passages ? Le QR code ne sera plus reconnu.')">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-red-300 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50">Supprimer le pass</button>
                    </form>
                @endcan
                @if ($pass->vehicle)
                    <form method="POST" action="{{ route('events.passes.reset', [$event, $pass]) }}" onsubmit="return confirm('Supprimer le véhicule ? L\'usager pourra réenregistrer un véhicule avec ce QR code.')">
                        @csrf
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Réinitialiser le véhicule</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <h2 class="font-semibold">{{ $pass->vehicle ? 'Véhicule' : 'Enregistrer un véhicule' }}</h2>
            <p class="mb-4 text-sm text-slate-500">
                {{ $pass->vehicle ? 'Modification réservée à l\'administrateur et au chef agent parking.' : 'L\'usager n\'a pas encore enregistré de véhicule. Vous pouvez le faire à sa place.' }}
            </p>
            <form method="POST" action="{{ route('events.passes.vehicle', [$event, $pass]) }}" class="max-w-md">
                @csrf @method('PUT')
                @include('partials.vehicle-fields', ['vehicle' => $pass->vehicle])
                <button class="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Enregistrer</button>
            </form>
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3"><h2 class="font-semibold">Historique des passages</h2></div>
        @include('scans._table', ['scans' => $pass->scans->each->setRelation('pass', $pass)])
    </section>
@endsection
