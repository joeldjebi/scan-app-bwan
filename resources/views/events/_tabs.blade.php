<div class="mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('dashboard') }}" class="text-sm text-slate-500 hover:text-slate-700">← Événements</a>
            <h1 class="mt-1 flex items-center gap-3 text-2xl font-bold">
                @if ($event->logoUrl())
                    <img src="{{ $event->logoUrl() }}" alt="" class="size-10 rounded-lg border border-slate-200 bg-white object-contain p-0.5">
                @endif
                {{ $event->name }}
                @if ($event->primary_color)
                    <span class="flex gap-1" title="Couleurs du formulaire d'enregistrement">
                        <span class="size-3 rounded-full" style="background: {{ $event->primary_color }}"></span>
                        <span class="size-3 rounded-full" style="background: {{ $event->secondary_color }}"></span>
                    </span>
                @endif
            </h1>
            <p class="text-sm text-slate-500">
                <span class="font-mono">{{ $event->code }}</span> · {{ $event->starts_at->translatedFormat('d M Y H\hi') }} → {{ $event->ends_at->translatedFormat('d M Y H\hi') }}@if ($event->location) · {{ $event->location }}@endif · {{ $event->status->label() }}
            </p>
        </div>
        @can('admin')
            <a href="{{ route('events.edit', $event) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Modifier</a>
        @endcan
    </div>
    <div class="mt-4 flex gap-1 border-b border-slate-200 text-sm">
        @foreach (['events.show' => 'Vue d\'ensemble', 'events.passes.index' => 'Pass', 'events.scans.index' => 'Passages'] as $route => $label)
            <a href="{{ route($route, $event) }}" class="-mb-px border-b-2 px-4 py-2 {{ request()->routeIs($route, $route === 'events.passes.index' ? 'events.passes.*' : $route) ? 'border-indigo-600 font-medium text-indigo-700' : 'border-transparent text-slate-600 hover:text-slate-900' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
